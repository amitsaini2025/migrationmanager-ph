/**
 * jQuery bridge for Tom Select (window.TomSelect from vendor-libs.js).
 * Use $('.mm-select').mmSelect({ ... }); options mirror the legacy plugin shape (ajax, templateResult, etc.).
 *
 * Canonical Custom events on the original <select> (listen with .on(...) / off):
 *   mmselect:select, mmselect:unselect, mmselect:clear, mmselect:open, mmselect:close
 * Each carries `e.params`; select/unselect include `params.data`.
 * Do not rely on Select2 class names (.select2-*) or select2:* jQuery events; runtime DOM is Tom Select (.ts-*).
 */
(function (factory) {
  if (typeof jQuery !== 'undefined' && typeof TomSelect !== 'undefined') {
    factory(jQuery, TomSelect);
  }
})(function ($, TomSelect) {
  'use strict';

  var INIT_CLASS = 'mm-select-initialized';
  var DATA_KEY = 'mmSelect';

  function identityEscapeMarkup(fn) {
    if (typeof fn !== 'function') return false;
    try {
      return fn('<x>') === '<x>';
    } catch (e) {
      return false;
    }
  }

  function jqToHtml(x) {
    if (x == null) return '';
    if (typeof x === 'string') return x;
    if (x.nodeType) return x.outerHTML;
    if (x.jquery) return x[0] ? x[0].outerHTML : '';
    return String(x);
  }

  /**
   * Tom Select parses render strings: if there's no '<', K() uses document.querySelector(str),
   * so plain labels like "Vipul Kumar" break (SyntaxError/null) and remove_button's appendChild crashes.
   * Always expose labels as minimal HTML fragments (matches Tom Select's default '<div>...</div>' pattern).
   */
  function wrapTomSelectRenderOutput(content) {
    if (content == null) content = '';
    content = String(content);
    if (content.trim() === '') {
      return '<div class="mm-ts-render">\u200b</div>';
    }
    if (content.indexOf('<') !== -1) {
      return content;
    }
    return '<div class="mm-ts-render">' + content + '</div>';
  }

  /**
   * For plain-text values: optionally trust HTML fragments; otherwise run through TS escape().
   */
  function stringOutputForTomSelect(str, trustHtml, escape) {
    if (str == null || str === '') return '';
    if (trustHtml && str.indexOf('<') !== -1) return str;
    return escape(String(str));
  }

  function legacyResultSearchText(opt) {
    var parts = [];
    if (opt.name) parts.push(String(opt.name));
    if (opt.client_reference) parts.push(String(opt.client_reference));
    if (opt.email) parts.push(String(opt.email));
    if (opt.phones) parts.push(String(opt.phones));
    if (opt.emails) parts.push(String(opt.emails));
    return parts.join(' ').trim();
  }

  function legacyResultToOpt(r) {
    if (!r || typeof r !== 'object') return { value: '', text: '' };
    var opt = {};
    for (var k in r) {
      if (Object.prototype.hasOwnProperty.call(r, k)) opt[k] = r[k];
    }
    if (opt.value === undefined && opt.id !== undefined) opt.value = String(opt.id);
    if (opt.value === undefined) opt.value = '';
    if (opt.text === undefined && opt.label !== undefined) opt.text = opt.label;
    if (opt.text === undefined && opt.name !== undefined) opt.text = opt.name;
    var searchText = legacyResultSearchText(opt);
    if (searchText) opt.text = searchText;
    if (opt.text === undefined) opt.text = String(opt.value);
    return opt;
  }

  function optionToLegacyData(raw, valueKey) {
    var d = $.extend(true, {}, raw);
    d.id = raw.id !== undefined ? raw.id : raw.value;
    if (d.text === undefined && raw.label !== undefined) d.text = raw.label;
    if (d.text === undefined && raw.name !== undefined) d.text = raw.name;
    if (d.text === undefined) d.text = String(valueKey);
    return d;
  }

  function bindBridgeEvents(ts, el) {
    ts.on('item_add', function (value) {
      var raw = ts.options[value];
      var data = raw ? optionToLegacyData(raw, value) : { id: value, text: value };
      $(el).trigger($.Event('mmselect:select', { params: { data: data } }));
    });

    ts.on('item_remove', function (value) {
      var raw = ts.options[value];
      var data = raw ? optionToLegacyData(raw, value) : { id: value, text: value };
      $(el).trigger($.Event('mmselect:unselect', { params: { data: data } }));
    });

    ts.on('clear', function () {
      $(el).trigger($.Event('mmselect:clear', { params: {} }));
    });

    ts.on('dropdown_open', function () {
      $(el).trigger($.Event('mmselect:open', { params: {} }));
    });

    ts.on('dropdown_close', function () {
      $(el).trigger($.Event('mmselect:close', { params: {} }));
    });
  }

  function applyWidth($el, width) {
    if (!width) return;
    var $w = $el.next('.ts-wrapper');
    if ($w.length) {
      if (width === '100%' || width === 'resolve') $w.css('width', '100%');
      else $w.css('width', width);
    }
  }

  /** Build Tom Select settings from legacy dropdown-style options */
  function buildSettings($el, legacyOpts) {
    var isMulti = !!(legacyOpts.multiple || $el.prop('multiple'));
    var plugins = [];

    if (isMulti) plugins.push('remove_button');
    if (!isMulti && legacyOpts.allowClear) plugins.push('clear_button');

    var maxItems = isMulti ? null : 1;
    if (isMulti && legacyOpts.maximumSelectionLength != null) {
      maxItems = legacyOpts.maximumSelectionLength;
    }

    var opts = {
      plugins: plugins,
      diacritics: true,
      maxItems: maxItems,
      allowEmptyOption: !!legacyOpts.allowClear,
      closeAfterSelect: legacyOpts.closeOnSelect === false ? false : true,
      loadThrottle: legacyOpts.ajax && legacyOpts.ajax.delay != null ? legacyOpts.ajax.delay : 300,
      placeholder: legacyOpts.placeholder || undefined,
      maxOptions: null,
    };

    if (legacyOpts.minimumResultsForSearch === Infinity) {
      opts.searchField = [];
    }

    if (legacyOpts.dropdownParent) {
      var dp = legacyOpts.dropdownParent;
      // Tom Select 2.6.x only runs positionDropdown() when settings.dropdownParent === "body" (string).
      // Passing document.body breaks menu placement (e.g. bottom of viewport).
      if (typeof dp === 'string') {
        opts.dropdownParent = dp === 'body' ? 'body' : document.querySelector(dp);
      } else {
        var el = dp && dp.jquery ? dp[0] : dp;
        if (el === document.body) {
          opts.dropdownParent = 'body';
        } else {
          opts.dropdownParent = el;
        }
      }
    }

    if (legacyOpts.dropdownCssClass) {
      opts.dropdownClass = ('ts-dropdown ' + String(legacyOpts.dropdownCssClass).trim()).trim();
    }

    // Select2 tags:true → Tom Select create (e.g. custom checklist names in modals)
    if (legacyOpts.create != null) {
      opts.create = legacyOpts.create;
    } else if (legacyOpts.tags === true) {
      opts.create = true;
    }
    if (legacyOpts.createOnBlur != null) {
      opts.createOnBlur = legacyOpts.createOnBlur;
    }
    if (legacyOpts.createFilter != null) {
      opts.createFilter = legacyOpts.createFilter;
    }

    if (legacyOpts.ajax) {
      opts.persist = false;
      if (isMulti) opts.hideSelected = true;
      // Remote search already filters on the server; do not re-filter by display name
      // (e.g. query "VIPL2400001" vs result name "Vipul Kumar").
      opts.score = function () {
        return function () {
          return 1;
        };
      };

      var minLen = legacyOpts.minimumInputLength;
      if (minLen === undefined) minLen = 0;
      opts.shouldLoad = function (query) {
        return query.length >= minLen;
      };

      var ajax = legacyOpts.ajax;
      var clearOnSearch = ajax.clearOnSearch !== false;
      opts.load = function (query, callback) {
        if (query.length < minLen) {
          callback();
          return;
        }
        var ts = this;
        if (clearOnSearch) {
          callback();
          if (ts && typeof ts.clearOptions === 'function') {
            ts.clearOptions();
          }
        }
        var payload =
          typeof ajax.data === 'function'
            ? ajax.data({ term: query, page: 1 })
            : $.extend({}, ajax.data || {});
        // Legacy Select2 configs often omitted ajax.data; /clients/get-recipients requires ?q=
        if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
          if (Object.keys(payload).length === 0) {
            payload.q = query;
          }
          if (ajax.cache !== true) {
            payload._ = Date.now();
          }
        }
        $.ajax({
          url: ajax.url,
          type: ajax.type || 'GET',
          dataType: ajax.dataType || 'json',
          cache: ajax.cache === true,
          data: payload,
          success: function (resp) {
            var processed = ajax.processResults
              ? ajax.processResults(resp, { term: query, page: 1 })
              : { results: resp };
            var results = processed.results || [];
            callback(results.map(legacyResultToOpt));
          },
          error: function () {
            callback();
          },
        });
      };
    }

    var trustHtml = identityEscapeMarkup(legacyOpts.escapeMarkup);

    if (legacyOpts.templateResult || legacyOpts.templateSelection) {
      opts.render = {};
      if (legacyOpts.templateResult) {
        opts.render.option = function (data, escape) {
          var d = optionToLegacyData(data, data.value);
          var out = legacyOpts.templateResult(d);
          if (out == null) {
            return wrapTomSelectRenderOutput('');
          }
          if (typeof out === 'string') {
            return wrapTomSelectRenderOutput(stringOutputForTomSelect(out, trustHtml, escape));
          }
          if (trustHtml) {
            return wrapTomSelectRenderOutput(jqToHtml(out));
          }
          var html = jqToHtml(out);
          return wrapTomSelectRenderOutput(html || escape(String(d.text || '')));
        };
      }
      opts.render.item = function (data, escape) {
        var d = optionToLegacyData(data, data.value);
        function escapeItemFallback() {
          var v = (d.name || d.text || d.email || '').toString().trim();
          if (v) return wrapTomSelectRenderOutput(escape(v));
          var key = d.value != null && d.value !== '' ? d.value : d.id;
          if (key != null && key !== '') return wrapTomSelectRenderOutput(escape('#' + String(key)));
          return wrapTomSelectRenderOutput(escape('(recipient)'));
        }
        if (legacyOpts.templateSelection) {
          var sel = legacyOpts.templateSelection(d);
          if (sel == null || String(sel).trim() === '') return escapeItemFallback();
          if (typeof sel === 'string') {
            var rendered = stringOutputForTomSelect(sel, trustHtml, escape);
            if (rendered === '' || rendered == null) return escapeItemFallback();
            return wrapTomSelectRenderOutput(rendered);
          }
          var jqH = jqToHtml(sel);
          if (!jqH) return escapeItemFallback();
          return wrapTomSelectRenderOutput(jqH);
        }
        var plain = String(d.text || d.email || d.value || '').trim();
        return wrapTomSelectRenderOutput(escape(plain || '(recipient)'));
      };
    }

    if (legacyOpts.data && Array.isArray(legacyOpts.data)) {
      opts.options = legacyOpts.data.map(legacyResultToOpt);
      opts.persist = false;
    }

    return opts;
  }

  if (!$.fn.mmSelectPatchedVal) {
    var _val = $.fn.val;
    $.fn.val = function (value) {
      if (arguments.length && this.length && this[0].tomselect) {
        var ts = this[0].tomselect;
        var v = value;
        if (Array.isArray(v)) {
          ts.setValue(v.map(String), true);
        } else if (v === null || v === undefined || v === '') {
          ts.clear(true);
        } else {
          ts.setValue(String(v), true);
        }
        return this;
      }
      return _val.apply(this, arguments);
    };
    $.fn.mmSelectPatchedVal = true;
  }

  $.fn.mmSelect = function (options) {
    if (options === 'destroy') {
      return this.each(function () {
        var el = this;
        if (el.tomselect) {
          el.tomselect.destroy();
        }
        $(el).removeData(DATA_KEY);
        $(el).removeClass(INIT_CLASS);
      });
    }

    if (typeof options === 'string') {
      return this;
    }

    var legacyOpts = options || {};

    return this.each(function () {
      var el = this;
      if (el.tagName !== 'SELECT') return;

      if (el.tomselect) {
        el.tomselect.destroy();
      }

      var opts = buildSettings($(el), legacyOpts);
      var ts = new TomSelect(el, opts);
      $(el).data(DATA_KEY, { tomselect: true });
      $(el).addClass(INIT_CLASS);
      bindBridgeEvents(ts, el);
      applyWidth($(el), legacyOpts.width);
    });
  };
});
