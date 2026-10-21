/* BulkBites BBQ — shared UI behaviors.
   1) data-confirm forms: ask before destructive actions (Reset Order, etc.)
   2) data-autosubmit inputs: submit their form on change (cart quantity field)
   3) Phase 9: light "submitting" feedback — adds .is-loading to the form's
      buttons so the user sees a spinner. A CSS class only: buttons are NEVER
      disabled here, because disabled buttons are dropped from the submitted
      form data (cart +/- / remove / reset post their button name/value). */

// Async controllers capture FormData before applying this visual state.
window.BulkBitesPending = function (button, label) {
  if (!button) return function () {};
  var text = button.textContent, busy = button.getAttribute('aria-busy');
  button.textContent = label;
  button.setAttribute('aria-busy', 'true');
  return function () {
    button.textContent = text;
    if (busy === null) button.removeAttribute('aria-busy');
    else button.setAttribute('aria-busy', busy);
  };
};
var clearDraft = document.currentScript && document.currentScript.dataset.clearCheckoutDraft;
if (clearDraft) { try { sessionStorage.removeItem(clearDraft); } catch (ignored) {} }

document.addEventListener('submit', function (event) {
  var form = event.target;
  var message = form.getAttribute('data-confirm') ||
    (event.submitter && event.submitter.getAttribute('data-confirm'));
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

document.addEventListener('submit', function (event) {
  // Runs after the confirm listener above: skip cancelled submits.
  if (event.defaultPrevented) return;
  var form = event.target;
  if (!form || !form.tagName || form.tagName !== 'FORM') return;
  var buttons = form.querySelectorAll('button[type="submit"], button:not([type])');
  for (var i = 0; i < buttons.length; i++) {
    buttons[i].classList.add('is-loading');
  }
});

/* Back/forward cache restores the page with buttons still marked. */
window.addEventListener('pageshow', function () {
  var loading = document.querySelectorAll('.btn.is-loading');
  for (var i = 0; i < loading.length; i++) {
    loading[i].classList.remove('is-loading');
  }
});

document.addEventListener('change', function (event) {
  var input = event.target;
  if (input.matches('[data-autosubmit]')) {
    var form = input.form;
    if (form) form.requestSubmit();
  }
});

// Brief feedback on successful changes; never delay an action for animation.
(function () {
  var running = new WeakMap();
  function reducedMotion() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  window.BulkBitesHighlight = function (nodes) {
    if (reducedMotion()) return;
    Array.from(nodes).forEach(function (node) {
      if (!node || !node.animate) return;
      var previous = running.get(node);
      if (previous) previous.cancel();
      running.set(node, node.animate([
        {backgroundColor:'rgba(244,172,61,.22)'},
        {backgroundColor:window.getComputedStyle(node).backgroundColor}
      ], {duration:180, easing:'ease-out'}));
    });
  };
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href="#menu"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    var menu = document.getElementById('menu');
    if (!menu) return;
    event.preventDefault();
    history.replaceState(history.state, '', '#menu');
    menu.scrollIntoView({behavior:reducedMotion() ? 'auto' : 'smooth', block:'start'});
  });
})();
