/**
 * SpotWeb modern Power UX: keyboard navigation, command palette, help overlay.
 * Depends on: jQuery (loaded with core statics), existing retrieveSpots/downloadSabnzbd/toggleWatchSpot.
 */
(function () {
  'use strict';

  var cfg = window.spotwebPowerUx || {};
  var state = {
    index: -1,
    cmdOpen: false,
    helpOpen: false,
    cmdActive: 0,
    cmdItems: [],
    gPending: 0,
    toastTimer: 0,
    pollTimer: 0
  };

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }
  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function isTypingTarget(el) {
    if (!el) return false;
    var tag = (el.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
    if (el.isContentEditable) return true;
    return false;
  }

  function overlayVisible() {
    var ov = qs('#overlay');
    if (!ov) return false;
    if (ov.classList.contains('loading')) return true;
    try {
      var style = window.getComputedStyle(ov);
      if (style.display === 'none' || style.visibility === 'hidden') return false;
    } catch (e) {}
    return ov.offsetParent !== null || ov.clientHeight > 0;
  }

  function hasCards() {
    return !!qs('.cardsGrid .spotCard');
  }

  function getItems() {
    var cards = qsa('.cardsGrid .spotCard');
    if (cards.length) return cards;
    return qsa('table.spots tbody tr').filter(function (tr) {
      return !tr.classList.contains('head') && !tr.classList.contains('header') && tr.querySelector('a.spotlink, td.title a');
    });
  }

  function clearFocus() {
    qsa('.sw-focus, .spotCard.active').forEach(function (el) {
      el.classList.remove('sw-focus');
      if (el.classList.contains('spotCard')) el.classList.remove('active');
    });
  }

  function setFocus(idx, opts) {
    var items = getItems();
    opts = opts || {};
    if (!items.length) {
      state.index = -1;
      clearFocus();
      return null;
    }
    if (idx < 0) idx = 0;
    if (idx >= items.length) idx = items.length - 1;
    state.index = idx;
    clearFocus();
    var el = items[idx];
    el.classList.add('sw-focus');
    if (el.classList.contains('spotCard')) el.classList.add('active');
    if (el.tagName === 'TR') {
      qsa('table.spots tbody tr.active').forEach(function (tr) {
        if (tr !== el) tr.classList.remove('active');
      });
      el.classList.add('active');
    }
    if (!opts.noScroll) {
      try {
        el.scrollIntoView({ block: 'nearest', behavior: opts.instant ? 'auto' : 'smooth' });
      } catch (e) {
        el.scrollIntoView(false);
      }
    }
    return el;
  }

  function currentItem() {
    var items = getItems();
    if (state.index < 0 || state.index >= items.length) return null;
    return items[state.index];
  }

  function itemTitle(el) {
    if (!el) return '';
    var link = el.querySelector('.title a, a.spotlink, td.title a');
    return ((link && link.textContent) || '').replace(/\s+/g, ' ').trim();
  }

  function openCurrent() {
    var el = currentItem();
    if (!el) return;
    var link = el.querySelector('a.spotlink') || el.querySelector('td.title a') || el.querySelector('a[href*="messageid"]');
    if (!link) return;
    if (typeof window.openSpot === 'function') {
      try {
        openSpot(link, link.getAttribute('href'));
        return;
      } catch (e) {}
    }
    link.click();
  }

  function downloadCurrent() {
    var el = currentItem();
    if (!el) return;
    var sab = el.querySelector('a.sabnzbd-button:not(.unconfigured)');
    if (sab) {
      sab.click();
      toast('Queued download', 'ok');
      return;
    }
    var nzb = el.querySelector('a.nzb');
    if (nzb) {
      window.location.href = nzb.getAttribute('href');
    }
  }

  function watchCurrent() {
    var el = currentItem();
    if (!el) return;
    var btn =
      el.querySelector('a.remove, a.removefromwatchlist, a[onclick*="toggleWatchSpot"][onclick*="remove"]') ||
      el.querySelector('a.add, a.addtowatchlist, a[onclick*="toggleWatchSpot"][onclick*="add"]') ||
      el.querySelector('a[onclick*="toggleWatchSpot"]');
    if (btn) btn.click();
  }

  function focusSearch() {
    var input = qs('input.searchbox') || qs('#filterform input[name="search[text]"]');
    if (input) {
      input.focus();
      input.select();
    }
  }

  function toggleView() {
    var next = (localStorage.getItem('spotweb_view') || 'cards') === 'table' ? 'cards' : 'table';
    try {
      localStorage.setItem('spotweb_view', next);
      document.cookie = 'spotweb_view=' + next + '; path=/; max-age=' + 60 * 60 * 24 * 365;
    } catch (e) {}
    var url = new URL(window.location.href);
    url.searchParams.set('view', next);
    window.location.href = url.toString();
  }

  function runRetrieve() {
    toast('Retrieving spots…', 'info');
    if (cfg.retrieveUrl && typeof window.retrieveSpots === 'function') {
      var a = document.createElement('a');
      a.href = cfg.retrieveUrl;
      a.className = 'retrievespots';
      retrieveSpots(a);
      return;
    }
    var existing = qs('ul.maintenancebox a.retrievespots');
    if (existing && typeof window.retrieveSpots === 'function') {
      retrieveSpots(existing);
    }
  }

  function markAllRead() {
    if (typeof window.markAsRead === 'function' && qs('ul.maintenancebox a.markasread')) {
      markAsRead();
    } else if (cfg.markReadUrl) {
      window.location.href = cfg.markReadUrl;
    }
  }

  function gotoPage(which) {
    var sel = which === 'next' ? '.cardsPager a.next, table.spots a.next, .nextpage' : '.cardsPager a.prev, table.spots a.prev, .prevpage';
    var link = qs(sel);
    if (link && link.getAttribute('href')) {
      window.location.href = link.getAttribute('href');
    }
  }

  function linkLabel(a) {
    if (!a) return '';
    var clone = a.cloneNode(true);
    qsa('.newspots, .inclusive, .toggle, .spoticon', clone).forEach(function (n) {
      if (n.parentNode) n.parentNode.removeChild(n);
    });
    return (clone.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function fillFilterHint() {
    var wrap = qs('[data-sw-filter-wrap]');
    var slot = qs('[data-sw-filter]');
    if (!wrap || !slot) return;
    var sel = qs('#filter a.filter.selected');
    var name = linkLabel(sel);
    if (name) {
      slot.textContent = name;
      wrap.hidden = false;
    }
  }

  function fillNewCountFallback() {
    if (qs('.sw-dashboard .sw-dash-label') && Array.prototype.some.call(qsa('.sw-dashboard .sw-dash-label'), function (el) {
      return (el.textContent || '').indexOf('New') !== -1;
    })) return;
    var badge = qs('ul.quicklinks a.filter .newspots');
    var dash = qs('.sw-dashboard .sw-dash-actions');
    if (!badge || !dash) return;
    var n = (badge.textContent || '').replace(/[^\d]/g, '');
    if (!n) return;
    var item = document.createElement('div');
    item.className = 'sw-dash-item';
    item.innerHTML = '<span class="sw-dash-label">New</span><span class="sw-dash-value sw-dash-ok"></span>';
    item.querySelector('.sw-dash-value').textContent = n;
    dash.parentNode.insertBefore(item, dash);
  }

  /* ---------- Toasts ---------- */
  function ensureToast() {
    if (qs('.sw-toast')) return;
    var el = document.createElement('div');
    el.className = 'sw-toast';
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    document.body.appendChild(el);
  }

  function toast(msg, kind) {
    ensureToast();
    var el = qs('.sw-toast');
    el.textContent = msg;
    el.className = 'sw-toast is-open sw-toast-' + (kind || 'info');
    window.clearTimeout(state.toastTimer);
    state.toastTimer = window.setTimeout(function () {
      el.classList.remove('is-open');
    }, 2800);
  }

  /* ---------- Command palette ---------- */
  function ensureCmdUi() {
    if (qs('.sw-cmdk-backdrop')) return;
    var backdrop = document.createElement('div');
    backdrop.className = 'sw-cmdk-backdrop';
    backdrop.innerHTML =
      '<div class="sw-cmdk" role="dialog" aria-modal="true" aria-label="Command palette">' +
      '<input class="sw-cmdk-input" type="text" placeholder="Jump to a filter, spot or command…" autocomplete="off" spellcheck="false" />' +
      '<ul class="sw-cmdk-list"></ul>' +
      '<div class="sw-cmdk-footer"><span><kbd>↑</kbd><kbd>↓</kbd> navigate</span><span><kbd>Enter</kbd> run</span><span><kbd>#</kbd> filters</span><span><kbd>@</kbd> spots</span><span><kbd>Esc</kbd> close</span></div>' +
      '</div>';
    document.body.appendChild(backdrop);

    backdrop.addEventListener('click', function (ev) {
      if (ev.target === backdrop) closeCmd();
    });
    qs('.sw-cmdk-input', backdrop).addEventListener('input', function () {
      renderCmdList(this.value);
    });
  }

  function staticCommands() {
    var list = [
      { id: 'search', title: 'Focus search', hint: '/', group: 'Actions', run: focusSearch },
      { id: 'help', title: 'Keyboard shortcuts', hint: '?', group: 'Actions', run: openHelp },
      { id: 'view', title: 'Toggle cards / table view', hint: 'v', group: 'Actions', run: toggleView },
      { id: 'home', title: 'Go to overview', hint: 'h', group: 'Actions', run: function () { window.location.href = cfg.homeUrl || '/'; } },
      { id: 'watchlist', title: 'Open watchlist', hint: '', group: 'Actions', run: function () { if (cfg.watchlistUrl) window.location.href = cfg.watchlistUrl; } },
      { id: 'prefs', title: 'Change preferences', hint: '', group: 'Actions', run: function () { if (cfg.prefsUrl) window.location.href = cfg.prefsUrl; } },
      { id: 'settings', title: 'Settings', hint: '', group: 'Actions', run: function () { if (cfg.settingsUrl) window.location.href = cfg.settingsUrl; } },
      { id: 'new', title: 'Show new spots only', hint: '', group: 'Actions', run: function () {
        var url = new URL(window.location.href);
        url.searchParams.append('search[value][]', 'New:0');
        window.location.href = url.toString();
      } }
    ];
    if (cfg.canRetrieve && cfg.retrieveUrl) {
      list.unshift({ id: 'retrieve', title: 'Retrieve new spots', hint: 'r', group: 'Actions', run: runRetrieve });
    }
    if (cfg.markReadUrl) {
      list.push({ id: 'markread', title: 'Mark everything as read', hint: '', group: 'Actions', run: markAllRead });
    }
    if (cfg.themeToggle) {
      list.push({
        id: 'theme',
        title: 'Toggle light / dark theme',
        hint: 't',
        group: 'Actions',
        run: function () {
          var btn = qs('[data-theme-toggle], .theme-toggle, #theme-toggle, button.themeToggle');
          if (btn) btn.click();
          else {
            var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', cur);
            try { localStorage.setItem('spotweb_theme', cur); } catch (e) {}
          }
        }
      });
    }
    return list.filter(function (c) {
      if (c.id === 'watchlist' && !cfg.watchlistUrl) return false;
      if (c.id === 'prefs' && !cfg.prefsUrl) return false;
      if (c.id === 'settings' && !cfg.settingsUrl) return false;
      return true;
    });
  }

  function collectFilters() {
    var seen = {};
    return qsa('ul.filterlist a.filter').map(function (a) {
      var title = linkLabel(a);
      var href = a.getAttribute('href') || '';
      if (!title || !href || seen[href]) return null;
      seen[href] = true;
      var isQuick = !!(a.closest && a.closest('ul.quicklinks'));
      return {
        id: 'f:' + href,
        title: title,
        hint: isQuick ? 'Quick link' : 'Filter',
        group: isQuick ? 'Quick links' : 'Filters',
        href: href,
        run: function () { window.location.href = href; }
      };
    }).filter(Boolean);
  }

  function collectSpots(query) {
    if (!query) return [];
    return getItems().map(function (el, i) {
      var title = itemTitle(el);
      if (!title) return null;
      return {
        id: 's:' + i,
        title: title,
        hint: 'Spot',
        group: 'On this page',
        el: el,
        idx: i,
        run: function () {
          setFocus(i);
          openCurrent();
        }
      };
    }).filter(Boolean);
  }

  function matchQuery(item, tokens) {
    if (!tokens.length) return true;
    var hay = (item.title + ' ' + (item.id || '') + ' ' + (item.hint || '') + ' ' + (item.group || '')).toLowerCase();
    for (var i = 0; i < tokens.length; i++) {
      if (hay.indexOf(tokens[i]) === -1) return false;
    }
    return true;
  }

  function commands(rawQuery) {
    var q = (rawQuery || '').trim();
    var mode = 'all';
    if (q.charAt(0) === '>') { mode = 'cmd'; q = q.slice(1).trim(); }
    else if (q.charAt(0) === '#') { mode = 'filter'; q = q.slice(1).trim(); }
    else if (q.charAt(0) === '@') { mode = 'spot'; q = q.slice(1).trim(); }
    var tokens = q.toLowerCase().split(/\s+/).filter(Boolean);

    var out = [];
    if (mode === 'all' || mode === 'cmd') {
      out = out.concat(staticCommands().filter(function (c) { return matchQuery(c, tokens); }));
    }
    if (mode === 'all' || mode === 'filter') {
      var filters = collectFilters().filter(function (c) { return matchQuery(c, tokens); });
      if (mode === 'all' && !tokens.length) filters = filters.slice(0, 8);
      else filters = filters.slice(0, 20);
      out = out.concat(filters);
    }
    if (mode === 'all' || mode === 'spot') {
      if (tokens.length || mode === 'spot') {
        out = out.concat(collectSpots(q || ' ').filter(function (c) { return matchQuery(c, tokens); }).slice(0, 12));
      }
    }
    return out;
  }

  function renderCmdList(query) {
    var listEl = qs('.sw-cmdk-list');
    if (!listEl) return;
    var items = commands(query);
    state.cmdItems = items;
    state.cmdActive = 0;
    if (!items.length) {
      listEl.innerHTML = '<li class="sw-cmdk-empty">No matching commands, filters or spots</li>';
      return;
    }
    var html = '';
    var lastGroup = '';
    items.forEach(function (c, i) {
      if (c.group && c.group !== lastGroup) {
        lastGroup = c.group;
        html += '<li class="sw-cmdk-group">' + escapeHtml(c.group) + '</li>';
      }
      html +=
        '<li class="sw-cmdk-item' +
        (i === 0 ? ' is-active' : '') +
        '" data-idx="' +
        i +
        '"><span>' +
        escapeHtml(c.title) +
        '</span><span class="sw-cmdk-hint">' +
        escapeHtml(c.hint || '') +
        '</span></li>';
    });
    listEl.innerHTML = html;
    qsa('.sw-cmdk-item', listEl).forEach(function (li) {
      li.addEventListener('mouseenter', function () {
        state.cmdActive = parseInt(li.getAttribute('data-idx'), 10) || 0;
        syncCmdActive();
      });
      li.addEventListener('click', function () {
        runCmdIndex(parseInt(li.getAttribute('data-idx'), 10) || 0);
      });
    });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function syncCmdActive() {
    qsa('.sw-cmdk-item').forEach(function (li) {
      var idx = parseInt(li.getAttribute('data-idx'), 10);
      li.classList.toggle('is-active', idx === state.cmdActive);
    });
    var active = qs('.sw-cmdk-item.is-active');
    if (active) {
      try {
        active.scrollIntoView({ block: 'nearest' });
      } catch (e) {}
    }
  }

  function runCmdIndex(i) {
    var item = state.cmdItems[i];
    closeCmd();
    if (item && typeof item.run === 'function') item.run();
  }

  function openCmd() {
    ensureCmdUi();
    var backdrop = qs('.sw-cmdk-backdrop');
    backdrop.classList.add('is-open');
    state.cmdOpen = true;
    renderCmdList('');
    var input = qs('.sw-cmdk-input');
    input.value = '';
    setTimeout(function () {
      input.focus();
    }, 0);
  }

  function closeCmd() {
    var backdrop = qs('.sw-cmdk-backdrop');
    if (backdrop) backdrop.classList.remove('is-open');
    state.cmdOpen = false;
  }

  /* ---------- Help ---------- */
  function ensureHelpUi() {
    if (qs('.sw-help-backdrop')) return;
    var backdrop = document.createElement('div');
    backdrop.className = 'sw-help-backdrop';
    backdrop.innerHTML =
      '<div class="sw-help" role="dialog" aria-modal="true">' +
      '<h3>Keyboard shortcuts</h3>' +
      '<dl>' +
      '<dt><kbd>j</kbd> / <kbd>k</kbd> <kbd>↓</kbd> <kbd>↑</kbd></dt><dd>Next / previous spot</dd>' +
      '<dt><kbd>g</kbd> <kbd>g</kbd> / <kbd>G</kbd></dt><dd>First / last spot</dd>' +
      '<dt><kbd>Enter</kbd> / <kbd>o</kbd></dt><dd>Open selected spot</dd>' +
      '<dt><kbd>s</kbd> / <kbd>d</kbd></dt><dd>Download / send NZB</dd>' +
      '<dt><kbd>w</kbd></dt><dd>Toggle watchlist</dd>' +
      '<dt><kbd>c</kbd> / <kbd>m</kbd></dt><dd>Toggle / send MultiNZB</dd>' +
      '<dt><kbd>[</kbd> / <kbd>]</kbd></dt><dd>Previous / next page</dd>' +
      '<dt><kbd>/</kbd></dt><dd>Focus search</dd>' +
      '<dt><kbd>r</kbd></dt><dd>Retrieve new spots</dd>' +
      '<dt><kbd>v</kbd></dt><dd>Toggle cards / table</dd>' +
      '<dt><kbd>t</kbd></dt><dd>Toggle light / dark</dd>' +
      '<dt><kbd>⌘</kbd>/<kbd>Ctrl</kbd>+<kbd>K</kbd></dt><dd>Command palette</dd>' +
      '<dt><kbd>?</kbd></dt><dd>This help</dd>' +
      '<dt><kbd>Esc</kbd></dt><dd>Close overlay / details</dd>' +
      '</dl>' +
      '<button type="button" class="sw-dash-btn sw-help-close">Close</button>' +
      '</div>';
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', function (ev) {
      if (ev.target === backdrop) closeHelp();
    });
    qs('.sw-help-close', backdrop).addEventListener('click', closeHelp);
  }

  function openHelp() {
    ensureHelpUi();
    qs('.sw-help-backdrop').classList.add('is-open');
    state.helpOpen = true;
  }

  function closeHelp() {
    var backdrop = qs('.sw-help-backdrop');
    if (backdrop) backdrop.classList.remove('is-open');
    state.helpOpen = false;
  }

  function consume(ev) {
    ev.preventDefault();
    if (ev.stopPropagation) ev.stopPropagation();
    if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
  }

  function moveFocus(delta) {
    var next = state.index < 0 ? (delta > 0 ? 0 : getItems().length - 1) : state.index + delta;
    setFocus(next);
    if (overlayVisible() && hasCards()) openCurrent();
  }

  /* ---------- Keys ---------- */
  function onKeyDown(ev) {
    var meta = ev.metaKey || ev.ctrlKey;
    var key = ev.key;

    if (meta && (key === 'k' || key === 'K')) {
      consume(ev);
      if (state.cmdOpen) closeCmd();
      else {
        closeHelp();
        openCmd();
      }
      return;
    }

    if (state.cmdOpen) {
      if (key === 'Escape') {
        consume(ev);
        closeCmd();
        return;
      }
      if (key === 'ArrowDown') {
        consume(ev);
        state.cmdActive = Math.min(state.cmdActive + 1, Math.max(state.cmdItems.length - 1, 0));
        syncCmdActive();
        return;
      }
      if (key === 'ArrowUp') {
        consume(ev);
        state.cmdActive = Math.max(state.cmdActive - 1, 0);
        syncCmdActive();
        return;
      }
      if (key === 'Enter') {
        consume(ev);
        runCmdIndex(state.cmdActive);
        return;
      }
      return;
    }

    if (state.helpOpen) {
      if (key === 'Escape' || key === '?') {
        consume(ev);
        closeHelp();
      }
      return;
    }

    if (isTypingTarget(ev.target)) return;
    if (ev.altKey || meta) return;

    var ov = overlayVisible();

    // Spot details overlay: keep the original table/detail bindings.
    if (ov && !hasCards()) return;

    switch (key) {
      case 'j':
      case 'J':
      case 'ArrowDown':
        consume(ev);
        moveFocus(1);
        break;
      case 'k':
      case 'K':
      case 'ArrowUp':
        consume(ev);
        moveFocus(-1);
        break;
      case 'Home':
        consume(ev);
        setFocus(0);
        break;
      case 'End':
        consume(ev);
        setFocus(getItems().length - 1);
        break;
      case 'g':
        if (state.gPending && Date.now() - state.gPending < 700) {
          consume(ev);
          state.gPending = 0;
          setFocus(0, { instant: true });
        } else {
          state.gPending = Date.now();
        }
        break;
      case 'G':
        consume(ev);
        setFocus(getItems().length - 1, { instant: true });
        break;
      case 'Enter':
      case 'o':
      case 'O':
        if (state.index >= 0) {
          consume(ev);
          openCurrent();
        }
        break;
      case 'd':
      case 'D':
      case 's':
      case 'S':
        if (!ov && state.index >= 0) {
          consume(ev);
          downloadCurrent();
        }
        break;
      case 'w':
      case 'W':
        if (!ov && state.index >= 0) {
          consume(ev);
          watchCurrent();
        }
        break;
      case '/':
        consume(ev);
        focusSearch();
        break;
      case 'r':
      case 'R':
        if (cfg.canRetrieve) {
          consume(ev);
          runRetrieve();
        }
        break;
      case 'v':
      case 'V':
        consume(ev);
        toggleView();
        break;
      case 't':
      case 'T':
        if (!ov) {
          consume(ev);
          var themeCmd = staticCommands().filter(function (c) { return c.id === 'theme'; })[0];
          if (themeCmd) themeCmd.run();
        }
        break;
      case '[':
        consume(ev);
        gotoPage('prev');
        break;
      case ']':
        consume(ev);
        gotoPage('next');
        break;
      case '?':
        consume(ev);
        openHelp();
        break;
      case 'Escape':
        if (qs('#overlay') && overlayVisible()) {
          var closer = qs('a.closeDetails');
          if (closer) closer.click();
        }
        break;
      default:
        break;
    }
  }

  function formatEta(seconds) {
    seconds = parseInt(seconds, 10) || 0;
    if (seconds <= 0) return '';
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    if (h > 0) return h + 'h ' + m + 'm left';
    if (m > 0) return m + 'm left';
    return seconds + 's left';
  }

  function updateDownloadLive(queue) {
    var live = qs('[data-sw-dl-live]');
    if (!live || !queue) return;
    var statusEl = qs('.sw-dl-status', live);
    var speedEl = qs('.sw-dl-speed', live);
    var queueEl = qs('.sw-dl-queue', live);
    var diskEl = qs('.sw-dl-disk', live);
    var etaEl = qs('.sw-dl-eta', live);
    if (statusEl) {
      statusEl.textContent = queue.status || '—';
      statusEl.classList.remove('sw-dash-ok', 'sw-dash-warn', 'sw-dash-fail');
      if (queue.paused) statusEl.classList.add('sw-dash-warn');
      else if (queue.status === 'Active') statusEl.classList.add('sw-dash-ok');
      else if (queue.status === 'Idle') statusEl.classList.add('sw-dash-ok');
    }
    if (speedEl) {
      var bps = queue.bytepersec || 0;
      speedEl.textContent = bps > 0 ? (bps / 1048576).toFixed(2) + ' MB/s' : '';
    }
    if (queueEl) {
      var n = queue.nrofdownloads || 0;
      queueEl.textContent = n ? n + ' in queue' : 'empty';
    }
    if (etaEl) {
      etaEl.textContent = queue.status === 'Active' ? formatEta(queue.secondsremaining) : '';
    }
    if (diskEl && queue.freediskspace != null && queue.freediskspace !== '-') {
      diskEl.textContent = queue.freediskspace + ' GB free';
    }
  }

  function openDownloadPanel() {
    if (typeof window.toggleSidebarPanel === 'function') {
      toggleSidebarPanel('.sabnzbdPanel');
      return;
    }
    var toggle = qs('a[onclick*="sabnzbdPanel"]');
    if (toggle) toggle.click();
  }

  function pollDownloadLive() {
    var live = qs('[data-sw-dl-live]');
    if (!live) return;
    if (typeof window.updateSabPanel === 'function' && typeof window.jQuery !== 'undefined') {
      var panelOpen = jQuery('div.sabnzbdPanel').is(':visible');
      if (!panelOpen) {
        var apikey = jQuery('div.sabnzbdPanel input.apikey').val();
        if (apikey) {
          jQuery
            .getJSON('?page=nzbhandlerapi&nzbhandlerapikey=' + encodeURIComponent(apikey) + '&action=getstatus')
            .done(function (json) {
              if (json && json.queue) updateDownloadLive(json.queue);
            })
            .fail(function () {
              var statusEl = qs('.sw-dl-status', live);
              if (statusEl) {
                statusEl.textContent = 'Offline';
                statusEl.classList.remove('sw-dash-ok', 'sw-dash-warn');
                statusEl.classList.add('sw-dash-fail');
              }
            });
        }
      }
    }
    state.pollTimer = window.setTimeout(pollDownloadLive, 10000);
  }

  /* ---------- Dashboard wire-up ---------- */
  function wireDashboard() {
    var dash = qs('.sw-dashboard');
    if (!dash) return;
    fillFilterHint();
    fillNewCountFallback();
    qsa('[data-sw-action]', dash).forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        var action = btn.getAttribute('data-sw-action');
        if (!action) return;
        ev.preventDefault();
        if (action === 'retrieve') runRetrieve();
        else if (action === 'cmdk') openCmd();
        else if (action === 'help') openHelp();
        else if (action === 'dlpanel') openDownloadPanel();
      });
    });
    window.setTimeout(pollDownloadLive, 1500);
  }

  function init() {
    document.addEventListener('keydown', onKeyDown, true);
    wireDashboard();
    document.addEventListener('click', function (ev) {
      var card = ev.target.closest && ev.target.closest('.spotCard');
      if (card) {
        var cards = getItems();
        var idx = cards.indexOf(card);
        if (idx >= 0) setFocus(idx, { noScroll: true });
        return;
      }
      var row = ev.target.closest && ev.target.closest('table.spots tbody tr');
      if (row) {
        var rows = getItems();
        var ridx = rows.indexOf(row);
        if (ridx >= 0) setFocus(ridx, { noScroll: true });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.spotwebPowerUxApi = {
    openCommandPalette: openCmd,
    openHelp: openHelp,
    focusSearch: focusSearch,
    setFocus: setFocus,
    updateDownloadLive: updateDownloadLive,
    openDownloadPanel: openDownloadPanel,
    toast: toast
  };
})();
