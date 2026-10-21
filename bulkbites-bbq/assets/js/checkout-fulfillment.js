(function () {
  'use strict';
  var form = document.getElementById('checkout-form');
  var button = document.querySelector('[data-place-order]');
  if (!form || !button) return;
  var address = form.querySelector('[name="delivery_address"]');
  var contact = form.querySelector('[name="pickup_contact"]');
  var key = form.dataset.draftKey;
  var fields = ['fulfillment_type', 'payment_method', 'pickup_name', 'pickup_contact', 'delivery_address', 'notes'];
  var feedback = document.querySelector('[data-checkout-feedback]');
  function tell(message) { if (feedback) { feedback.textContent = message; feedback.hidden = false; } }
  function saveDraft() {
    var values = {}, data = new FormData(form);
    fields.forEach(function (name) { values[name] = data.get(name); });
    try { sessionStorage.setItem(key, JSON.stringify({savedAt:Date.now(), values:values})); } catch (ignored) {}
  }
  // Only this tab/customer's short-lived contact draft is retained. Never save tokens.
  try {
    var draft = JSON.parse(sessionStorage.getItem(key));
    if (draft && Date.now() - draft.savedAt < 30 * 60 * 1000 && draft.values) {
      fields.forEach(function (name) {
        if (typeof draft.values[name] !== 'string') return;
        var controls = form.querySelectorAll('[name="' + name + '"]');
        controls.forEach(function (control) {
          if (control.type === 'radio') control.checked = !control.disabled && control.value === draft.values[name];
          else control.value = draft.values[name].slice(0, control.maxLength > 0 ? control.maxLength : 500);
        });
      });
      // A previously offered payment option may no longer be enabled.
      if (!form.querySelector('[name="fulfillment_type"]:checked')) form.querySelector('[name="fulfillment_type"][value="PICKUP"]').checked = true;
      if (!form.querySelector('[name="payment_method"]:checked')) form.querySelector('[name="payment_method"][value="CASH"]').checked = true;
      tell('Your unfinished checkout details were restored. Review them before ordering.');
    } else if (draft) sessionStorage.removeItem(key);
  } catch (ignored) { /* Storage unavailable: server validation still preserves posted values. */ }
  form.addEventListener('input', saveDraft);
  form.addEventListener('change', saveDraft);
  form.addEventListener('submit', function (event) {
    saveDraft();
    if (!navigator.onLine) {
      event.preventDefault();
      tell('You are offline. Your details are kept in this tab. Reconnect before placing your order.');
    }
  });
  function money(cents) { return new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(cents / 100); }
  function update() {
    var delivery = form.querySelector('[name="fulfillment_type"]:checked').value === 'DELIVERY';
    var fee = delivery ? Number(button.dataset.feeCentavos) : 0;
    var total = Number(button.dataset.subtotalCentavos) + fee;
    address.closest('.form-group').hidden = !delivery;
    address.required = delivery;
    address.minLength = delivery ? 10 : 0;
    contact.required = delivery;
    var optional = form.querySelector('[data-checkout-optional]');
    if (delivery) optional.open = true;
    optional.querySelector('summary').textContent = delivery ? 'Delivery contact and notes' : 'Contact and notes (optional)';
    document.querySelector('[data-pickup-guidance]').hidden = delivery;
    document.querySelector('[data-delivery-guidance]').hidden = !delivery;
    document.querySelector('[data-delivery-fee]').textContent = money(fee);
    document.querySelector('[data-checkout-total]').textContent = money(total);
    document.querySelector('[data-cash-help]').textContent = delivery ? 'Cash on delivery' : 'Pay at pickup';
    if (!button.disabled) button.textContent = 'Place order — ' + money(total);
  }
  form.querySelector('[data-checkout-optional]').addEventListener('toggle', function (event) {
    if (contact.required && !event.target.open) event.target.open = true;
  });
  form.querySelectorAll('[name="fulfillment_type"]').forEach(function (input) { input.addEventListener('change', update); });
  update();
})();
