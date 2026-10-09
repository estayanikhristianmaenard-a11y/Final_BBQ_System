/**
 * KDS polling + status actions (architecture §5/§6).
 * - Re-renders the board from api/orders.php every 10 seconds.
 * - Upgrades the server-rendered action forms to fetch (no full reload);
 *   if anything fails it falls back to a normal form submit.
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
    CANCELLED: 'Cancel order'
  };
  var TRANSITIONS = {
    NEW: ['ACCEPTED', 'CANCELLED'],
    ACCEPTED: ['PREPARING', 'CANCELLED'],
    PREPARING: ['READY', 'CANCELLED'],
    READY: ['COMPLETED'],
    COMPLETED: [],
    CANCELLED: []
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

  function buildActionForm(orderId, target) {
    var form = document.createElement('form');
    form.setAttribute('method', 'post');
    form.setAttribute('action', api);
    if (target === 'CANCELLED') {
      form.setAttribute('data-confirm', 'Cancel this order? This cannot be undone.');
    }
    var fields = {
      csrf_token: csrf,
      order_id: orderId,
      action: target,
      return: 'staff/kitchen.php'
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
    btn.textContent = LABELS[target] || target;
    form.appendChild(btn);
    return form;
  }

  function buildCard(order) {
    var card = el('article', 'kds-card' + (order.age_seconds >= 900 ? ' age-card-hot' : ''));
    card.setAttribute('data-order-id', String(order.id));

    var head = el('header', 'kds-card-head');
    head.appendChild(el('strong', 'kds-no', order.order_number));
    head.appendChild(el('span', ageClass(order.age_seconds), ageLabel(order.age_seconds)));
    card.appendChild(head);

    var meta = el('div', 'kds-meta');
    meta.appendChild(el('span', null, order.customer));
    meta.appendChild(el('span', null, order.payment_method + ' · ₱' + Number(order.total).toFixed(2)));
    card.appendChild(meta);

    var items = el('ul', 'kds-items');
    order.items.forEach(function (item) {
      var li = document.createElement('li');
      li.appendChild(el('span', 'kds-qty', item.qty + '×'));
      li.appendChild(document.createTextNode(' ' + item.name));
      items.appendChild(li);
    });
    card.appendChild(items);

    if (order.notes) {
      card.appendChild(el('p', 'kds-notes', '💬 ' + order.notes));
    }

    var actions = el('div', 'kds-actions');
    (TRANSITIONS[order.status] || []).forEach(function (target) {
      actions.appendChild(buildActionForm(order.id, target));
    });
    card.appendChild(actions);
    return card;
  }

  function render(data) {
    var counts = data.counts || {};
    Object.keys(counts).forEach(function (key) {
      var node = board.ownerDocument.querySelector('[data-count="' + key + '"]');
      if (node) node.textContent = String(counts[key]);
    });

    COLUMNS.forEach(function (col) {
      var section = board.querySelector('[data-col="' + col.key + '"]');
      if (!section) return;
      var list = data.orders.filter(function (o) { return col.statuses.indexOf(o.status) !== -1; });
      var container = section.querySelector('.kds-cards');
      var empty = section.querySelector('.kds-empty');
      container.textContent = '';
      list.forEach(function (order) { container.appendChild(buildCard(order)); });
      if (empty) empty.style.display = list.length ? 'none' : '';
      var countNode = section.querySelector('[data-col-count]');
      if (countNode) countNode.textContent = String(list.length);
    });
  }

  var failedPolls = 0;
  var offlineShown = false;

  function poll() {
    // Loading state: dim the board while the fresh data is on its way.
    board.classList.add('is-refreshing');
    fetch(api, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (data && data.ok) {
          failedPolls = 0;
          offlineShown = false;
          render(data);
        } else {
          noteFailure();
        }
      })
      .catch(function () { noteFailure(); /* retry next tick */ })
      .then(function () { board.classList.remove('is-refreshing'); });
  }

  // Error state: after 3 consecutive failures tell the user once (no spam).
  function noteFailure() {
    failedPolls++;
    if (failedPolls >= 3 && !offlineShown) {
      offlineShown = true;
      showFlash('Connection problem — the board may be out of date. Retrying…', false);
    }
  }

  function submitAction(form, actionUrl) {
    var btn = form.querySelector('button[type="submit"], button');
    if (btn) btn.disabled = true;
    fetch(actionUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form)
    })
      .then(function (res) { return res.json().then(function (data) { return { res: res, data: data }; }); })
      .then(function (r) {
        if (r.data && r.data.ok) {
          showFlash(r.data.message, true);
          poll();
        } else {
          showFlash((r.data && r.data.message) || 'Action failed.', false);
          if (btn) btn.disabled = false;
          poll();
        }
      })
      .catch(function () {
        // Network/parse failure → behave like a plain form
        if (btn) btn.disabled = false;
        HTMLFormElement.prototype.submit.call(form);
      });
  }

  document.addEventListener('submit', function (event) {
    if (event.defaultPrevented) return; // app.js confirm() said no
    var form = event.target;
    if (!form || !form.tagName || form.tagName !== 'FORM') return;
    if (!board.contains(form)) return;
    // NB: never read form.action — a hidden input named "action" shadows it.
    var actionUrl = form.getAttribute('action') || '';
    if (actionUrl.indexOf('api/orders.php') === -1) return;
    event.preventDefault();
    submitAction(form, actionUrl);
  });

  setInterval(poll, 10000);
})();
