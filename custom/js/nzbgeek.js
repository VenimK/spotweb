/**
 * NZBGeek search results panel on the Spotweb index page.
 *
 * Injected by custom/includes/theme-loader.inc.php. When the current
 * URL carries a real search query (search[value][]=...), fetches
 * matching releases from NZBGeek via custom/tools/nzbgeek.php and
 * shows them above the spots table with NZB download links.
 */
(function () {
    'use strict';

    var ENDPOINT = 'custom/tools/nzbgeek.php';

    var CSS = ''
        + '#sw-geek{margin:6px 0 12px;border:1px solid rgba(148,163,184,.25);border-radius:8px;overflow:hidden;font-size:12px}'
        + '#sw-geek .sw-geek-head{display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;'
        + 'background:rgba(148,163,184,.12);font-weight:700;user-select:none}'
        + '#sw-geek .sw-geek-head .sw-geek-count{font-weight:400;opacity:.75;margin-left:auto}'
        + '#sw-geek .sw-geek-body{max-height:280px;overflow-y:auto}'
        + '#sw-geek.collapsed .sw-geek-body{display:none}'
        + '#sw-geek .sw-geek-row{display:flex;gap:10px;align-items:baseline;padding:5px 10px;border-top:1px solid rgba(148,163,184,.12)}'
        + '#sw-geek .sw-geek-row:nth-child(odd){background:rgba(148,163,184,.05)}'
        + '#sw-geek .sw-geek-title{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
        + '#sw-geek .sw-geek-meta{white-space:nowrap;opacity:.7}'
        + '#sw-geek .sw-geek-dl{flex:none;text-decoration:none;font-weight:700;padding:0 6px;border-radius:4px;'
        + 'background:#3b82f6;color:#fff !important;line-height:1.8}'
        + '#sw-geek .sw-geek-dl:hover{filter:brightness(1.15)}'
        + '#sw-geek .sw-geek-note{padding:6px 10px;opacity:.7}';

    function injectStyle() {
        if (document.getElementById('sw-geek-style')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'sw-geek-style';
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    /* The search query Spotweb is currently showing.
       Values look like "Title:=:DEF:spiderman" (field:=:op:value);
       take the part after the last ':=:'. */
    function currentQuery() {
        var values = new URLSearchParams(window.location.search).getAll('search[value][]');
        var terms = [];
        values.forEach(function (v) {
            if (!v || v === 'NULL') {
                return;
            }
            var idx = v.lastIndexOf(':=:');
            if (idx >= 0) {
                // Field search: "Title:=:DEF:spiderman" -> "spiderman"
                var term = v.slice(idx + 3).trim();
                if (term && term !== 'NULL') {
                    terms.push(term);
                }
            } else if (v.indexOf(':') === -1) {
                // Plain free-text term
                terms.push(v.trim());
            } else {
                // "Field:value" counts as a term only for real text fields;
                // flags like "New:0", "Watch:0", "SpotterID:x" are skipped
                var field = v.slice(0, v.indexOf(':'));
                if (/^(titel|title|poster|tag|cat|genre|search|season|episode|ep|year)$/i.test(field)) {
                    terms.push(v.slice(field.length + 1).trim());
                }
            }
        });
        return terms.join(' ').trim();
    }

    function fmtSize(bytes) {
        if (!bytes) {
            return '';
        }
        var mb = bytes / 1048576;
        return mb >= 1024 ? (mb / 1024).toFixed(1) + ' GB' : Math.round(mb) + ' MB';
    }

    function fmtAge(stamp) {
        if (!stamp) {
            return '';
        }
        var days = Math.floor((Date.now() / 1000 - stamp) / 86400);
        return days < 1 ? 'today' : days + 'd ago';
    }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function render(panel, query, data) {
        var body = panel.querySelector('.sw-geek-body');
        body.innerHTML = '';

        if (data.error) {
            body.appendChild(el('div', 'sw-geek-note', data.error));
            panel.querySelector('.sw-geek-count').textContent = 'unavailable';
            return;
        }
        if (!data.items || !data.items.length) {
            body.appendChild(el('div', 'sw-geek-note', 'No NZBGeek results for “' + query + '”.'));
            panel.querySelector('.sw-geek-count').textContent = '0 results';
            return;
        }

        panel.querySelector('.sw-geek-count').textContent =
            data.total > data.items.length
                ? data.items.length + ' of ' + data.total + ' results'
                : data.items.length + ' results';

        data.items.forEach(function (item) {
            var row = el('div', 'sw-geek-row');
            var dl = el('a', 'sw-geek-dl', 'NZB');
            dl.href = item.nzb;
            dl.title = 'Download NZB from NZBGeek';
            var title = el('span', 'sw-geek-title', item.title);
            title.title = item.title;
            var meta = [];
            if (item.size) {
                meta.push(fmtSize(item.size));
            }
            if (item.date) {
                meta.push(fmtAge(item.date));
            }
            if (item.category) {
                meta.push(item.category);
            }
            if (item.grabs) {
                meta.push(item.grabs + ' grabs');
            }
            row.appendChild(dl);
            row.appendChild(title);
            row.appendChild(el('span', 'sw-geek-meta', meta.join(' · ')));
            body.appendChild(row);
        });
    }

    function run() {
        var query = currentQuery();
        if (!query) {
            return;
        }
        var anchor = document.querySelector('div.spots') || document.querySelector('table.spots');
        if (!anchor || document.getElementById('sw-geek')) {
            return;
        }
        injectStyle();

        var panel = el('div');
        panel.id = 'sw-geek';
        var head = el('div', 'sw-geek-head');
        head.appendChild(el('span', null, 'NZBGeek — “' + query + '”'));
        head.appendChild(el('span', 'sw-geek-count', 'searching…'));
        var body = el('div', 'sw-geek-body');
        panel.appendChild(head);
        panel.appendChild(body);
        head.addEventListener('click', function () {
            panel.classList.toggle('collapsed');
        });

        anchor.parentNode.insertBefore(panel, anchor);

        fetch(ENDPOINT + '?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : res.json().catch(function () { return { error: 'NZBGeek lookup failed.' }; }); })
            .then(function (data) { render(panel, query, data || {}); })
            .catch(function () { render(panel, query, { error: 'NZBGeek lookup failed.' }); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
