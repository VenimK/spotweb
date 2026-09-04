/**
 * Injects Filter Manager into Spotweb overlays the same way custom
 * themes hook the toolbar: one script, no extra installer step.
 *
 * Loaded by custom/includes/theme-loader.inc.php when the custom/
 * folder is present. Skips injection when the template already
 * rendered a Filter Manager control.
 */
(function () {
  'use strict';

  function managerUrl() {
    var base = document.querySelector('base');
    var root = (base && base.getAttribute('href')) || (window.location.origin + '/');
    try {
      return new URL('custom/tools/filter-manager.php', root).href;
    } catch (e) {
      return '/custom/tools/filter-manager.php';
    }
  }

  function makeLink(className, label) {
    var a = document.createElement('a');
    a.href = managerUrl();
    a.className = className;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = label;
    a.title = 'Open the filter manager';
    return a;
  }

  function publishUrl() {
    var url = managerUrl();
    window.spotwebPowerUx = window.spotwebPowerUx || {};
    window.spotwebPowerUx.filterManagerUrl = url;
  }

  function injectAdvancedSearch() {
    var adv = document.querySelector('.sidebarPanel.advancedSearch');
    if (!adv || adv.querySelector('a.filterManager')) {
      return;
    }
    var link = makeLink('greyButton filterManager', 'Filter manager');
    var addBtn = adv.querySelector('a.addFilter');
    if (addBtn && addBtn.parentNode) {
      addBtn.parentNode.insertBefore(link, addBtn.nextSibling);
      return;
    }
    var heading = null;
    Array.prototype.forEach.call(adv.querySelectorAll('h4'), function (h4) {
      if (/filters/i.test((h4.textContent || '').replace(/\s+/g, ' '))) {
        heading = h4;
      }
    });
    if (heading) {
      heading.insertAdjacentElement('afterend', link);
    } else {
      adv.appendChild(link);
    }
  }

  function injectToolbar() {
    var toolbar = document.querySelector('#toolbar');
    if (!toolbar || toolbar.querySelector('.toolbarButton.filterManagerBtn')) {
      return;
    }
    var wrap = document.createElement('div');
    wrap.className = 'toolbarButton filterManagerBtn';
    var p = document.createElement('p');
    p.appendChild(makeLink('', 'Filters'));
    wrap.appendChild(p);
    var after = toolbar.querySelector('.toolbarButton.dlpanel') || toolbar.querySelector('.toolbarButton.addspot');
    if (after && after.parentNode) {
      if (after.nextSibling) {
        after.parentNode.insertBefore(wrap, after.nextSibling);
      } else {
        after.parentNode.appendChild(wrap);
      }
      return;
    }
    var scroll = toolbar.querySelector('span.scroll');
    if (scroll) {
      toolbar.insertBefore(wrap, scroll);
    } else {
      toolbar.appendChild(wrap);
    }
  }

  function injectDashboard() {
    var dash = document.querySelector('.sw-dashboard .sw-dash-actions');
    if (!dash || dash.querySelector('a[href*="filter-manager.php"]')) {
      return;
    }
    dash.insertBefore(makeLink('sw-dash-btn', 'Filters'), dash.firstChild);
  }

  function injectPrefs() {
    var fs = document.querySelector('fieldset.manageFilters');
    if (!fs || fs.querySelector('a.filterManager')) {
      return;
    }
    fs.appendChild(makeLink('greyButton filterManager', 'Filter manager'));
  }

  function injectEditDialog() {
    Array.prototype.forEach.call(document.querySelectorAll('form.editfilterform'), function (form) {
      if (form.querySelector('a.filterManager')) {
        return;
      }
      var dd = form.querySelector('dd:last-of-type') || form;
      dd.appendChild(makeLink('smallGreyButton filterManager', 'Filter manager'));
    });
  }

  function injectAll() {
    publishUrl();
    injectAdvancedSearch();
    injectToolbar();
    injectDashboard();
    injectPrefs();
    injectEditDialog();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectAll);
  } else {
    injectAll();
  }

  if (typeof MutationObserver === 'function') {
    var obs = new MutationObserver(function () {
      injectEditDialog();
      injectAdvancedSearch();
    });
    document.addEventListener('DOMContentLoaded', function () {
      obs.observe(document.body, { childList: true, subtree: true });
    });
  }
})();
