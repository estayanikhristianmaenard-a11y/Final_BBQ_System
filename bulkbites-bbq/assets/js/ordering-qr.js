(function () {
  'use strict';
  var canvas = document.getElementById('ordering-qr');
  if (!canvas) return;
  var status = document.getElementById('qr-status');
  try {
    var code = qrcode(0, 'H');
    code.addData(canvas.dataset.orderingUrl, 'Byte');
    code.make();
    var modules = code.getModuleCount(), quiet = 4, scale = 10;
    canvas.width = canvas.height = (modules + quiet * 2) * scale;
    var context = canvas.getContext('2d');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#000';
    for (var row = 0; row < modules; row++) {
      for (var col = 0; col < modules; col++) {
        if (code.isDark(row, col)) context.fillRect((col + quiet) * scale, (row + quiet) * scale, scale, scale);
      }
    }
    var download = document.getElementById('download-qr');
    download.href = canvas.toDataURL('image/png');
    download.hidden = false;
    status.textContent = 'QR ready. Test it with a phone camera before printing.';
    document.getElementById('print-qr').addEventListener('click', function () { window.print(); });
  } catch (error) {
    status.textContent = 'The QR could not be generated. Use the clickable ordering link and reload this page.';
  }
})();
