/* Progressive enhancement: native forms still work without JavaScript. */
(function () {
  'use strict';
  if (!window.fetch || !window.FormData) return;
  var popup = document.querySelector('[data-cart-feedback]');
  if (!popup) return;
  var pending = false, popupTimer;
  function feedback(message, ok) {
    clearTimeout(popupTimer);
    popup.hidden = false;
    popup.dataset.result = ok ? 'success' : 'error';
    popup.querySelector('[data-cart-message]').textContent = message;
    popupTimer = setTimeout(function () { if (!popup.contains(document.activeElement)) popup.hidden = true; }, 7000);
  }
  popup.querySelector('[data-cart-dismiss]').addEventListener('click', function () { popup.hidden = true; });
  function clampQuantity(input) {
    var min = Number(input.min) || 1, max = Number(input.max) || 999999;
    var number = Number(input.value);
    if (!Number.isFinite(number)) number = min;
    return Math.min(max, Math.max(min, Math.trunc(number)));
  }
  document.querySelectorAll('[data-quantity-step]').forEach(function (button) { button.hidden = false; });
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-quantity-step]');
    if (!button) return;
    var input = button.form.querySelector('input[name=qty]');
    input.value = Math.max(Number(input.min) || 1, Math.min(Number(input.max) || 999999, clampQuantity(input) + Number(button.dataset.quantityStep)));
    input.dispatchEvent(new Event('input', {bubbles:true}));
  });
  function updateCart(cart) {
    document.querySelectorAll('[data-cart-nav-count]').forEach(function (node) { node.textContent = 'Cart (' + cart.count + ')'; });
    document.querySelectorAll('[data-product-id]').forEach(function (card) {
      var quantity = Number(cart.quantities[card.dataset.productId] || 0);
      var badge = card.querySelector('[data-in-cart]');
      badge.textContent = 'In cart ×' + quantity;
      badge.hidden = quantity === 0;
    });
    var dock = document.querySelector('[data-menu-cart-dock]');
    if (dock) {
      dock.hidden = cart.count === 0;
      dock.querySelector('[data-cart-dock-count]').textContent = cart.count + ' in your cart';
      dock.querySelector('[data-cart-dock-total]').textContent = cart.subtotal + (cart.unavailable ? ' available subtotal' : ' subtotal');
      document.body.classList.toggle('has-cart-dock', cart.count > 0);
    } else {
      document.body.classList.toggle('has-cart-dock', cart.items > 0);
    }
  }
  document.addEventListener('submit', async function (event) {
    var form = event.target;
    if (!form.matches('[data-cart-form]') || event.defaultPrevented) return;
    event.preventDefault();
    if (pending) { form.querySelectorAll('.is-loading').forEach(function (button) { button.classList.remove('is-loading'); }); return; }
    pending = true;
    var submitter = event.submitter;
    var body = new FormData(form);
    if (submitter && submitter.name) body.append(submitter.name, submitter.value);
    var productId = body.get('product_id');
    var focusName = submitter ? submitter.name : 'qty';
    var focusValue = submitter ? submitter.value : null;
    var controls = Array.from(form.querySelectorAll('button'));
    var originalDisabled = controls.map(function (button) { return button.disabled; });
    controls.forEach(function (button) { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 15000);
    try {
      var response = await fetch(form.getAttribute('action'), {method:'POST',body:body,credentials:'same-origin',cache:'no-store',signal:controller.signal,
        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) {
        throw new Error(response.status === 401 || response.status === 403 ? 'expired' : 'unconfirmed');
      }
      var data = await response.json();
      if (!data.cart || typeof data.ok !== 'boolean') throw new Error('unconfirmed');
      var scrollY = window.scrollY;
      if (typeof data.html === 'string') {
        // HTML comes from the same-origin PHP cart template, with escaped database text.
        var content = document.querySelector('[data-cart-content]');
        var documentFragment = new DOMParser().parseFromString(data.html, 'text/html');
        content.replaceChildren.apply(content, Array.from(documentFragment.body.childNodes));
        var nextForm = Array.from(content.querySelectorAll('.qty-form')).find(function (candidate) {
          return candidate.querySelector('[name=product_id]').value === productId;
        });
        if (nextForm) {
          var target = Array.from(nextForm.elements).find(function (control) { return control.name === focusName && (focusValue === null || control.value === focusValue); });
          if (target) target.focus({preventScroll:true});
        } else {
          var fallbackFocus = content.querySelector('.qty-form input[name=qty], .empty-state a');
          if (fallbackFocus) fallbackFocus.focus({preventScroll:true});
        }
      }
      updateCart(data.cart);
      if (data.ok && window.BulkBitesHighlight) {
        var changed = Array.from(document.querySelectorAll('[data-product-id], [data-cart-product]')).find(function (node) {
          return (node.dataset.productId || node.dataset.cartProduct) === productId;
        });
        window.BulkBitesHighlight(Array.from(document.querySelectorAll('[data-cart-nav-count], .summary-total, [data-cart-dock-total]')).concat(changed ? [changed.querySelector('[data-in-cart]') || changed.querySelector('.cart-line-total')] : []));
      }
      window.scrollTo({top:scrollY,behavior:'instant'});
      feedback(data.message, data.ok);
    } catch (error) {
      // A lost response may follow a committed mutation. Never resend automatically.
      feedback(error.message === 'expired' ? 'Your session expired. Refresh this page before trying again.' : 'We could not confirm the update. Check your cart before trying again.', false);
    } finally {
      clearTimeout(timeout);
      pending = false;
      form.removeAttribute('aria-busy');
      controls.forEach(function (button, index) { button.disabled = originalDisabled[index]; button.classList.remove('is-loading'); });
    }
  });
})();
