import $ from 'jquery';

function processResults(data) {
    const response = {results: [], pagination: {more: false}};
    if (Array.isArray(data)) {
        response.results = data;
    } else {
        response.results         = data?.results ?? [];
        // BC: allow 'more' to be 'data.more' or 'data.pagination.more'
        response.pagination.more = data?.pagination?.more ?? data?.more ?? false;
    }
    return response;
}

export function initSelect2(el, options = null) {
    const $el = $(el), cfg = Object.assign({}, options ?? {}, $el.data('config'));
    if (!cfg) {
        console.error('No data-config attribute found for Select2 element', el);
        return;
    }

    const createTag = function ({term}) {
        term = $.trim(term);
        return term === '' ? null : {
            id: (cfg.tag_id_prefix ?? '') + term,
            text: (cfg.tag_prefix ?? '') + term,
            tag: true, // not useful yet
        };
    }

    const settings = Object.assign({}, cfg, {createTag});

    if (settings.hasOwnProperty('ajax') && settings.ajax !== null) {
        Object.assign(settings.ajax, {processResults})
    }

    if (settings.hasOwnProperty('data') && settings.data !== null) {
        // covert 'choices' keyed array into an indexed array
        if (typeof settings.data === 'object' && !Array.isArray(settings.data)) {
            settings.data = Object.entries(settings.data).map(d => ({id: d[1], text: d[0]}))
        }
        // make sure 'text' field is present
        settings.data = settings.data.map(r => ({text: r[cfg.text_property ?? 'text'] ?? '*unknown*', ...r}));
    }

    $el.select2(settings);
    if (cfg.submit_on_select) {
        $el.on('select2:select select2:unselect', e => e.target.closest('form').submit())
    }
    return $el;
}

$.fn.lifoSelect2 = function(options) {
    return this.each((i, el) => initSelect2(el, options));
}

$(() => $('.lifo-select2').lifoSelect2())
