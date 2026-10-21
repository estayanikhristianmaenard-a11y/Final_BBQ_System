(function () {
  'use strict';
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var modelURL = document.currentScript && document.currentScript.getAttribute('data-grill-3d');
  document.querySelectorAll('[data-grilling]').forEach(function (scene) {
    var button = scene.querySelector('[data-grill-pause]');
    var playbackIcon = button.querySelector('[data-grill-playback-icon]');
    var iconPath = playbackIcon.getAttribute('href').split('#')[0];
    var sauce = scene.querySelector('[data-grill-sauce]');
    var feedback = scene.querySelector('[data-grill-feedback]');
    var preview = scene.hasAttribute('data-grill-preview');
    var paused = false;
    var motionOverride = false;
    var sauceTimer;
    var model = null;
    scene.querySelector('.grill-controls').hidden = false;
    function finishSauce() {
      clearTimeout(sauceTimer);
      scene.classList.remove('is-basting');
      scene.classList.add('is-sauced');
      sauce.disabled = false;
      feedback.textContent = 'Sauce brushed!';
      if (model) model.finishBaste();
    }
    function update() {
      var playing = !paused && (!reducedMotion.matches || motionOverride);
      scene.dataset.animation = playing ? 'playing' : 'paused';
      scene.dataset.motion = playing ? 'enabled' : 'disabled';
      if (model) model.setPlaying(playing);
      if (sauce && scene.dataset.animation === 'paused' && scene.classList.contains('is-basting')) finishSauce();
      button.hidden = scene.dataset.renderer === 'image';
      button.setAttribute('aria-pressed', String(!playing));
      playbackIcon.setAttribute('href', iconPath + (playing ? '#pause' : '#play'));
      var playbackLabel = playing ? 'Pause grilling animation' : 'Play grilling animation';
      button.setAttribute('aria-label', playbackLabel);
      button.title = playbackLabel;
      if (feedback && reducedMotion.matches && !motionOverride && !scene.classList.contains('is-sauced')) feedback.textContent = 'Press Play for animation';
    }
    if (sauce) sauce.addEventListener('click', function () {
      if (sauce.disabled) return;
      scene.classList.remove('is-basting', 'is-sauced');
      if (scene.dataset.animation !== 'playing') { finishSauce(); return; }
      // Restart a short, user-triggered basting pass, including repeat clicks.
      void scene.offsetWidth;
      scene.classList.add('is-basting');
      sauce.disabled = true;
      feedback.textContent = 'Brushing sauce…';
      if (model) model.baste();
      sauceTimer = setTimeout(finishSauce, 3300);
    });
    button.addEventListener('click', function () {
      if (scene.dataset.animation === 'playing') { paused = true; }
      else { paused = false; motionOverride = true; if (feedback && !scene.classList.contains('is-sauced')) feedback.textContent = 'Ihaw preview'; }
      update();
    });
    reducedMotion.addEventListener('change', function () { motionOverride = false; update(); });
    update();
    if (preview && modelURL) {
      scene.dataset.renderer = 'loading';
      import(modelURL).then(function (module) {
        model = module.createGrill(scene.querySelector('.grill-art'));
        scene.classList.add('has-grill-3d');
        scene.dataset.renderer = 'webgl';
        if (feedback.textContent === 'Ihaw preview') feedback.textContent = 'Drag to turn the grill';
        update();
        if (scene.classList.contains('is-basting')) model.baste();
        else if (scene.classList.contains('is-sauced')) model.finishBaste();
        window.addEventListener('pagehide', function (event) { if (!event.persisted) model.dispose(); });
      }).catch(function () {
        scene.dataset.renderer = 'image';
        paused = true;
        update();
        button.hidden = true;
        if (sauce) sauce.hidden = true;
        feedback.textContent = 'Image preview — 3D unavailable';
      });
    }
  });
})();
