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

    function feat(name) {
        return !window.swFeatureOn || window.swFeatureOn(name);
    }

    var CSS = ''
        + '.sw-ratings{display:inline-block;margin-left:10px;white-space:nowrap;vertical-align:middle}'
        + '.sw-rt{display:inline-block;padding:1px 7px;margin-right:6px;border-radius:4px;'
        + 'font-size:11px;font-weight:700;line-height:1.7;vertical-align:middle;text-decoration:none;cursor:default}'
        + 'a.sw-rt{cursor:pointer}'
        + '.sw-rt-imdb{background:#f5c518;color:#000 !important}'
        + '.sw-rt-imdb:hover{filter:brightness(1.12)}'
        + '.sw-rt-tmdb{background:#01b4e4;color:#fff !important}'
        + '.sw-rt-tmdb:hover{filter:brightness(1.12)}'
        + '.sw-rt-trailer{background:#dc2626;color:#fff !important}'
        + '.sw-rt-trailer:hover{filter:brightness(1.12)}'
        + '.sw-info{display:flex;gap:14px;margin:10px 0;padding:12px;border:1px solid rgba(148,163,184,.25);'
        + 'border-radius:8px;background:rgba(148,163,184,.07);line-height:1.45}'
        + '.sw-info-poster{width:92px;height:auto;flex:none;border-radius:4px;align-self:flex-start}'
        + '.sw-info-text{flex:1;min-width:0}'
        + '.sw-info-title{font-weight:700;margin-bottom:3px}'
        + '.sw-info-tagline{font-style:italic;opacity:.75;margin-bottom:4px}'
        + '.sw-info-plot{margin:4px 0 6px}'
        + '.sw-info-cast{opacity:.85}'
        + '.sw-trailer{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.82);display:flex;'
        + 'align-items:center;justify-content:center}'
        + '.sw-trailer-frame{position:relative;width:min(960px,92vw);aspect-ratio:16/9}'
        + '.sw-trailer-frame iframe{width:100%;height:100%;border:0;border-radius:6px;background:#000}'
        + '.sw-trailer-close{position:absolute;top:-38px;right:0;background:none;border:0;color:#fff;'
        + 'font-size:32px;line-height:1;cursor:pointer}';

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

    /* Finds or lazily creates the badge wrap in the spot header */
    function badgeWrap(details) {
        var host = details.querySelector('table.spotheader th.rating')
            || details.querySelector('table.spotheader th.title');
        if (!host) {
            return null;
        }
        var wrap = host.querySelector('.sw-ratings');
        if (!wrap) {
            wrap = document.createElement('span');
            wrap.className = 'sw-ratings';
            host.appendChild(wrap);
        }
        return wrap;
    }

    function render(details, data) {
        var info = data.details;

        if (feat('ratings')) {
            var wrap = null;
            if (data.imdb_rating) {
                wrap = wrap || badgeWrap(details);
                if (wrap) {
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
            }

            if (data.tmdb_rating) {
                wrap = wrap || badgeWrap(details);
                if (wrap) {
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
            }
        }

        if (!feat('info')) {
            return;
        }
        if (info && info.trailer) {
            var wrap = badgeWrap(details);
            if (wrap) {
                var play = document.createElement('a');
                play.className = 'sw-rt sw-rt-trailer';
                play.href = 'https://www.youtube.com/watch?v=' + info.trailer;
                play.textContent = '\u25B6 Trailer';
                play.title = 'Watch the trailer';
                play.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    openTrailer(info.trailer);
                });
                wrap.appendChild(play);
            }
        }
        if (info) {
            renderInfo(details, info);
        }
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

    /* Plot, year, runtime, genres and cast between the spot header and its details */
    function renderInfo(details, info) {
        var anchor = details.querySelector('table.spotdetails');
        if (!anchor || details.querySelector('.sw-info') || !(info.overview || info.cast.length)) {
            return;
        }
        var box = el('div', 'sw-info');
        if (info.poster) {
            var img = el('img', 'sw-info-poster');
            img.src = info.poster;
            img.alt = info.title;
            img.loading = 'lazy';
            box.appendChild(img);
        }
        var text = el('div', 'sw-info-text');
        var facts = [];
        if (info.year) {
            facts.push(info.year);
        }
        if (info.runtime) {
            facts.push(Math.floor(info.runtime / 60) ? Math.floor(info.runtime / 60) + 'h ' + (info.runtime % 60) + 'm' : info.runtime + 'm');
        }
        if (info.seasons) {
            facts.push(info.seasons + (info.seasons === 1 ? ' season' : ' seasons'));
        }
        if (info.genres.length) {
            facts.push(info.genres.join(', '));
        }
        text.appendChild(el('div', 'sw-info-title', info.title + (facts.length ? '  ·  ' + facts.join('  ·  ') : '')));
        if (info.tagline) {
            text.appendChild(el('div', 'sw-info-tagline', info.tagline));
        }
        if (info.overview) {
            text.appendChild(el('p', 'sw-info-plot', info.overview));
        }
        if (info.cast.length) {
            var cast = el('div', 'sw-info-cast');
            cast.appendChild(el('strong', null, 'Cast: '));
            cast.appendChild(document.createTextNode(info.cast.join(', ')));
            text.appendChild(cast);
        }
        box.appendChild(text);
        anchor.parentNode.insertBefore(box, anchor);
    }

    /* In-page YouTube player; closes on the backdrop, the X or Escape */
    function openTrailer(key) {
        var backdrop = el('div', 'sw-trailer');
        var frame = el('div', 'sw-trailer-frame');
        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(key) + '?autoplay=1&rel=0';
        iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        iframe.allowFullscreen = true;
        var close = el('button', 'sw-trailer-close', '×');
        close.type = 'button';
        close.title = 'Close (Esc)';
        frame.appendChild(iframe);
        frame.appendChild(close);
        backdrop.appendChild(frame);

        function shut() {
            document.removeEventListener('keydown', onKey, true);
            backdrop.remove();
        }
        function onKey(ev) {
            if (ev.key === 'Escape') {
                // Keep Spotweb's own Escape (close spot) from firing as well
                ev.stopPropagation();
                ev.preventDefault();
                shut();
            }
        }
        backdrop.addEventListener('click', function (ev) {
            if (ev.target === backdrop) {
                shut();
            }
        });
        close.addEventListener('click', shut);
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(backdrop);
    }

    function process(details) {
        if (!feat('ratings') && !feat('info')) {
            return;
        }
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

        fetch(ENDPOINT + '?details=1&' + qs, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (data && (data.imdb_rating || data.tmdb_rating || data.details)) {
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
