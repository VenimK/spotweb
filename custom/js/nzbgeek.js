/**
 * NZBGeek search results panel on the Spotweb index page.
 *
 * Injected by custom/includes/theme-loader.inc.php. When the current
 * URL carries a real search query (search[value][]=...) and/or a
 * category (search[tree]=cat0_z0...), fetches matching releases, or the
 * newest ones in that category, from NZBGeek via
 * custom/tools/nzbgeek.php and shows them above the spots table.
 */
(function () {
    'use strict';

    var ENDPOINT = 'custom/tools/nzbgeek.php';
    var SEND_ENDPOINT = 'custom/tools/nzbgeek-send.php';

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
        + '#sw-geek .sw-geek-send{background:#16a34a}'
        + '#sw-geek .sw-geek-send.busy{opacity:.6;cursor:progress}'
        + '#sw-geek .sw-geek-send.done{background:#15803d;cursor:default}'
        + '#sw-geek .sw-geek-send.fail{background:#dc2626}'
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
                var term = v.slice(idx + 3).replace(/^[A-Z]+:/, '').trim();
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

    /* Spotweb category tree -> Newznab categories (NZBGeek) */
    var CAT_MAP = {
        cat0_z0: { cats: ['2000'], label: 'Movies' },
        cat0_z1: { cats: ['5000'], label: 'Series' },
        cat0_z2: { cats: ['7000'], label: 'Books' },
        cat0_z3: { cats: ['6000'], label: 'Erotic' },
        cat0: { cats: ['2000', '5000'], label: 'Movies & Series' },
        cat1: { cats: ['3000'], label: 'Sound' },
        cat2: { cats: ['1000', '4050'], label: 'Games' },
        cat3: { cats: ['4000'], label: 'Applications' }
    };

    /* Categories Spotweb is currently showing, from search[tree]=cat0_z0_a4,... */
    function currentCategories() {
        var tree = new URLSearchParams(window.location.search).get('search[tree]') || '';
        var cats = [];
        var labels = [];
        tree.split(',').forEach(function (node) {
            // Excluded nodes are prefixed with "!" or "~"
            var m = node.trim().match(/^(cat\d)(?:_(z\d))?/);
            if (!m) {
                return;
            }
            var hit = CAT_MAP[m[1] + (m[2] ? '_' + m[2] : '')] || CAT_MAP[m[1]];
            if (!hit) {
                return;
            }
            hit.cats.forEach(function (c) {
                if (cats.indexOf(c) === -1) {
                    cats.push(c);
                }
            });
            if (labels.indexOf(hit.label) === -1) {
                labels.push(hit.label);
            }
        });
        return { cats: cats, label: labels.join(', ') };
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

    /* Sends the release to the user's NZB handler (SABnzbd, NZBGet, ...) */
    function sendButton(handler, item) {
        var btn = el('a', 'sw-geek-dl sw-geek-send', handler);
        btn.href = '#';
        btn.title = 'Send to ' + handler;
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            if (btn.classList.contains('busy') || btn.classList.contains('done')) {
                return;
            }
            btn.classList.add('busy');
            btn.textContent = '…';
            var form = new URLSearchParams();
            form.set('id', item.id);
            form.set('title', item.title);
            form.set('cat', String(item.catid || 0));
            fetch(SEND_ENDPOINT, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'spotweb' },
                body: form
            })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    btn.classList.remove('busy');
                    if (res && res.ok) {
                        btn.classList.add('done');
                        btn.textContent = '✓ ' + handler;
                        btn.title = 'Sent to ' + handler;
                    } else {
                        btn.classList.add('fail');
                        btn.textContent = handler;
                        btn.title = (res && res.error) || 'Sending failed';
                        window.alert(btn.title);
                    }
                })
                .catch(function () {
                    btn.classList.remove('busy');
                    btn.classList.add('fail');
                    btn.textContent = handler;
                    window.alert('Sending to ' + handler + ' failed.');
                });
        });
        return btn;
    }

    function render(panel, desc, data) {
        var body = panel.querySelector('.sw-geek-body');
        body.innerHTML = '';

        if (data.error) {
            var note = el('div', 'sw-geek-note', data.error);
            if (data.prefs_url) {
                note.appendChild(document.createTextNode(' '));
                var link = el('a', null, 'Open preferences');
                link.href = data.prefs_url;
                note.appendChild(link);
            }
            body.appendChild(note);
            panel.querySelector('.sw-geek-count').textContent = 'unavailable';
            return;
        }
        if (!data.items || !data.items.length) {
            body.appendChild(el('div', 'sw-geek-note', 'No NZBGeek results for ' + desc + '.'));
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
            if (data.handler && item.id) {
                row.appendChild(sendButton(data.handler, item));
            }
            row.appendChild(title);
            row.appendChild(el('span', 'sw-geek-meta', meta.join(' · ')));
            body.appendChild(row);
        });
    }

    function run() {
        var query = currentQuery();
        var category = currentCategories();
        if (!query && !category.cats.length) {
            return;
        }
        var desc = query
            ? '“' + query + '”' + (category.label ? ' in ' + category.label : '')
            : 'newest in ' + category.label;
        var anchor = document.querySelector('div.spots') || document.querySelector('table.spots');
        if (!anchor || document.getElementById('sw-geek')) {
            return;
        }
        injectStyle();

        var panel = el('div');
        panel.id = 'sw-geek';
        var head = el('div', 'sw-geek-head');
        head.appendChild(el('span', null, 'NZBGeek — ' + desc));
        head.appendChild(el('span', 'sw-geek-count', 'loading…'));
        var body = el('div', 'sw-geek-body');
        panel.appendChild(head);
        panel.appendChild(body);
        head.addEventListener('click', function () {
            panel.classList.toggle('collapsed');
        });

        /* Inside div.spots so it inherits the margin that clears the filter sidebar */
        if (anchor.tagName === 'DIV') {
            anchor.insertBefore(panel, anchor.firstChild);
        } else {
            anchor.parentNode.insertBefore(panel, anchor);
        }

        var params = new URLSearchParams();
        if (query) {
            params.set('q', query);
        }
        if (category.cats.length) {
            params.set('cat', category.cats.join(','));
        }
        fetch(ENDPOINT + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : res.json().catch(function () { return { error: 'NZBGeek lookup failed.' }; }); })
            .then(function (data) { render(panel, desc, data || {}); })
            .catch(function () { render(panel, desc, { error: 'NZBGeek lookup failed.' }); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
