(function () {
  'use strict';
  var root = document.querySelector('[data-managed-products]');
  if (!root || typeof HTMLDialogElement === 'undefined') return;
  var active = null, pending = false, oldOverflow, oldPadding;
  var feedback = document.querySelector('[data-product-feedback]');
  var refreshButton = document.querySelector('[data-refresh-products]');
  function report(message, dialog) {
    feedback.textContent = message;
    if (dialog) {
      var error = dialog.querySelector('[data-product-error]');
      error.textContent = message; error.hidden = false;
    }
  }
  function close() { if (active && !pending) active.close(); }
  function enhance() {
    root.querySelectorAll('details.product-editor').forEach(function (details) {
      var row = details.closest('tr');
      var summary = details.querySelector('summary');
      var trigger = document.createElement('button');
      trigger.type = 'button'; trigger.className = 'btn btn-outline btn-sm';
      trigger.textContent = 'Manage product'; trigger.setAttribute('aria-haspopup','dialog');
      var dialog = document.createElement('dialog'); dialog.className = 'product-manager-dialog';
      dialog.setAttribute('aria-label', 'Manage ' + row.querySelector('strong').textContent.trim());
      var header = document.createElement('div'); header.className = 'product-manager-head';
      var heading = document.createElement('h2'); heading.textContent = row.querySelector('strong').textContent.trim();
      var dismiss = document.createElement('button'); dismiss.type = 'button';
      dismiss.className = 'btn btn-outline btn-sm'; dismiss.textContent = 'Close';
      header.append(heading,dismiss); dialog.appendChild(header);
      var error = document.createElement('p'); error.className = 'flash flash-error';
      error.dataset.productError = ''; error.setAttribute('role','alert'); error.hidden = true;
      dialog.appendChild(error); summary.remove();
      while (details.firstChild) dialog.appendChild(details.firstChild);
      var wrapper = document.createElement('div'); wrapper.append(trigger,dialog); details.replaceWith(wrapper);
      trigger.addEventListener('click', function () {
        if (active) return;
        active = dialog; error.hidden = true;
        oldOverflow = document.body.style.overflow; oldPadding = document.body.style.paddingRight;
        var gap = window.innerWidth - document.documentElement.clientWidth;
        if (gap > 0) document.body.style.paddingRight = (parseFloat(getComputedStyle(document.body).paddingRight) + gap) + 'px';
        document.body.style.overflow = 'hidden'; dialog.showModal();
      });
      dismiss.addEventListener('click', close);
      dialog.addEventListener('cancel', function (event) { if (pending) event.preventDefault(); });
      dialog.addEventListener('close', function () {
        document.body.style.overflow = oldOverflow; document.body.style.paddingRight = oldPadding;
        active = null;
        if (trigger.isConnected) trigger.focus({preventScroll:true});
      });
    });
  }
  async function load(url, options) {
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 15000);
    try {
      var response = await fetch(url, Object.assign({credentials:'same-origin',cache:'no-store'},options || {},{signal:controller.signal}));
      var doc = new DOMParser().parseFromString(await response.text(),'text/html');
      if (!response.ok || !doc.querySelector('[data-managed-products]')) throw new Error('Could not update products. Check your sign-in and refresh the list.');
      var error = doc.querySelector('.flash-error');
      if (error) throw new Error(error.textContent.trim());
      return doc;
    } finally { clearTimeout(timeout); }
  }
  function replace(doc, rowId) {
    var x = window.scrollX, y = window.scrollY;
    var next = doc.querySelector('[data-managed-products]');
    root.replaceChildren.apply(root,Array.from(next.childNodes));
    enhance();
    if (window.BulkBitesEnhanceRecords) window.BulkBitesEnhanceRecords(root);
    var row = rowId && document.getElementById(rowId);
    var trigger = row && row.querySelector('button');
    if (trigger) trigger.focus({preventScroll:true});
    window.scrollTo(x,y); refreshButton.hidden = true;
  }
  document.addEventListener('submit', async function (event) {
    var form = event.target;
    if (event.defaultPrevented || !root.contains(form) || !form.closest('.product-manager-dialog')) return;
    event.preventDefault();
    if (pending) return;
    var dialog = form.closest('dialog');
    var rowId = form.closest('tr').id;
    var body = new FormData(form);
    if (event.submitter && event.submitter.name) body.append(event.submitter.name,event.submitter.value);
    var restoreLabel = window.BulkBitesPending(event.submitter, 'Saving…');
    var buttons = Array.from(dialog.querySelectorAll('button'));
    var disabled = buttons.map(function (button) { return button.disabled; });
    pending = true; dialog.setAttribute('aria-busy','true');
    buttons.forEach(function (button) { button.disabled = true; });
    report('Saving product…');
    dialog.querySelector('[data-product-error]').hidden = true;
    try {
      // Use existing CSRF-protected controllers and their server-rendered validation.
      var doc = await load(form.getAttribute('action'),{method:'POST',body:body});
      var message = doc.querySelector('.flash-success, .flash-warning');
      pending = false; close();
      // Let the native close event restore scrolling before replacing the dialog.
      await new Promise(function (resolve) { setTimeout(resolve,0); });
      replace(doc,rowId);
      report(message ? message.textContent.trim() : 'Product saved.');
    } catch (error) {
      var interrupted = error.name === 'AbortError' || error instanceof TypeError;
      report(interrupted ? 'Connection interrupted. Refresh products to check whether the change saved before trying again.' : error.message,dialog);
      refreshButton.hidden = false; // GET only; an ambiguous POST is never repeated.
    } finally {
      restoreLabel();
      pending = false; dialog.removeAttribute('aria-busy');
      buttons.forEach(function (button,index) { button.disabled = disabled[index]; button.classList.remove('is-loading'); });
    }
  });
  refreshButton.addEventListener('click', async function () {
    if (pending) return;
    refreshButton.disabled = true;
    try {
      var doc = await load(window.location.href);
      close(); await new Promise(function (resolve) { setTimeout(resolve,0); });
      replace(doc); report('Product list updated.');
    } catch (error) { report(error.message,active); }
    finally { refreshButton.disabled = false; }
  });
  enhance();
})();
