(function () {
  'use strict';
  var active = null, previousOverflow, previousPadding;
  function close() { if (active && active.open) active.close(); }
  function open(dialog, trigger) {
    if (active) return;
    active = dialog;
    var gap = window.innerWidth - document.documentElement.clientWidth;
    previousOverflow = document.body.style.overflow;
    previousPadding = document.body.style.paddingRight;
    if (gap > 0) document.body.style.paddingRight = (parseFloat(getComputedStyle(document.body).paddingRight) + gap) + 'px';
    document.body.style.overflow = 'hidden'; dialog.showModal();
  }
  function restore(trigger) {
    document.body.style.overflow = previousOverflow;
    document.body.style.paddingRight = previousPadding;
    active = null;
    if (trigger && trigger.isConnected) trigger.focus({preventScroll:true});
  }
  function error(form, message) {
    var dialog = form.closest('dialog[open]');
    if (!dialog) return;
    var feedback = dialog.querySelector('[data-action-error]');
    if (!feedback) {
      feedback = document.createElement('p'); feedback.dataset.actionError = '';
      feedback.className = 'flash flash-error'; feedback.setAttribute('role','alert');
      dialog.insertBefore(feedback, dialog.firstChild.nextSibling);
    }
    feedback.textContent = message;
    feedback.scrollIntoView({block:'nearest'});
  }
  function enhance(root) {
    if (typeof HTMLDialogElement === 'undefined') return;
    root.querySelectorAll('details.order-more-actions, details.order-view').forEach(function (details) {
      var wrapper = document.createElement('div');
      wrapper.className = 'order-more-actions';
      var trigger = document.createElement('button');
      trigger.type = 'button'; trigger.className = 'btn btn-outline btn-sm';
      trigger.textContent = details.classList.contains('order-view') ? 'View order' : 'More actions'; trigger.setAttribute('aria-haspopup', 'dialog');
      var dialog = document.createElement('dialog');
      dialog.className = 'order-actions-dialog' + (details.classList.contains('order-view') ? ' order-view-dialog' : '');
      var row = details.closest('tr,article');
      var order = row && row.querySelector('strong');
      var heading = document.createElement('h2');
      heading.textContent = order ? order.textContent.trim() : 'Order actions';
      dialog.setAttribute('aria-label', heading.textContent + ' actions');
      var header = document.createElement('div'); header.className = 'order-actions-dialog-head';
      var dismiss = document.createElement('button');
      dismiss.type = 'button'; dismiss.className = 'btn btn-outline btn-sm'; dismiss.textContent = 'Close';
      header.append(heading, dismiss); dialog.appendChild(header);
      details.querySelector('summary').remove();
      while (details.firstChild) dialog.appendChild(details.firstChild);
      wrapper.append(trigger, dialog); details.replaceWith(wrapper);
      trigger.addEventListener('click', function () { open(dialog, trigger); });
      dismiss.addEventListener('click', close);
      dialog.addEventListener('close', function () { restore(trigger); });
      dialog.addEventListener('click', function (event) {
        var box = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) close();
      });
    });
  }
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (event.defaultPrevented || !form.matches('form[data-cash-amount]') || form.dataset.cashConfirmed === 'true' || typeof HTMLDialogElement === 'undefined') return;
    event.preventDefault();
    form.querySelectorAll('.is-loading').forEach(function (button) { button.classList.remove('is-loading'); });
    var existing = form.closest('dialog[open]');
    if (existing) {
      var panel = document.createElement('section'); panel.className = 'cash-confirm-panel';
      panel.setAttribute('aria-label','Confirm cash received');
      var question = document.createElement('p');
      question.textContent = 'Have you received the full ' + form.dataset.cashAmount + ' in cash?';
      var confirmed = form.cloneNode(true); confirmed.dataset.cashConfirmed = 'true';
      confirmed.querySelector('button[type="submit"]').textContent = 'Confirm payment';
      var back = document.createElement('button'); back.type = 'button'; back.className = 'btn btn-outline'; back.textContent = 'Back';
      panel.append(question,confirmed,back); form.hidden = true; form.after(panel);
      back.addEventListener('click',function () { panel.remove(); form.hidden = false; form.querySelector('button').focus({preventScroll:true}); });
      panel.querySelector('button').focus({preventScroll:true});
      return;
    }
    if (active) return;
    var trigger = event.submitter;
    var dialog = document.createElement('dialog'); dialog.className = 'order-actions-dialog';
    dialog.setAttribute('aria-label','Confirm cash received');
    var header = document.createElement('div'); header.className = 'order-actions-dialog-head';
    var heading = document.createElement('h2'); heading.textContent = 'Confirm cash received';
    var dismiss = document.createElement('button'); dismiss.type = 'button'; dismiss.className = 'btn btn-outline btn-sm'; dismiss.textContent = 'Close';
    header.append(heading,dismiss); dialog.appendChild(header);
    var message = document.createElement('p');
    message.textContent = 'Have you received the full ' + form.dataset.cashAmount + ' in cash? Confirm only after receiving payment.';
    dialog.appendChild(message);
    var confirmation = form.cloneNode(true); confirmation.dataset.cashConfirmed = 'true';
    var submit = confirmation.querySelector('button[type="submit"]');
    submit.textContent = 'Confirm payment'; submit.disabled = false; submit.classList.remove('is-loading');
    dialog.appendChild(confirmation); form.parentElement.appendChild(dialog);
    dismiss.addEventListener('click', close);
    dialog.addEventListener('close', function () { restore(trigger); dialog.remove(); });
    open(dialog,trigger);
  });
  window.BulkBitesOrderDialogs = {enhance:enhance, close:close, error:error};
  enhance(document);
})();
