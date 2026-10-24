/**
 * IMDb / TMDb rating badges on the spot detail page.
 *
 * Injected by custom/includes/theme-loader.inc.php. Watches for
 * #details (loaded into #overlay when a spot is opened, or rendered
 * directly on ?page=getspot) and fetches ratings from
 * custom/tools/ratings.php, which requires a Spotweb login.
 */
(function () {
    'use strict';

    var ENDPOINT = 'custom/tools/ratings.php';

    var CSS = ''
        + '.sw-ratings{display:inline-block;margin-left:10px;white-space:nowrap;vertical-align:middle}'
        + '.sw-rt{display:inline-block;padding:1px 7px;margin-right:6px;border-radius:4px;'
        + 'font-size:11px;font-weight:700;line-height:1.7;vertical-align:middle;text-decoration:none;cursor:default}'
        + 'a.sw-rt{cursor:pointer}'
        + '.sw-rt-imdb{background:#f5c518;color:#000 !important}'
        + '.sw-rt-imdb:hover{filter:brightness(1.12)}'
        + '.sw-rt-tmdb{background:#01b4e4;color:#fff !important}'
        + '.sw-rt-tmdb:hover{filter:brightness(1.12)}';

    function injectStyle() {
        if (document.getElementById('sw-ratings-style')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'sw-ratings-style';
        style.textContent = CSS;
        document.head.appendChild(style);
    }

    function extract(details) {
        var titleEl = details.querySelector('table.spotheader th.title');
        var title = titleEl ? titleEl.textContent.trim() : '';

        var imdbId = null;
        var imdbLinks = details.querySelectorAll('a[href*="imdb.com/title/tt"]');
        for (var i = 0; i < imdbLinks.length; i++) {
            var m = imdbLinks[i].href.match(/tt\d{6,9}/);
            if (m) {
                imdbId = m[0];
                break;
            }
        }
        if (!imdbId) {
            var pre = details.querySelector('.description pre');
            var text = pre ? pre.textContent : details.textContent;
            var hit = text.match(/\btt\d{7,9}\b/);
            if (hit) {
                imdbId = hit[0];
            }
        }

        var year = null;
        var ym = title.match(/\b(19|20)\d{2}\b/);
        if (ym) {
            year = ym[0];
        }
        return { title: title, imdb: imdbId, year: year };
    }

    function fmt(rating) {
        return (Math.round(rating * 10) / 10).toFixed(1);
    }

    function render(details, data) {
        var host = details.querySelector('table.spotheader th.rating')
            || details.querySelector('table.spotheader th.title');
        if (!host || host.querySelector('.sw-ratings')) {
            return;
        }
        var wrap = document.createElement('span');
        wrap.className = 'sw-ratings';

        if (data.imdb_rating) {
            var imdb = document.createElement('a');
            imdb.className = 'sw-rt sw-rt-imdb';
            imdb.textContent = 'IMDb ' + fmt(data.imdb_rating);
            if (data.imdb_id) {
                imdb.href = 'https://www.imdb.com/title/' + data.imdb_id + '/';
                imdb.target = '_blank';
                imdb.rel = 'noopener noreferrer';
            }
            imdb.title = data.name ? 'IMDb rating for ' + data.name : 'IMDb rating';
            wrap.appendChild(imdb);
        }

        if (data.tmdb_rating) {
            var tmdb;
            if (data.tmdb_url) {
                tmdb = document.createElement('a');
                tmdb.href = data.tmdb_url;
                tmdb.target = '_blank';
                tmdb.rel = 'noopener noreferrer';
            } else {
                tmdb = document.createElement('span');
            }
            tmdb.className = 'sw-rt sw-rt-tmdb';
            tmdb.textContent = 'TMDb ' + fmt(data.tmdb_rating);
            tmdb.title = data.name ? 'TMDb rating for ' + data.name : 'TMDb rating';
            wrap.appendChild(tmdb);
        }

        if (wrap.childNodes.length) {
            host.appendChild(wrap);
        }
    }

    function process(details) {
        if (details.dataset.swRatings) {
            return;
        }
        details.dataset.swRatings = '1';
        injectStyle();

        var info = extract(details);
        if (!info.imdb && !info.title) {
            return;
        }
        var qs = info.imdb
            ? 'imdb=' + encodeURIComponent(info.imdb)
            : 'title=' + encodeURIComponent(info.title)
                + (info.year ? '&year=' + encodeURIComponent(info.year) : '');

        fetch(ENDPOINT + '?' + qs, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && (data.imdb_rating || data.tmdb_rating)) {
                    render(details, data);
                }
            })
            .catch(function () { /* ratings are best-effort */ });
    }

    function scan(root) {
        if (!root || root.nodeType !== 1) {
            return;
        }
        if (root.id === 'details') {
            process(root);
        }
        if (root.querySelectorAll) {
            var found = root.querySelectorAll('#details');
            for (var i = 0; i < found.length; i++) {
                process(found[i]);
            }
        }
    }

    /* This script is injected in <head>, before <body> exists */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan(document.body);
        });
    } else {
        scan(document.body);
    }

    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                for (var i = 0; i < mutation.addedNodes.length; i++) {
                    scan(mutation.addedNodes[i]);
                }
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
