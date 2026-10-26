/**
 * Feature toggles for the custom Spotweb extras.
 *
 * A small gear button in the bottom-right corner opens a panel where
 * each feature can be switched on or off. The choice is stored in
 * localStorage under "sw-features" and read by ratings.js, nzbgeek.js
 * and spotlist.js. Everything defaults to on; toggling reloads the page.
 */
(function () {
    'use strict';

    var KEY = 'sw-features';
    var FEATURES = [
        ['ratings', 'IMDb/TMDb rating badges'],
        ['info', 'Spot details & trailer'],
        ['spotlist', 'List ratings & tools'],
        ['nzbgeek', 'NZBGeek results panel']
    ];

    var CSS = ''
        + '#sw-feat-btn{position:fixed;right:14px;bottom:14px;z-index:99998;width:34px;height:34px;'
        + 'border-radius:50%;border:1px solid rgba(148,163,184,.4);background:rgba(30,41,59,.9);'
        + 'color:#cbd5e1;font-size:17px;line-height:1;cursor:pointer;opacity:.7;padding:0}'
        + '#sw-feat-btn:hover{opacity:1}'
        + '#sw-feat-panel{position:fixed;right:14px;bottom:54px;z-index:99999;min-width:220px;padding:10px 14px;'
        + 'border-radius:8px;border:1px solid rgba(148,163,184,.4);background:rgba(15,23,42,.96);'
        + 'color:#e2e8f0;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.4)}'
        + '#sw-feat-panel .sw-feat-title{font-weight:700;margin-bottom:8px}'
        + '#sw-feat-panel label{display:flex;gap:8px;align-items:center;padding:3px 0;cursor:pointer}'
        + '#sw-feat-panel .sw-feat-note{margin-top:8px;font-size:11px;opacity:.6}';

    function load() {
        try {
            return JSON.parse(localStorage.getItem(KEY) || '{}');
        } catch (e) {
            return {};
        }
    }

    /* ratings.js / nzbgeek.js / spotlist.js consult this */
    window.swFeatureOn = function (name) {
        var prefs = load();
        return prefs[name] !== false;
    };

    function init() {
        if (document.getElementById('sw-feat-btn') || document.body === null) {
            return;
        }
        var style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);

        var prefs = load();
        var panel = document.createElement('div');
        panel.id = 'sw-feat-panel';
        panel.hidden = true;
        var head = document.createElement('div');
        head.className = 'sw-feat-title';
        head.textContent = 'Spotweb extras';
        panel.appendChild(head);
        FEATURES.forEach(function (f) {
            var label = document.createElement('label');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = prefs[f[0]] !== false;
            cb.addEventListener('change', function () {
                var p = load();
                p[f[0]] = cb.checked;
                try {
                    localStorage.setItem(KEY, JSON.stringify(p));
                } catch (e) { /* private mode */ }
                setTimeout(function () { location.reload(); }, 150);
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(f[1]));
            panel.appendChild(label);
        });
        var note = document.createElement('div');
        note.className = 'sw-feat-note';
        note.textContent = 'Stored in this browser only; the page reloads to apply.';
        panel.appendChild(note);

        var btn = document.createElement('button');
        btn.id = 'sw-feat-btn';
        btn.type = 'button';
        btn.title = 'Spotweb extras';
        btn.textContent = '⚙';
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            panel.hidden = !panel.hidden;
        });
        document.addEventListener('click', function (ev) {
            if (!panel.hidden && !panel.contains(ev.target)) {
                panel.hidden = true;
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !panel.hidden) {
                panel.hidden = true;
            }
        });

        document.body.appendChild(btn);
        document.body.appendChild(panel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
