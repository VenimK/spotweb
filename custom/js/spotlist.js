/**
 * Spot list helpers on the index page: an IMDb/TMDb rating badge per
 * movie or series row (via custom/tools/ratings.php), "sort by rating"
 * and "hide below" controls, and optional grouping of duplicate
 * releases of the same title into one row with version chips.
 */
(function () {
    'use strict';

    var ENDPOINT = 'custom/tools/ratings.php';
    var STORE_KEY = 'sw-spotlist-prefs';

    var CSS = ''
        + '.sw-rl{display:inline-block;padding:0 5px;margin-left:6px;border-radius:3px;'
        + 'font-size:10px;font-weight:700;line-height:1.7;vertical-align:1px;'
        + 'background:#f5c518;color:#000;cursor:default}'
        + '.sw-rl-tmdb{background:#01b4e4;color:#fff}'
        + '.sw-rl-na{opacity:.35;font-weight:400}'
        + '.sw-slbar{display:flex;align-items:center;gap:12px;margin:2px 0 8px;font-size:12px;opacity:.95}'
        + '.sw-slbar label{display:inline-flex;align-items:center;gap:4px;cursor:pointer;user-select:none}'
        + '.sw-slbar button{cursor:pointer;padding:2px 10px;border-radius:4px;'
        + 'border:1px solid rgba(148,163,184,.4);background:rgba(148,163,184,.12);color:inherit;font-size:12px}'
        + '.sw-slbar button.on{background:#3b82f6;border-color:#3b82f6;color:#fff}'
        + '.sw-slbar select{font-size:12px}'
        + 'tr.sw-sl-duphide{display:none !important}'
        + 'tr.sw-sl-ratinghide{display:none !important}'
        + '.sw-vchip{display:inline-block;margin-left:5px;padding:0 6px;border-radius:3px;'
        + 'font-size:10px;font-weight:700;line-height:1.7;vertical-align:1px;cursor:pointer;'
        + 'border:1px solid rgba(148,163,184,.5);color:inherit;text-decoration:none;opacity:.85}'
        + '.sw-vchip:hover{opacity:1}';

    var prefs = { sort: false, min: 0, group: false };
    try {
        var saved = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
        prefs = { sort: !!saved.sort, min: +saved.min || 0, group: !!saved.group };
    } catch (e) { /* private mode */ }

    var ratings = new Map();   // rawTitle -> rating|null
    var inFlight = new Set();  // rawTitle being fetched
    var queue = [];            // pending rawTitles
    var rowSeq = 0;

    function savePrefs() {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify(prefs));
        } catch (e) { /* ignore */ }
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

    function injectStyle() {
        if (document.getElementById('sw-sl-style')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'sw-sl-style';
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    function titleLink(row) {
        var cell = row.querySelector('td.title a.spotlink');
        return cell ? { link: cell, title: cell.textContent.trim() } : null;
    }

    /* Ratings: queued, max 3 concurrent so Apache workers stay free */
    function pump() {
        while (inFlight.size < 3 && queue.length) {
            var title = queue.shift();
            if (ratings.has(title) || inFlight.has(title)) {
                continue;
            }
            inFlight.add(title);
            fetch(ENDPOINT + '?title=' + encodeURIComponent(title), { credentials: 'same-origin' })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    inFlight.delete(title);
                    var r = data && (data.imdb_rating || data.tmdb_rating);
                    ratings.set(title, r ? { imdb: data.imdb_rating, tmdb: data.tmdb_rating, name: data.name } : null);
                    paintBadges(title);
                    applyFilters();
                })
                .catch(function () {
                    inFlight.delete(title);
                    ratings.set(title, null);
                    paintBadges(title);
                })
                .finally(pump);
        }
    }

    function paintBadges(title) {
        var r = ratings.get(title);
        document.querySelectorAll('tr[data-swl-title]').forEach(function (row) {
            if (row.dataset.swlTitle !== title) {
                return;
            }
            var host = row.querySelector('td.title .sw-rl-host');
            if (!host || host.dataset.done) {
                return;
            }
            host.dataset.done = '1';
            if (!r) {
                return;
            }
            var score = r.imdb !== null && r.imdb !== undefined ? r.imdb : r.tmdb;
            if (score === null || score === undefined) {
                return;
            }
            var b = el('span', 'sw-rl' + (r.imdb === null || r.imdb === undefined ? ' sw-rl-tmdb' : ''), (+score).toFixed(1));
            b.title = (r.imdb !== null && r.imdb !== undefined ? 'IMDb' : 'TMDb') + ' ' + (+score).toFixed(1)
                + (r.name ? ' — ' + r.name : '');
            host.appendChild(b);
            row.dataset.swlRating = score;
        });
    }

    /* "Movie.Title.2024.1080p" -> "movie title", + category flavour z0/z1 */
    function groupKey(row, title) {
        var m = title.replace(/[._]+/g, ' ')
            .match(/^(.*?)\s+\b(19|20)\d{2}\b|^(.*?)\s+\bS\d{1,2}(E\d{1,3})?\b|^(.*?)\s+\b\d{3,4}p\b/i);
        var base = (m && (m[1] || m[3] || m[5])) || title;
        var z = (row.className.match(/spotcat\d_(z\d)/) || [])[1] || '';
        return base.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim() + '|' + z;
    }

    /* Short quality label for chips: 2160p/1080p/720p/WEB-DL/BluRay... */
    function resLabel(title) {
        var m = title.match(/\b(4320|2160|1440|1080|900|720|576|480)p\b/i);
        if (m) {
            return m[1] + 'p';
        }
        m = title.match(/\b(UHD|4K|8K|BluRay|Blu-?ray|WEB-?DL|WEBRip|HDTV|DVDRip|DVD|CAM|TS)\b/i);
        return m ? m[1].toUpperCase().replace(/-/g, '') : 'alt';
    }

    var rowsCache = [];
    function rows() {
        var body = document.querySelector('tbody#spots');
        if (!body) {
            return [];
        }
        var out = [];
        body.querySelectorAll('tr').forEach(function (row) {
            if (!/spotcat0/.test(row.className)) {
                return;
            }
            if (!row.dataset.swlSeq) {
                row.dataset.swlSeq = rowSeq++;
            }
            out.push(row);
        });
        return out;
    }

    /* Hide non-leading duplicates; chips on the kept row reveal them */
    function regroup() {
        var groups = new Map();
        rows().forEach(function (row) {
            row.classList.remove('sw-sl-duphide');
            var info = titleLink(row);
            if (!info) {
                return;
            }
            var key = groupKey(row, info.title);
            var g = groups.get(key);
            if (!g) {
                groups.set(key, { first: row, rest: [] });
            } else {
                g.rest.push(row);
            }
        });

        if (!prefs.group) {
            document.querySelectorAll('.sw-vchip').forEach(function (c) { c.remove(); });
            return;
        }
        groups.forEach(function (g) {
            if (!g.rest.length) {
                return;
            }
            g.rest.forEach(function (row) {
                row.classList.add('sw-sl-duphide');
            });
            var host = g.first.querySelector('td.title');
            if (!host || host.querySelector('.sw-vchip')) {
                return;
            }
            g.rest.forEach(function (row) {
                var info = titleLink(row);
                var chip = el('a', 'sw-vchip', '+' + resLabel(info ? info.title : ''));
                chip.title = 'Also show: ' + (info ? info.title : 'alternate release');
                chip.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    row.classList.remove('sw-sl-duphide');
                    chip.remove();
                });
                host.appendChild(chip);
            });
        });
    }

    function applyFilters() {
        rows().forEach(function (row) {
            var rating = row.dataset.swlRating !== undefined ? +row.dataset.swlRating : null;
            row.classList.toggle('sw-sl-ratinghide', prefs.min > 0 && rating !== null && rating < prefs.min);
        });
        if (prefs.sort) {
            var body = document.querySelector('tbody#spots');
            if (body) {
                var list = Array.prototype.slice.call(body.children).sort(function (a, b) {
                    var ra = a.dataset.swlRating !== undefined ? +a.dataset.swlRating : -1;
                    var rb = b.dataset.swlRating !== undefined ? +b.dataset.swlRating : -1;
                    if (rb !== ra) {
                        return rb - ra;
                    }
                    return (+a.dataset.swlSeq || 0) - (+b.dataset.swlSeq || 0);
                });
                list.forEach(function (r) { body.appendChild(r); });
            }
        }
    }

    function toolbar() {
        if (document.getElementById('sw-sl-bar')) {
            return;
        }
        var anchor = document.querySelector('table.spots');
        if (!anchor) {
            return;
        }
        var bar = el('div', 'sw-slbar');
        bar.id = 'sw-sl-bar';

        var sortBtn = el('button', prefs.sort ? 'on' : '', 'Sort by rating');
        sortBtn.type = 'button';
        sortBtn.addEventListener('click', function () {
            prefs.sort = !prefs.sort;
            sortBtn.classList.toggle('on', prefs.sort);
            savePrefs();
            if (!prefs.sort) {
                // Restore original order
                var body = document.querySelector('tbody#spots');
                if (body) {
                    Array.prototype.slice.call(body.children)
                        .sort(function (a, b) { return (+a.dataset.swlSeq || 0) - (+b.dataset.swlSeq || 0); })
                        .forEach(function (r) { body.appendChild(r); });
                }
            }
            applyFilters();
        });

        var minSel = document.createElement('select');
        [[0, 'Any rating'], [5, '5+'], [6, '6+'], [7, '7+'], [8, '8+']].forEach(function (opt) {
            var o = document.createElement('option');
            o.value = opt[0];
            o.textContent = opt[1];
            if (+prefs.min === +opt[0]) {
                o.selected = true;
            }
            minSel.appendChild(o);
        });
        minSel.addEventListener('change', function () {
            prefs.min = +minSel.value;
            savePrefs();
            applyFilters();
        });

        var groupLbl = el('label');
        var groupCb = document.createElement('input');
        groupCb.type = 'checkbox';
        groupCb.checked = prefs.group;
        groupCb.addEventListener('change', function () {
            prefs.group = groupCb.checked;
            savePrefs();
            regroup();
        });
        groupLbl.appendChild(groupCb);
        groupLbl.appendChild(document.createTextNode('Group duplicates'));

        bar.appendChild(sortBtn);
        bar.appendChild(el('span', null, 'Hide rated below'));
        bar.appendChild(minSel);
        bar.appendChild(groupLbl);
        anchor.parentNode.insertBefore(bar, anchor);
    }

    function process() {
        rows().forEach(function (row) {
            if (row.dataset.swlSeen) {
                return;
            }
            row.dataset.swlSeen = '1';
            var info = titleLink(row);
            if (!info) {
                return;
            }
            row.dataset.swlTitle = info.title;
            var host = el('span', 'sw-rl-host');
            info.link.parentNode.insertBefore(host, info.link.nextSibling);
            if (ratings.has(info.title)) {
                paintBadges(info.title);
            } else if (!inFlight.has(info.title) && queue.indexOf(info.title) === -1) {
                queue.push(info.title);
                pump();
            }
        });
        regroup();
        applyFilters();
    }

    function init() {
        if (!document.querySelector('tbody#spots')) {
            return;
        }
        injectStyle();
        toolbar();
        process();
        var body = document.querySelector('tbody#spots');
        var timer = null;
        new MutationObserver(function () {
            // Infinite scroll appends rows; debounce so each batch processes once
            clearTimeout(timer);
            timer = setTimeout(process, 250);
        }).observe(body, { childList: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
