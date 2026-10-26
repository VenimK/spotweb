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

    /* NZBGeek (Newznab) sub-category names, for the panel title */
    var NZB_NAMES = {
        1010: 'NDS', 1020: 'PSP', 1030: 'Wii', 1040: 'Xbox', 1050: 'Xbox 360', 1080: 'PS3',
        1090: 'Xbox One', 1100: 'PS4', 2030: 'SD', 2040: 'HD', 2045: 'UHD', 2050: 'BluRay',
        2060: '3D', 3010: 'MP3', 3020: 'Video', 3040: 'Lossless', 4030: 'Mac',
        4040: 'Mobile', 4050: 'PC', 4060: 'iOS', 4070: 'Android', 5030: 'SD', 5040: 'HD',
        5045: 'UHD', 5060: 'Sport', 5070: 'Anime', 5080: 'Documentary', 6010: 'DVD',
        6020: 'WMV', 6030: 'XviD', 6040: 'x264', 7010: 'Mags', 7020: 'Ebook', 7030: 'Comics'
    };

    /* Maps each Spotweb sub-filter code in a group to one Newznab id */
    function subs(ids, codes) {
        var out = {};
        Object.keys(codes).forEach(function (nzb) {
            codes[nzb].split(' ').forEach(function (code) {
                out[code] = ids[nzb] || nzb;
            });
        });
        return out;
    }
    /* Spotweb video formats: DivX/WMV/MPG/DVD5/DVD9, HD other/HD-DVD/WMVHD/x264, Blu-ray, UHD, 3D */
    var VIDEO = { sd: 'a0 a1 a2 a3 a10', hd: 'a4 a7 a8 a9', bd: 'a6', uhd: 'a15', '3d': 'a14' };

    /*
     * Spotweb source filters (the b-group) have no Newznab category, but
     * release titles carry the tag. Each code maps to regex alternates
     * matched against the title (dots/underscores are spaces there).
     */
    var SOURCE_KW = {
        b0: 'cam', b1: 'vcd|svcd', b2: 'promo', b3: 'retail', b4: 'hdtv',
        b6: 'satellite', b7: 'r5', b8: 'telecine|tc', b9: 'telesync|ts',
        b10: 'scan', b11: 'web.?dl', b12: 'web.?rip', b13: 'hdrip'
    };
    var SOURCE_NAMES = {
        b0: 'CAM', b1: 'VCD', b2: 'Promo', b3: 'Retail', b4: 'HDTV',
        b6: 'Satellite', b7: 'R5', b8: 'Telecine', b9: 'Telesync',
        b10: 'Scan', b11: 'WEB-DL', b12: 'WEBRip', b13: 'HDRip'
    };

    /* Spotweb category tree -> Newznab categories; "sub" refines by Spotweb sub-filter */
    var CAT_MAP = {
        cat0_z0: { cats: ['2000'], label: 'Movies', sub: subs(
            { sd: '2030', hd: '2040', bd: '2050', uhd: '2045', '3d': '2060' }, VIDEO) },
        cat0_z1: { cats: ['5000'], label: 'Series', sub: subs(
            { sd: '5030', hd: '5040', bd: '5040', uhd: '5045', '3d': '5040', anime: '5070', doc: '5080', sport: '5060' },
            { sd: VIDEO.sd, hd: VIDEO.hd, bd: VIDEO.bd, uhd: VIDEO.uhd, '3d': VIDEO['3d'], anime: 'd2 d29 d32', doc: 'd6', sport: 'd18' }) },
        cat0_z2: { cats: ['7000'], label: 'Books', sub: subs({}, { 7020: 'a5 a11', 7010: 'd43 d44', 7030: 'd31' }) },
        cat0_z3: { cats: ['6000'], label: 'Erotic', sub: subs({}, { 6010: 'a3 a10', 6020: 'a1 a8', 6030: 'a0', 6040: 'a9' }) },
        cat0: { cats: ['2000', '5000'], label: 'Movies & Series', sub: {} },
        cat1: { cats: ['3000'], label: 'Sound', sub: subs({}, { 3010: 'a0', 3040: 'a2 a4 a7 a8', 3020: 'b3' }) },
        cat1_z3: { cats: ['3030'], label: 'Audiobooks', sub: {} },
        cat2: { cats: ['1000', '4050'], label: 'Games', sub: subs({}, {
            4050: 'a0 a1 a2', 1020: 'a5', 1040: 'a6', 1050: 'a7', 1010: 'a10 a16', 1030: 'a11',
            1080: 'a12', 1100: 'a17', 1090: 'a18', 4040: 'a13', 4060: 'a14', 4070: 'a15' }) },
        cat3: { cats: ['4000'], label: 'Applications', sub: subs({}, { 4030: 'a1', 4040: 'a4', 4060: 'a6', 4070: 'a7' }) }
    };

    /*
     * Categories Spotweb is currently showing, from
     * search[tree]=cat0_z0_a4,cat0_z0_a9,... Per Spotweb category the
     * mapped sub-filters are used; when none of them maps, the whole
     * category. Excluded nodes ("!" / "~" prefix) are skipped.
     */
    function currentCategories() {
        var tree = new URLSearchParams(window.location.search).get('search[tree]') || '';
        var groups = {};
        var order = [];
        tree.split(',').forEach(function (node) {
            /* Nodes chain sub-codes: cat0_z0_a9_b11 = Movies > x264 > WEB-DL */
            var m = node.trim().match(/^(cat\d)(?:_(z\d))?((?:_[a-d]\d+)+)?$/);
            if (!m) {
                return;
            }
            var key = CAT_MAP[m[1] + (m[2] ? '_' + m[2] : '')] ? m[1] + (m[2] ? '_' + m[2] : '') : m[1];
            var def = CAT_MAP[key];
            if (!def) {
                return;
            }
            if (!groups[key]) {
                groups[key] = { def: def, cats: [], kw: [], kwnames: [] };
                order.push(key);
            }
            var g = groups[key];
            (m[3] || '').split('_').forEach(function (code) {
                if (!code) {
                    return;
                }
                var nzb = def.sub[code];
                if (nzb && g.cats.indexOf(nzb) === -1) {
                    g.cats.push(nzb);
                }
                /* b-codes are source filters: keyword-match the titles instead */
                if (code[0] === 'b' && SOURCE_KW[code] && g.kw.indexOf(SOURCE_KW[code]) === -1) {
                    g.kw.push(SOURCE_KW[code]);
                    g.kwnames.push(SOURCE_NAMES[code]);
                }
            });
        });

        var cats = [];
        var labels = [];
        var kw = [];
        order.forEach(function (key) {
            var g = groups[key];
            var picked = g.cats.length ? g.cats : g.def.cats;
            picked.forEach(function (c) {
                if (cats.indexOf(c) === -1) {
                    cats.push(c);
                }
            });
            var names = g.cats.map(function (c) { return NZB_NAMES[c]; })
                .filter(function (n, i, all) { return n && all.indexOf(n) === i; });
            var label = g.def.label;
            if (names.length || g.kwnames.length) {
                label += ' (' + names.concat(g.kwnames).join(', ') + ')';
            }
            labels.push(label);
            if (g.kw) {
                kw = kw.concat(g.kw);
            }
        });
        return { cats: cats, label: labels.join(', '), kw: kw };
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

    /* Title matcher for b-group source filters, e.g. "web.?dl" */
    function kwMatcher(kw) {
        if (!kw.length) {
            return null;
        }
        var re = new RegExp('\\b(' + kw.join('|') + ')\\b', 'i');
        return function (title) {
            return re.test(title.replace(/[._]+/g, ' '));
        };
    }

    function render(panel, desc, data, matches) {
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
        var items = matches ? data.items.filter(function (i) { return matches(i.title); }) : data.items;
        if (!data.items || !data.items.length || !items.length) {
            body.appendChild(el('div', 'sw-geek-note', 'No NZBGeek results for ' + desc + '.'));
            panel.querySelector('.sw-geek-count').textContent = '0 results';
            return;
        }

        panel.querySelector('.sw-geek-count').textContent =
            (data.total > items.length
                ? items.length + ' of ' + data.total + ' results'
                : items.length + ' results')
            + (matches ? ' (filtered)' : '');

        items.forEach(function (item) {
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
        if (window.swFeatureOn && !window.swFeatureOn('nzbgeek')) {
            return;
        }
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
        var matches = kwMatcher(category.kw || []);
        fetch(ENDPOINT + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : res.json().catch(function () { return { error: 'NZBGeek lookup failed.' }; }); })
            .then(function (data) { render(panel, desc, data || {}, matches); })
            .catch(function () { render(panel, desc, { error: 'NZBGeek lookup failed.' }); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
