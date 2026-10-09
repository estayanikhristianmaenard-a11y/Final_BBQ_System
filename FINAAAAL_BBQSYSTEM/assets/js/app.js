/* BulkBites BBQ — shared UI behaviors.
   1) data-confirm forms: ask before destructive actions (Reset Order, etc.)
   2) data-autosubmit inputs: submit their form on change (cart quantity field)
   3) Phase 9: light "submitting" feedback — adds .is-loading to the form's
      buttons so the user sees a spinner. A CSS class only: buttons are NEVER
      disabled here, because disabled buttons are dropped from the submitted
      form data (cart +/- / remove / reset post their button name/value). */

document.addEventListener('submit', function (event) {
  var form = event.target;
  var message = form.getAttribute('data-confirm');
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
    if (form) form.submit();
  }
});
