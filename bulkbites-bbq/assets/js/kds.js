/**
 * KDS polling + status actions (architecture §5/§6).
 * - Re-renders the board from api/orders.php every 3 seconds.
 * - Upgrades the server-rendered action forms to fetch (no full reload);
 *   interrupted mutations are never automatically submitted again.
 * - app.js registers its data-confirm listener first (this script is defer),
 *   and we bail when the event was already default-prevented by that confirm.
 * All server data is inserted with textContent — never innerHTML.
 */
(function () {
  'use strict';

  var script = document.currentScript || document.querySelector('script[data-kds-api]');
  var api = script ? script.getAttribute('data-kds-api') : null;
  var board = document.getElementById('kds-board');
  if (!api || !board) return;

  var picker = document.querySelector('[data-kitchen-stage]');
  if (picker) {
    var compact = window.matchMedia('(max-width: 760px)');
    document.body.classList.add('has-kitchen-stage-picker'); picker.parentElement.hidden = false;
    function filterStage() {
      board.querySelectorAll('[data-col]').forEach(function (column) { column.hidden = compact.matches && picker.value !== 'ALL' && column.dataset.col !== picker.value; });
    }
    picker.addEventListener('change',filterStage); compact.addEventListener('change',filterStage); filterStage();
  }
  var flashEl = document.getElementById('kds-flash');
  var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';

  var COLUMNS = [
    { key: 'NEW', statuses: ['NEW'] },
    { key: 'IN PROGRESS', statuses: ['ACCEPTED', 'PREPARING'] },
    { key: 'READY', statuses: ['READY'] }
  ];
  var LABELS = {
    ACCEPTED: 'Accept order',
    PREPARING: 'Start preparing',
    READY: 'Mark ready',
    COMPLETED: 'Complete order',
    CANCELLED: 'Cancel order',
    COLLECT_CASH: 'Confirm cash received'
  };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function ageLabel(seconds) {
    var m = Math.floor(seconds / 60);
    if (m < 1) return 'just now';
    return m + 'm ' + (seconds % 60) + 's';
  }

  function ageClass(seconds) {
    return 'kds-age' + (seconds >= 900 ? ' age-hot' : (seconds >= 300 ? ' age-warm' : ''));
  }

  function showFlash(message, ok) {
    if (!flashEl) return;
    flashEl.textContent = message;
    flashEl.className = ok ? 'flash flash-success' : 'flash flash-error';
    flashEl.style.display = '';
    clearTimeout(showFlash._t);
    showFlash._t = setTimeout(function () { flashEl.style.display = 'none'; }, 6000);
  }

  function buildActionForm(orderId, target, order) {
    var form = document.createElement('form');
    form.setAttribute('method', 'post');
    form.setAttribute('action', api);
    if (target === 'CANCELLED') {
      form.setAttribute('data-confirm', 'Cancel this order? This cannot be undone.');
    }
    if (target === 'COLLECT_CASH') {
      form.dataset.cashAmount = new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(Number(order.total));
    }
    var fields = {
      csrf_token: csrf,
      order_id: orderId,
      action: target,
      return: script.dataset.kdsReturn || 'staff/kitchen.php'
    };
    Object.keys(fields).forEach(function (name) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = String(fields[name]);
      form.appendChild(input);
    });
    var btn = document.createElement('button');
    btn.type = 'submit';
    btn.className = 'btn btn-sm ' + (target === 'CANCELLED' ? 'btn-outline kds-btn-cancel' : 'btn-primary');
    btn.textContent = target === 'COMPLETED' ? (order.fulfillment_type === 'DELIVERY' ? 'Confirm delivered' : 'Complete pickup') : (LABELS[target] || target);
    form.appendChild(btn);
    return form;
  }

  function buildCard(order) {
    var card = el('article', 'kds-card' + (order.age_seconds >= 900 ? ' age-card-hot' : ''));
    card.setAttribute('data-order-id', String(order.id));
    card.dataset.orderStatus = order.status;
    if (order.acceptance_wait) card.appendChild(el('p', 'acceptance-wait', order.acceptance_wait));

    var head = el('header', 'kds-card-head');
    head.appendChild(el('strong', 'kds-no', order.order_number));
    head.appendChild(el('span', ageClass(order.age_seconds), ageLabel(order.age_seconds)));
    card.appendChild(head);

    var meta = el('div', 'kds-meta');
    meta.appendChild(el('span', null, order.customer));
    meta.appendChild(el('span', null, order.payment_method + ' · ₱' + Number(order.total).toFixed(2)));
    card.appendChild(meta);
    card.appendChild(el('p', 'kds-meta', order.payment_label));
    card.appendChild(el('p', 'kds-meta', order.fulfillment_type === 'DELIVERY' ? 'Delivery · fee ?' + Number(order.delivery_fee).toFixed(2) : 'Pickup'));
    if (order.delivery_address) card.appendChild(el('p', 'kds-meta', order.delivery_address));
    if (order.fulfillment_type === 'DELIVERY' && order.status === 'READY') card.appendChild(el('p', 'kds-meta', 'Complete after delivery and payment are confirmed.'));
    if (order.pickup_contact) card.appendChild(el('p', 'kds-meta', order.pickup_contact));
    if (order.estimate) card.appendChild(el('p', 'kds-meta', order.estimate));

    var items = el('ul', 'kds-items');
    order.items.forEach(function (item) {
      var li = document.createElement('li');
      li.appendChild(el('span', 'kds-qty', item.qty + '×'));
      li.appendChild(document.createTextNode(' ' + item.name));
      items.appendChild(li);
    });
    card.appendChild(items);

    if (order.notes) {
      card.appendChild(el('p', 'kds-notes', 'Notes: ' + order.notes));
    }

    var actions = el('div', 'kds-actions');
    var more = el('details', 'order-more-actions');
    more.appendChild(el('summary', null, 'More actions'));
    (order.actions || []).forEach(function (target) {
      var form = buildActionForm(order.id, target, order);
      if (target === 'CANCELLED') more.appendChild(form);
      else actions.appendChild(form);
    });
    if (more.querySelector('form')) actions.appendChild(more);
    card.appendChild(actions);
    return card;
  }

  function render(data) {
    if (actionPending) return;
    if (data.summary) {
      document.querySelectorAll('[data-dashboard-stat]').forEach(function (node) {
        var key = node.dataset.dashboardStat;
        if (data.summary[key] === undefined) return;
        node.textContent = key === 'sales_today' ? new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(Number(data.summary[key])) : String(data.summary[key]);
      });
    }
    var orderingForm = document.querySelector('[data-ordering-control]');
    if (orderingForm && data.ordering) {
      document.querySelector('[data-ordering-label]').textContent = data.ordering.label;
      var open = data.ordering.is_open === true;
      orderingForm.querySelector('[name=ordering_status]').value = open ? 'CLOSED' : 'OPEN';
      var orderingButton = orderingForm.querySelector('[data-ordering-toggle]');
      var modeLabel = document.querySelector('[data-ordering-mode]');
      if (modeLabel) modeLabel.textContent = data.ordering.mode_label;
      var automaticButton = orderingForm.querySelector('[data-ordering-auto]');
      if (automaticButton) automaticButton.hidden = data.ordering.override === 'AUTO';
      orderingButton.textContent = open ? 'Close ordering' : 'Open ordering';
      orderingButton.classList.toggle('btn-outline', open);
      orderingButton.classList.toggle('btn-primary', !open);
    }
    var stockNotice = document.querySelector('[data-dashboard-low-stock]');
    if (stockNotice && Array.isArray(data.low_stock)) {
      stockNotice.hidden = data.low_stock.length === 0;
      stockNotice.querySelector('[data-low-stock-text]').textContent = 'Stock needs attention: ' + data.low_stock.map(function (item) { return item.name + ' (' + item.quantity + ' left)'; }).join(', ') + '.';
    }
    if (board.querySelector('dialog[open]')) return;
    var counts = data.counts || {};
    Object.keys(counts).forEach(function (key) {
      var node = board.ownerDocument.querySelector('[data-count="' + key + '"]');
      if (node) node.textContent = String(counts[key]);
    });

    var oldStages = new Map();
    board.querySelectorAll('[data-order-id]').forEach(function (card) { oldStages.set(card.dataset.orderId, card.dataset.orderStatus); });
    COLUMNS.forEach(function (col) {
      var section = board.querySelector('[data-col="' + col.key + '"]');
      if (!section) return;
      var list = data.orders.filter(function (o) { return col.statuses.indexOf(o.status) !== -1; });
      var container = section.querySelector('.kds-cards');
      var empty = section.querySelector('.kds-empty');
      var expanded = Array.from(container.querySelectorAll('[data-order-id] details[open]')).map(function (details) { return details.closest('[data-order-id]').dataset.orderId; });
      container.textContent = '';
      list.forEach(function (order) {
        var card = buildCard(order);
        var more = card.querySelector('details');
        if (more && expanded.indexOf(String(order.id)) !== -1) more.open = true;
        container.appendChild(card);
        if (oldStages.get(String(order.id)) !== order.status && window.BulkBitesHighlight) window.BulkBitesHighlight([card]);
        if (window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.enhance(card);
      });
      if (empty) empty.style.display = list.length ? 'none' : '';
      var countNode = section.querySelector('[data-col-count]');
      if (countNode) countNode.textContent = String(list.length);
    });
  }

  var failedPolls = 0;
  var offlineShown = false;
  var connection = document.querySelector('[data-kds-connection]');
  var lastChecked = new Date(), polling = false, actionPending = false;
  function connectionText(online) {
    if (!connection) return;
    connection.dataset.connection = online ? 'online' : 'offline';
    connection.textContent = (online ? 'Up to date. ' : 'Reconnecting — the board may be out of date. ') + 'Last checked ' + lastChecked.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + '.';
  }

  function poll() {
    if (polling || actionPending || document.hidden) return;
    polling = true;
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 8000);
    // Loading state: dim the board while the fresh data is on its way.
    board.classList.add('is-refreshing');
    fetch(api, {
      credentials: 'same-origin',
      cache: 'no-store', signal: controller.signal,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (data && data.ok) {
          failedPolls = 0;
          offlineShown = false;
          lastChecked = new Date(); connectionText(true);
          render(data);
        } else {
          noteFailure();
        }
      })
      .catch(function () { noteFailure(); /* retry next tick */ })
      .then(function () { clearTimeout(timeout); polling = false; board.classList.remove('is-refreshing'); });
  }

  // Error state: after 3 consecutive failures tell the user once (no spam).
  function noteFailure() {
    connectionText(false);
    failedPolls++;
    if (failedPolls >= 3 && !offlineShown) {
      offlineShown = true;
      showFlash('Connection problem — the board may be out of date. Retrying…', false);
    }
  }

  async function submitAction(form, actionUrl, submitter) {
    if (actionPending) return;
    var body = new FormData(form);
    if (submitter && submitter.name) body.append(submitter.name, submitter.value);
    var restoreLabel = window.BulkBitesPending(submitter, 'Updating…');
    actionPending = true;
    var buttons = Array.from(board.querySelectorAll('button[type="submit"]'));
    if (!board.contains(form)) buttons = buttons.concat(Array.from(form.querySelectorAll('button[type="submit"]')));
    var disabled = buttons.map(function (button) { return button.disabled; });
    buttons.forEach(function (button) { button.disabled = true; });
    var controller = new AbortController();
    var timeout = setTimeout(function () { controller.abort(); }, 12000);
    try {
      var response = await fetch(actionUrl, {
        method:'POST', credentials:'same-origin', signal:controller.signal,
        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, body:body
      });
      var data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Action failed. Check the order status.');
      if (window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.close();
      showFlash(data.message, true);
    } catch (error) {
      var message = error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
        ? 'Connection interrupted. Check the order status before trying again.' : error.message;
      if (window.BulkBitesOrderDialogs) window.BulkBitesOrderDialogs.error(form, message);
      showFlash(message, false);
    } finally {
      clearTimeout(timeout);
      restoreLabel();
      buttons.forEach(function (button,index) { button.disabled = disabled[index]; button.classList.remove('is-loading'); });
      actionPending = false;
      poll(); // GET only: never resend an interrupted payment or status action.
    }
  }

  document.addEventListener('submit', function (event) {
    if (event.defaultPrevented) return; // app.js confirm() said no
    var form = event.target;
    if (!form || !form.tagName || form.tagName !== 'FORM') return;
    if (!board.contains(form) && !form.matches('[data-ordering-control]')) return;
    // NB: never read form.action — a hidden input named "action" shadows it.
    var actionUrl = form.getAttribute('action') || '';
    if (actionUrl.indexOf('api/orders.php') === -1 && !form.matches('[data-ordering-control]')) return;
    event.preventDefault();
    submitAction(form, actionUrl, event.submitter);
  });

  setInterval(poll, 3000);
  window.addEventListener('online', poll);
  window.addEventListener('offline', function () { connectionText(false); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
