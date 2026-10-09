/**
 * Notification polling (architecture §10): every 15 s refresh the header bell's
 * unread count. No-ops when the badge element is absent (any non-customer page).
 * Endpoint URL comes from the script tag's data-notif-api attribute.
 */
(function () {
  'use strict';

  var badge = document.getElementById('notif-count');
  if (!badge) return;

  var script = document.currentScript || document.querySelector('script[data-notif-api]');
  var api = script ? script.getAttribute('data-notif-api') : null;
  if (!api) return;

  function refresh() {
    fetch(api, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data || typeof data.unread !== 'number') return;
        badge.textContent = String(data.unread);
        badge.style.display = data.unread > 0 ? '' : 'none';
      })
      .catch(function () { /* transient network error — try again next tick */ });
  }

  setInterval(refresh, 15000);
})();
