import $ from 'jquery';

export function initSelect2(el, options = null) {
    const $el = $(el), cfg = Object.assign({}, options ?? {}, $el.data('config'));
    if (!cfg) {
        console.error('No data-config attribute found for Select2 element', el);
        return;
    }

    // map the text property to 'text' for Select2
    const mapResults = function (ary) {
        // fallback to r.id if r[text_property] doesn't exist
        return Array.isArray(ary) && cfg.text_property && cfg.text_property !== 'text'
            ? ary.map(r => r.hasOwnProperty('text') ? r : {text: r[cfg.text_property] ?? r.id, ...r})
            : ary;
    }

    const processResults = function (data) {
        const response = {results: [], pagination: {more: false}};
        if (Array.isArray(data)) {
            response.results = data;
        } else if (typeof data === 'object' && 'hydra:member' in data) {
            response.results = data['hydra:member'];
            response.pagination.more = !!data['hydra:view']?.['hydra:next'];
        } else {
            response.results         = data?.results ?? [];
            // BC: allow 'more' to be 'data.more' or 'data.pagination.more'
            response.pagination.more = data?.pagination?.more ?? data?.more ?? false;
        }

        response.results = mapResults(response.results);
        return response;
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

    if (settings.hasOwnProperty('ajax') && settings.ajax !== null && !!!settings.ajax.processResults) {
        Object.assign(settings.ajax, {processResults})
        // override ?term ajax parameter
        if (!!cfg.term_param) {
            settings.ajax.data = function(params) {
                const p = {...params, [cfg.term_param]: params.term};
                delete p.term;
                return p;
            }
        }
    }

    if (settings.hasOwnProperty('data') && settings.data !== null) {
        // covert 'choices' keyed array into an indexed array
        if (typeof settings.data === 'object' && !Array.isArray(settings.data)) {
            settings.data = Object.entries(settings.data).map(d => ({id: d[1], text: d[0]}))
        }
        settings.data = mapResults(settings.data);
    }

    $el.select2(settings);
    if (cfg.submit_on_select) {
        $el.on('select2:select select2:unselect', e => e.target.closest('form').submit())
    }
    return $el;
}

$.fn.lifoSelect2 = function (options) {
    return this.each((i, el) => initSelect2(el, options));
}

$(() => $('.lifo-select2').lifoSelect2())
