/* Progressive enhancements: native navigation fallback and original POST forms remain usable. */
(function () {
  'use strict';
  var logout = document.querySelector('.operations .nav-logout');
  var navigation = document.getElementById('primary-nav');
  if (logout && navigation) {
    var originalParent = logout.parentElement;
    var mobileNav = window.matchMedia('(max-width: 760px)');
    function placeLogout() { if(mobileNav.matches) navigation.appendChild(logout); else originalParent.appendChild(logout); }
    mobileNav.addEventListener('change',placeLogout); placeLogout();
  }
  var toggle = document.querySelector('.nav-toggle');
  if (toggle) {
    document.body.classList.add('js-nav');
    toggle.hidden = false;
    function closeNavigation() {
      document.body.classList.remove('nav-open');
      toggle.setAttribute('aria-expanded', 'false');
    }
    toggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
        closeNavigation(); toggle.focus();
      }
    });
    document.querySelector('#primary-nav').addEventListener('click', function (event) {
      if (event.target.closest('a')) closeNavigation();
    });
  }

  document.querySelectorAll('.record-create').forEach(function (details) {
    var wide = window.matchMedia('(min-width: 761px)');
    function setInitialState() { details.open = (wide.matches && details.dataset.compact !== 'true') || details.dataset.editing === 'true'; }
    setInitialState();
    wide.addEventListener('change', setInitialState);
  });

  var recordPages = ['page-staff-orders','page-staff-inventory','page-manager-products',
    'page-manager-categories','page-manager-inventory','page-manager-customers','page-manager-users'];
  function enhanceRecords(root) {
  if (recordPages.some(function (name) { return document.body.classList.contains(name); })) {
    root.querySelectorAll('.data-table:not(.record-table)').forEach(function (table) {
      var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) { return th.textContent.trim(); });
      if (!headers.length) return;
      table.setAttribute('role', 'table');
      table.querySelectorAll('thead,tbody,tfoot').forEach(function (section) { section.setAttribute('role','rowgroup'); });
      table.querySelectorAll('tr').forEach(function (row) { row.setAttribute('role','row'); });
      table.querySelectorAll('th').forEach(function (cell) { cell.setAttribute('role','columnheader'); });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.from(row.cells).forEach(function (cell, index) {
          cell.setAttribute('role','cell');
          cell.dataset.label = headers[index] || 'Actions';
          if (cell.querySelector('form,details') || /details|actions|restock/i.test(cell.dataset.label)) cell.classList.add('record-wide');
          var value = document.createElement('div');
          value.className = 'cell-value';
          while (cell.firstChild) value.appendChild(cell.firstChild);
          cell.appendChild(value);
        });
      });
      table.classList.add('record-table');
      var parent = table.parentElement;
      while (parent && parent.tagName !== 'MAIN') {
        if (parent.classList.contains('card') || parent.classList.contains('queue-wrap')) parent.classList.add('has-record-table');
        parent = parent.parentElement;
      }
    });
  }
  }
  window.BulkBitesEnhanceRecords = enhanceRecords;
  enhanceRecords(document);
})();
