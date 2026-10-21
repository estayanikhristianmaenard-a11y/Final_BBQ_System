/* Shared order updates: keep navigation, scroll and mutation authority on the server. */
(function () {
  'use strict';
  var root = document.querySelector('[data-live-orders]');
  if (!root) return;
  var feedback = document.querySelector('[data-live-feedback]');
  var timer, busy = false, actionPending = false, stopped = false;
  var lastHTML = null;
  var interval = 3000;
  function report(message) { if (feedback) feedback.textContent = message; }
  function schedule() { clearTimeout(timer); if (!stopped) timer = setTimeout(check, interval); }
  async function request(url, options) {
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 12000);
    try {
      var response = await fetch(url, Object.assign({credentials:'same-origin',cache:'no-store'}, options || {}, {signal:controller.signal}));
      if (response.redirected || response.status === 401 || response.status === 403) {
        stopped = true;
        throw new Error('Updates stopped. Sign in again or check your access.');
      }
      if (!response.ok) {
        var message = 'Could not update orders. Retrying automatically.';
        if ((response.headers.get('content-type') || '').includes('application/json')) {
          var data = await response.json(); message = data.message || message;
        }
        throw new Error(message);
      }
      return response;
    } finally { clearTimeout(timeout); }
  }
  async function refresh(force) {
    var response = await request(window.location.href);
    var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    var next = doc.querySelector('[data-live-orders]');
    if (!next) throw new Error('Order updates unavailable. Check your sign-in.');
    var html = next.innerHTML;
    if (!force && (html === lastHTML || root.querySelector('dialog[open]'))) return false;
    // Never erase a manager's estimate/refund draft during a background check.
    if (!force && root.contains(document.activeElement) && document.activeElement.matches('input,textarea,select')) return false;
    var oldStages = new Map();
    root.querySelectorAll('tr[id]').forEach(function (row) {
      var badge = row.querySelector('.badge.status');
      if (badge) oldStages.set(row.id, badge.textContent.trim());
    });
    var oldCallout = root.querySelector('.order-callout');
    var oldInstruction = oldCallout && oldCallout.textContent.trim();
    var scrollX = window.scrollX, scrollY = window.scrollY;
    var focused = document.activeElement;
    var row = focused && focused.closest('tr[id]');
    var rowId = row && row.id;
    var openDetails = Array.from(root.querySelectorAll('tr[id] details[open]')).map(function (details) { return details.closest('tr').id; });
    // Retain the existing decorative grill and its playback listeners while preparing.
    var grill = root.querySelector('[data-grilling]');
    var incomingGrill = next.querySelector('[data-grilling]');
    if (grill && incomingGrill) incomingGrill.replaceWith(grill);
    root.replaceChildren.apply(root, Array.from(next.childNodes));
    root.dataset.liveRevision = next.dataset.liveRevision || '';
    lastHTML = html;
    if (window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.enhance(root);
    if (window.BulkBitesEnhanceRecords) window.BulkBitesEnhanceRecords(root);
    openDetails.forEach(function (id) { var details = root.querySelector('#' + id + ' details'); if (details) details.open = true; });
    if (rowId) {
      var currentRow = document.getElementById(rowId);
      var button = currentRow && currentRow.querySelector('button:not(:disabled)');
      if (button) button.focus({preventScroll:true});
    }
    if (window.BulkBitesHighlight) {
      var changes = [];
      var callout = root.querySelector('.order-callout');
      if (callout && callout.textContent.trim() !== oldInstruction) changes.push(callout);
      root.querySelectorAll('tr[id]').forEach(function (row) {
        var badge = row.querySelector('.badge.status');
        if (badge && oldStages.get(row.id) !== badge.textContent.trim()) changes.push(badge);
      });
      window.BulkBitesHighlight(changes);
    }
    window.scrollTo(scrollX, scrollY);
    return true;
  }
  async function check(manual) {
    clearTimeout(timer);
    if (busy || actionPending || (document.hidden && manual !== true)) { schedule(); return; }
    busy = true;
    try {
      var changed = false;
      if (root.dataset.liveApi) {
        var response = await request(root.dataset.liveApi);
        var data = await response.json();
        if (!data.ok || !data.revision) throw new Error('Order updates unavailable. Retrying automatically.');
        if (data.revision !== root.dataset.liveRevision) {
          changed = await refresh(false);
          if (!changed) {
            report(root.querySelector('dialog[open]') ? 'New update available. Close this window to refresh.' : 'New update available. Finish editing to refresh.');
            return;
          }
        }
      } else { changed = await refresh(false); }
      report(changed ? (root.dataset.liveLabel || 'Order status') + ' updated.' : 'Up to date. Checking every 3 seconds.');
    } catch (error) { report(stopped ? error.message : 'Reconnecting. Status may be out of date; updates will resume automatically.'); }
    finally { busy = false; schedule(); }
  }
  // app.js runs the confirmation prompt before this delegated listener.
  document.addEventListener('submit', async function (event) {
    var form = event.target;
    if (event.defaultPrevented || !root.contains(form) || !(/(?:api\/orders|staff\/ordering)\.php(?:[?#]|$)/).test(form.getAttribute('action') || '')) return;
    event.preventDefault();
    if (actionPending) return;
    var body = new FormData(form);
    if (event.submitter && event.submitter.name) body.append(event.submitter.name, event.submitter.value);
    var restoreLabel = window.BulkBitesPending(event.submitter, 'Updating…');
    actionPending = true; clearTimeout(timer);
    var buttons = Array.from(root.querySelectorAll('button'));
    var disabled = buttons.map(function (button) { return button.disabled; });
    buttons.forEach(function (button) { button.disabled = true; });
    report('Updating order…');
    try {
      var response = await request(form.getAttribute('action'), {method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:body});
      var data = await response.json();
      if (!data.ok) throw new Error(data.message || 'The order action could not be completed.');
      if (window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.close();
      await refresh(true);
      report(data.message || 'Order updated.');
    } catch (error) {
      var message = stopped ? error.message : error.name === 'AbortError' || error instanceof TypeError ? 'Connection interrupted. Check the updated status before trying again.' : error.message;
      var dialog = form.closest('dialog[open]');
      if (dialog && window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.error(form, message);
      else { try { await refresh(true); } catch (ignored) {} }
      report(message);
    } finally {
      restoreLabel();
      buttons.forEach(function (button, index) { button.disabled = disabled[index]; button.classList.remove('is-loading'); });
      actionPending = false; schedule();
    }
  });
  document.addEventListener('click', function (event) {
    if (event.target.closest('#refresh-order-status')) { event.preventDefault(); check(true); }
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(true); });
  window.addEventListener('online', function () { check(true); });
  window.addEventListener('offline', function () { report('Offline. Updates will resume automatically when connected.'); });
  check(true);
})();
