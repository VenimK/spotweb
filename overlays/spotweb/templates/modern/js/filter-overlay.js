document.addEventListener('DOMContentLoaded', function () {
  var panels = Array.prototype.slice.call(
    document.querySelectorAll('.sidebarPanel.advancedSearch, .sidebarPanel.sabnzbdPanel')
  );
  if (!panels.length) {
    return;
  }

  var isVisible = function (panel) {
    if (!panel || panel.style.display === 'none') {
      return false;
    }
    // jQuery fadeIn/fadeOut toggles display; also treat computed hidden as closed
    var style = window.getComputedStyle(panel);
    if (style.display === 'none' || style.visibility === 'hidden') {
      return false;
    }
    return panel.offsetWidth > 0 && panel.offsetHeight > 0;
  };

  var updateState = function () {
    var anyOpen = panels.some(isVisible);
    document.body.classList.toggle('filters-open', anyOpen);
    document.body.classList.toggle('dlpanel-open', panels.some(function (panel) {
      return panel.classList.contains('sabnzbdPanel') && isVisible(panel);
    }));
  };

  var observer = new MutationObserver(function () {
    updateState();
  });

  panels.forEach(function (panel) {
    observer.observe(panel, { attributes: true, attributeFilter: ['style', 'class'] });
  });

  document.addEventListener('click', function () {
    setTimeout(updateState, 25);
    // fade animations can finish a bit later
    setTimeout(updateState, 220);
  });

  updateState();
});
