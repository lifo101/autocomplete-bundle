<script>
import Vue from 'vue'
import Multiselect from 'vue-multiselect';
import {ObserveVisibility} from 'vue-observe-visibility'

Vue.directive('observe-visibility', ObserveVisibility);

function debounce(func, timeout = 250) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            func.apply(this, args);
        }, timeout);
    };
}

let NEXT_IDX = -1;

export default {
    name: "MultiselectAjax",
    components: {Multiselect},
    inheritAttrs: false,
    props: {
        value: {},
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
    },
    data() {
        return {
            loading: false,
            response: null,
            view: null,
            page: 1,
            pages: [],
            query: '',
            delayedOnChange: null,
            abortController: null,
            opened: false,
            tags: [],
            localValue: null, // instead of value prop
        }
    },
    created() {
        const vm             = this;
        this.delayedOnChange = debounce(query => {
            this.page  = 1;
            this.pages = [];
            vm.onChange(query);
        }, this.delay);

        this.localValue = JSON.parse(JSON.stringify(this.value));
    },
    methods: {
        onOpen(...args) {
            if (!this.opened && this.minInputLength <= 0) {
                this.opened = true;
                this.onChange('');
            }
            this.$emit('open', ...args);
        },
        onChange(query) {
            if (query.length < this.minInputLength) {
                this.response = null;
                this.pages    = [];
                this.page     = 1;
                return;
            }
            const url  = new URL(this.url, window.location.href);
            url.search = new URLSearchParams([...url.searchParams.entries(), [this.searchParam, query], [this.pageParam, this.page]]).toString();
            if (this.loading && this.abortController) this.abortController.abort();
            this.query           = query;
            this.abortController = new AbortController();
            this.loading         = true;
            fetch(url.href, {signal: this.abortController.signal})
                .then(response => response.json())
                .then(data => {
                    this.response = this.processResponse(data);
                    if (Array.isArray(this.response)) this.pages.push(this.response);
                })
                .catch(e => {
                    if (e.name === "AbortError") {
                        this.response = null;
                    }
                })
                .finally(() => {
                    this.abortController = null;
                    this.loading         = false;
                })
        },
        onTag(name) {
            const tag = {[this.label]: name, [this.trackBy]: NEXT_IDX--};
            this.$emit('add-tag', tag);
            this.tags ??= [];
            this.tags.push(tag);
            if (this.multiple) {
                this.localValue = [tag, ...(this.localValue ?? [])];
            } else {
                this.localValue = tag;
            }
        },
        onRemove(opt, id) {
            this.$emit('remove', opt, id);
            if (!!this.tags) {
                this.tags = this.tags.filter(t => t[this.trackBy] !== opt[this.trackBy]);
                if (this.tags.length === 0) this.tags = null;
            }
        },
        onSelect(opt, id) { // won't trigger for non-tags
            this.$emit('select', opt, id);
            // clear any internal tags if we're not allowing multiple options
            if (!this.multiple && !!this.tags) {
                this.tags = null;
            }
        },
        processResponse(data) {
            // save view if it's available
            this.view = data?.['hydra:view'] ?? null;
            return data?.['hydra:member'] ?? data?.results ?? data;
        },
        fetchNextPage() {
            if (this.hasMore) {
                this.page++;
                this.onChange(this.query);
            }
        },
        reachedEndOfList(reached) {
            if (reached && !this.loading) {
                this.fetchNextPage();
            }
        },
        activate() {
            this.$refs.multiselect.activate();
        },
        deactivate() {
            this.$refs.multiselect.deactivate();
        },
        getValue(v) {
            return v?.[this.trackBy] ?? '';
        }
    },
    computed: {
        minInputLength() {
            return Number(this.minimumInputLength);
        },
        options() {
            const list = this.pages.flat();
            if (!!this.prependOptions) list.unshift(...this.prependOptions);
            if (!!this.appendOptions) list.push(...this.appendOptions);
            // always make sure selected value(s) are in the options list (so they can be removed by clicking)
            if (!!this.localValue) {
                const values = (Array.isArray(this.localValue) ? this.localValue : [this.localValue])
                list.unshift(...values.filter(v => !list.some(l => l[this.trackBy] === v[this.trackBy])));
            }
            return list;
        },
        hasMore() {
            // did we get JSONLD response?
            if (!!this.view?.['hydra:last']) {
                return this.view['hydra:last'] !== this.view['@id'];
            }
            return this.response?.more ?? false;
        },
        classes() {
            return !!this.size && this.size !== '' ? `multiselect-${this.size}` : null;
        }
    }
}
</script>

<template>
    <div>
        <multiselect ref="multiselect"
                     v-bind="$attrs"
                     v-model="localValue"
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
                     @input="$emit('input', $event)"
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
            <template v-for="(_, slot) in $scopedSlots" #[slot]="props">
                <slot :name="slot" v-bind="props"/>
            </template>
        </multiselect>
        <template v-if="formName">
            <input v-for="v in multiple ? localValue : [localValue]" type="hidden" :value="getValue(v)" :name="formName"/>
        </template>
    </div>
</template>
