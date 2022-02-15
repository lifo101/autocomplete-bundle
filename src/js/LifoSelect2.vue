<script>
import $ from 'jquery'
import 'select2'
import '@/../../vendor/lifo/autocomplete-bundle/src/js/autocomplete'
import merge from 'lodash/merge'
import {cloneDeep} from 'lodash/lang';

export default {
    inheritAttrs: false,
    props: {
        value: {default: null},
        // provide all props in a single config object
        config: {type: Object, default: () => ({})},
        // or provide each option individually
        data: {type: Array, default: () => []},
        url: {type: String, default: null},
        cache: {type: Boolean, default: true},
        cacheTimeout: {type: Number, default: 60000},
        delay: {type: Number, default: 250},
        dataType: {type: String, default: 'json'},
        dropdownParent: null,
        title: {type: String, default: ''},
        placeholder: {type: String, default: ''},
        theme: {type: String, default: 'bootstrap'},
        multiple: {type: Boolean, default: false},
        tags: {type: Boolean, default: false},
        scroll: {type: Boolean, default: true},
        autoStart: {type: Boolean, default: true},
        allowClear: {type: Boolean, default: true},
        language: {type: String, default: 'en'},
        textProperty: {type: String, default: 'text'},
        minimumInputLength: {type: [Number, String], default: 1},
        maximumSelectionLength: {type: [Number, String], default: 0},
        closeOnSelect: {type: Boolean, default: true},
        submitOnSelect: {type: Boolean, default: false},
        debug: {type: Boolean, default: false},
        disabled: {type: Boolean, default: false},
        dropdownAutoWidth: {type: Boolean, default: false},
        dir: {type: String, default: 'ltr'},
        tagIdPrefix: {type: String, default: '__NEWTAG__:'},
        tagPrefix: {type: String, default: ''},
        tokenSeparators: {type: Array, default: null},
        width: {type: String, default: 'resolve'},
        scrollAfterSelect: {type: Boolean, default: true},
    },
    data() {
        return {}
    },
    mounted() {
        const vm = this;

        $(this.$el)
            .lifoSelect2(this.cfg)
            .on('select2:unselect select2:select', function (e, args) {
                // relay events [select,unselect] to parent; sending the actual selected objects or null
                const data  = cloneDeep($(this).select2('data'));
                const value = vm.fromSelect2Data(data.length === 0 ? null : vm.cfg.multiple ? data : data[0]);
                vm.$emit(e.type.substr(e.type.indexOf(':') + 1), e, value);
                if (!(args && 'ignore' in args)) {
                    vm.$emit('input', vm.cfg.multiple ? data : data[0]);
                }
            })
            .on('change', function (e, args) {
                // relay change to model; 'ignore' will be true when triggered from watcher
                if (!(args && 'ignore' in args)) {
                    vm.$emit('change', vm.multiple ? $(this).find(':selected').get().map(o => o.value) : e.target.value);
                }
            });

        // set initial value so Select2 will display it
        if (this.localValue !== null) {
            this.update(this.localValue);
        }
    },
    destroyed() {
        $(this.$el).off().select2('destroy');
    },
    methods: {
        clear() {
            $(this.$el).val(null).trigger('change').trigger('select2:unselect');
        },
        /**
         * update select2 component
         * @param value
         */
        update(value) {
            const $el = $(this.$el);
            if (typeof value === 'object' && value !== null) {
                if (Array.isArray(value)) {
                    value.forEach(v => this.update(v));
                } else {
                    !this.exists(value) && $el.append(new Option(value[this.textProperty] ?? value.id, value.id, true, true));
                }
            } else {
                !this.exists(value) && $(this.$el).val(value);
            }
            $el.trigger('change', {ignore: true});
        },
        exists(value) {
            const data = $(this.$el).data('select2').data();
            return data.some(o => o === value || o?.[this.textProperty] && o[this.textProperty] === value[this.textProperty]);
        },
        fromSelect2Data(data) {
            if (data === null) return null;
            if (Array.isArray(data)) {
                return data.map(d => this.fromSelect2Data(d));
            }
            delete data._resultId;
            delete data.selected;
            delete data.disabled;
            delete data.element;
            if (this.textProperty !== 'text') {
                delete data.text;
            }

            return data;
        },
    },
    computed: {
        localValue() {
            if (Array.isArray(this.value)) {
                return this.value.map(v => cloneDeep(v));
            } else if (typeof this.value === 'object' && this.value !== null) {
                return cloneDeep(this.value);
            }
            return this.value;
        },
        cfg() {
            return merge({
                data: this.data ? this.data.map(d => cloneDeep(d)) : null,
                multiple: this.multiple,
                theme: this.theme,
                placeholder: this.placeholder,
                allowClear: this.allowClear,
                minimumInputLength: this.minimumInputLength,
                maximumSelectionLength: this.maximumSelectionLength,
                closeOnSelect: this.closeOnSelect,
                dropdownAutoWidth: this.dropdownAutoWidth,
                tags: this.tags,
                language: this.language,
                debug: this.debug,
                disabled: this.disabled,
                scroll: this.scroll,
                dir: this.dir,
                text_property: this.textProperty,
                tag_id_prefix: this.tagIdPrefix,
                tag_prefix: this.tagPrefix,
                submit_on_select: this.submitOnSelect,
                dropdownParent: this.dropdownParent ? $(this.dropdownParent) : null,
                ajax: this.url ? {
                    url: this.url,
                    delay: this.delay,
                    cache: this.cache,
                    cacheTimeout: this.cacheTimeout,
                } : null,
            }, this.config);
        }
    },
    watch: {
        value(value) {
            // detect change from outside model to our internal value
            this.update(value);
        },
        cfg(cfg) {
            // todo: not sure about this...
            $(this.$el).select2(cfg);
        }
    },
}
</script>

<template>
    <select style="width:100%">
        <slot>
            <!--
            need an empty option if tagging is enabled to work-around a bug where the first tag entered  does not fire
            an event in Chrome
            -->
            <option v-if="tags"></option>
        </slot>
    </select>
</template>
