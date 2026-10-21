/* Native checkout navigation stays in charge of success and error responses. */
(function () {
  'use strict';
  var script = document.currentScript;
  var dialog = document.querySelector('[data-order-progress]');
  var form = document.querySelector('[data-order-loading]');
  if (!dialog || !form || typeof dialog.showModal !== 'function') return;
  var art = dialog.querySelector('.order-loading-art');
  var message = dialog.querySelector('[role=status]');
  var initialMessage = message.textContent;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var pending = false, model = null, slowTimer;
  var submitButton = document.querySelector('[data-place-order]');
  var originalDisabled = submitButton && submitButton.disabled;
  var originalText;
  // Load the existing scene during review, rather than waiting for the click.
  var moduleReady = import(script.getAttribute('data-grill-module')).catch(function () { return null; });
  dialog.addEventListener('cancel', function (event) { if (pending) event.preventDefault(); });
  form.addEventListener('submit', function (event) {
    if (event.defaultPrevented) return;
    if (pending) { event.preventDefault(); return; }
    pending = true;
    if (submitButton) {
      originalText = submitButton.textContent;
      submitButton.textContent = 'Placing order…';
      submitButton.disabled = true;
    }
    dialog.showModal();
    form.setAttribute('aria-busy', 'true');
    document.body.classList.add('order-is-pending');
    moduleReady.then(function (module) {
      if (!module || !pending) return;
      try {
        if (!model) {
          model = module.createGrill(art);
          var canvas = art.querySelector('canvas');
          canvas.tabIndex = -1;
          canvas.removeAttribute('role');
          canvas.removeAttribute('aria-label');
          art.classList.add('has-loading-model');
        }
        model.setPlaying(!reducedMotion.matches);
        if (!reducedMotion.matches) model.baste();
      } catch (error) { /* The grill image and CSS smoke remain available. */ }
    });
    slowTimer = window.setTimeout(function () {
      message.textContent = 'Still waiting for confirmation. Please keep this page open and avoid submitting again.';
    }, 12000);
    // No preventDefault or timer-based submission: retain validation, tokens and redirects.
  });
  function reset() {
    pending = false;
    window.clearTimeout(slowTimer);
    if (dialog.open) dialog.close();
    if (model) model.setPlaying(false);
    form.removeAttribute('aria-busy');
    if (submitButton && originalText) { submitButton.textContent = originalText; submitButton.disabled = originalDisabled; }
    document.body.classList.remove('order-is-pending');
    message.textContent = initialMessage;
  }
  window.addEventListener('pageshow', reset);
  reducedMotion.addEventListener('change', function () { if (model) model.setPlaying(pending && !reducedMotion.matches); });
  window.addEventListener('pagehide', function (event) {
    if (model) { model.setPlaying(false); if (!event.persisted) model.dispose(); }
  });
})();
