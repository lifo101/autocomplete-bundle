<script setup>
import Multiselect from 'vue-multiselect';
import {ObserveVisibility as vObserveVisibility} from 'vue-observe-visibility'
import {computed, ref, useAttrs, watchEffect} from 'vue';

defineOptions({inheritAttrs: false})
defineExpose({activate, deactivate})
const props      = defineProps({
    url: {type: String, required: true},
    trackBy: {type: String, default: 'id'},
    label: {type: String, default: 'name'},
    minimumInputLength: {type: [Number, String], default: 0},
    delay: {type: Number, default: 250},
    multiple: {type: Boolean, default: false},
    scroll: {type: Boolean, default: true},
    showLabels: {type: Boolean, default: true},
    allowEmpty: {type: Boolean, default: true},
    internalSearch: {type: Boolean, default: false},
    preserveSearch: {type: Boolean, default: true},
    searchParam: {type: String, default: 'q'},
    size: {type: String, default: null},
    optionHeight: {type: Number, default: 40},
    pageParam: {type: String, default: 'page'},
    prependOptions: {type: Array, default: null},
    appendOptions: {type: Array, default: null},
    formName: {type: String, default: null},
})
const emit       = defineEmits(['select', 'remove', 'searchChange', 'tag', 'open', 'close'])
const localValue = defineModel()
const $attrs     = useAttrs()

const $multiselect    = ref() // $refs.multiselect
const NEXT_IDX        = ref(-1)
const loading         = ref(false)
const response        = ref(null)
const view            = ref(null)
const page            = ref(1)
const pages           = ref([])
const query           = ref('')
const delayedOnChange = ref(null)
const abortController = ref(null)
const opened          = ref(false)
const tags            = ref([])

const minInputLength = computed(() => Number(props.minimumInputLength))
const options        = computed(() => {
    const list = pages.value.flat();
    if (!!props.prependOptions) list.unshift(...props.prependOptions);
    if (!!props.appendOptions) list.push(...props.appendOptions);
    // always make sure selected value(s) are in the options list (so they can be removed by clicking)
    if (!!localValue.value) {
        const values = (Array.isArray(localValue.value) ? localValue.value : [localValue.value])
        list.unshift(...values.filter(v => !list.some(l => l[props.trackBy] === v[props.trackBy])));
    }
    return list;
})
const hasMore        = computed(() => !!view.value?.['hydra:last'] ? view.value['hydra:last'] !== view.value['@id'] : response.value?.more ?? false)
const classes        = computed(() => !!props.size && props.size !== '' ? `multiselect-${props.size}` : null)

watchEffect(() => {
    delayedOnChange.value = debounce(query => {
        page.value  = 1;
        pages.value = [];
        onChange(query);
    }, props.delay);
})

function doEmit(event, ...args) {
    emit(event, ...args)
    if (args.length === 1 && Array.isArray(args[0])) args = args[0]
    // dispatch event so non-vuejs code can react
    $multiselect.value?.$el.dispatchEvent(new CustomEvent(event, {bubbles: true, detail: args}))
}

function onOpen(...args) {
    if (!opened.value && minInputLength.value <= 0) {
        opened.value = true;
        onChange('');
    }
    doEmit('open', ...args);
}

function onChange(str) {
    if (str.length < minInputLength.value) {
        response.value = null;
        pages.value    = [];
        page.value     = 1;
        return;
    }
    const url  = new URL(props.url, window.location.href);
    url.search = new URLSearchParams([...url.searchParams.entries(), [props.searchParam, str], [props.pageParam, page.value]]).toString();
    if (loading.value && abortController.value) abortController.value.abort();
    query.value           = str;
    abortController.value = new AbortController();
    loading.value         = true;
    fetch(url.href, {signal: abortController.value.signal})
        .then(response => response.json())
        .then(data => {
            response.value = processResponse(data);
            if (Array.isArray(response.value)) pages.value.push(response.value);
        })
        .catch(e => {
            if (e.name === "AbortError") {
                response.value = null;
            }
        })
        .finally(() => {
            abortController.value = null;
            loading.value         = false;
        })
}

function onTag(name) {
    const tag = {[props.label]: name, [props.trackBy]: NEXT_IDX.value--};
    doEmit('add-tag', tag);
    tags.value ??= [];
    tags.value.push(tag);
    if (props.multiple) {
        localValue.value = [tag, ...(localValue.value ?? [])];
    } else {
        localValue.value = tag;
    }
}

function onRemove(opt, id) {
    doEmit('remove', opt, id);
    if (!!tags.value) {
        tags.value = tags.value.filter(t => t[props.trackBy] !== opt[props.trackBy]);
        if (tags.value.length === 0) tags.value = null;
    }
}

function onSelect(opt, id) { // won't trigger for non-tags
    doEmit('select', opt, id);
    // clear any internal tags if we're not allowing multiple options
    if (!props.multiple && !!tags.value) {
        tags.value = null;
    }
}

function processResponse(data) {
    // save view if it's available
    view.value = data?.['hydra:view'] ?? null;
    return data?.['hydra:member'] ?? data?.results ?? data;
}

function fetchNextPage() {
    if (hasMore.value) {
        page.value++;
        onChange(query.value);
    }
}

function reachedEndOfList(reached) {
    if (reached && !loading.value) {
        fetchNextPage();
    }
}

function activate() {
    $multiselect.value?.activate();
}

function deactivate() {
    $multiselect.value?.deactivate();
}

function getValue(v) {
    return v?.[props.trackBy] ?? '';
}

function updateValue(v) {
    if (v === null) localValue.value = null;
    localValue.value = JSON.parse(JSON.stringify(v));
}

function debounce(func, timeout = 250) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            func.apply(this, args);
        }, timeout);
    };
}
</script>

<template>
    <multiselect ref="$multiselect"
                 v-model="localValue"
                 v-bind="$attrs"
                 :options="options"
                 :option-height="optionHeight"
                 :multiple="multiple"
                 :loading="loading"
                 :track-by="trackBy"
                 :label="label"
                 :allow-empty="allowEmpty"
                 :internal-search="internalSearch"
                 :preserve-search="preserveSearch"
                 :show-labels="showLabels"
                 :class="classes"
                 @tag="onTag"
                 @remove="onRemove"
                 @open="onOpen"
                 @select="onSelect"
                 @search-change="delayedOnChange">
        <template #noResult="{search}" v-if="!loading">
                    <span v-if="search.length < minInputLength" class="text-wrap">
                        Enter {{ minInputLength - search.length }} more
                        character{{ minInputLength - search.length > 1 ? 's' : '' }} to search
                    </span>
            <span v-else class="text-wrap">No matches found.</span>
        </template>
        <template #noOptions v-if="!loading">
            <span class="text-wrap">Enter search criteria to show options.</span>
        </template>
        <template #beforeList v-if="loading">
            <li class="multiselect__option">
                <i class="fa fa-refresh fa-fw fa-spin"/>
                Searching ...
            </li>
        </template>
        <template #afterList v-if="!loading">
            <div v-observe-visibility="reachedEndOfList" v-if="hasMore"/>
        </template>
        <!-- Pass down slots from parent -->
        <template v-for="(_, slot) in $slots" #[slot]="props">
            <slot :name="slot" v-bind="props"/>
        </template>
    </multiselect>
    <template v-if="formName">
        <input v-for="v in multiple ? localValue : [localValue]" type="hidden" :value="getValue(v)"
               :name="formName"/>
    </template>
</template>
