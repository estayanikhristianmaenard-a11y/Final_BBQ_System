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
        var list = document.querySelector('[data-notification-list]');
        if (list && Array.isArray(data.latest)) {
          list.replaceChildren();
          data.latest.slice(0, 5).forEach(function (update) {
            var item = document.createElement('li');
            if (!update.is_read) item.className = 'unread';
            var content = item;
            if (update.order_url) {
              var link = document.createElement('a'); link.href = update.order_url;
              item.appendChild(link); content = link;
            }
            var title = document.createElement('strong'); title.textContent = update.title;
            var message = document.createElement('p'); message.textContent = update.message;
            content.append(title, message); list.appendChild(item);
          });
          if (!data.latest.length) { var empty = document.createElement('li'); empty.textContent = 'No updates yet.'; list.appendChild(empty); }
        }
      })
      .catch(function () { /* transient network error — try again next tick */ });
  }

  setInterval(refresh, 15000);
  var menu = document.querySelector('.notification-menu');
  if (menu) {
    menu.addEventListener('toggle', function () { if (menu.open) refresh(); });
    document.addEventListener('click', function (event) { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); } });
  }
})();
