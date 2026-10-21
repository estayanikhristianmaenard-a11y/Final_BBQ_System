<?php /** Decorative grill render; motion is enabled by JS only with a pause control. */ $hasGrillScene = true; ?>
<div class="grill-scene<?= !empty($grillInteractive) ? ' grill-preview' : '' ?>" data-grilling<?= !empty($grillInteractive) ? ' data-grill-preview' : '' ?>>
  <div class="grill-art"<?= empty($grillInteractive) ? ' aria-hidden="true"' : '' ?>>
    <img src="<?= e(url('assets/images/grill-render.webp')) ?>" alt="" width="640" height="640" decoding="async">
    <div class="grill-steam" aria-hidden="true"><i></i><i></i><i></i></div>
    <?php if (!empty($grillInteractive)): ?>
    <svg class="grill-effects" viewBox="0 0 640 640" focusable="false" aria-hidden="true">
      <g class="grill-embers" fill="#ff872b">
        <ellipse cx="313" cy="474" rx="9" ry="5" transform="rotate(-26 313 474)"/>
        <ellipse cx="405" cy="424" rx="9" ry="5" transform="rotate(-26 405 424)"/>
        <ellipse cx="510" cy="373" rx="8" ry="4" transform="rotate(-26 510 373)"/>
      </g>
      <g class="grill-sauce" fill="none" stroke="#f4ac3d" stroke-width="23" stroke-linecap="round">
        <path d="M126 185 242 345"/>
        <path d="M203 151 314 311"/>
        <path d="M278 122 376 277"/>
        <path d="M365 125 438 256"/>
      </g>
    </svg>
    <svg class="grill-brush" viewBox="0 0 100 140" focusable="false" aria-hidden="true">
      <path d="M40 12a10 10 0 0 1 20 0v67H40z" fill="#c49459" stroke="#855b32" stroke-width="3"/>
      <path d="M45 14v57" fill="none" stroke="#edc995" stroke-width="4" stroke-linecap="round"/>
      <path d="M23 76h54v22H23z" fill="#c6cbc5" stroke="#6a716b" stroke-width="3"/>
      <path d="M25 96h50l7 34q-31 12-64 0z" fill="#bc4015" stroke="#762b13" stroke-width="3"/>
      <path d="m31 101-3 26m14-26-1 29m11-29 1 29m10-29 4 26" stroke="#f4ac3d" stroke-width="3" opacity=".7"/>
    </svg>
    <?php endif; ?>
  </div>
  <div class="grill-controls" hidden>
    <?php if (!empty($grillInteractive)): ?>
    <button class="grill-control grill-sauce-button" type="button" data-grill-sauce aria-label="Brush sauce" title="Brush sauce">
      <svg class="grill-control-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="<?= e(url('assets/images/nav-icons.svg#brush')) ?>"></use></svg>
    </button>
    <?php endif; ?>
    <button class="grill-control" type="button" data-grill-pause aria-label="Pause grilling animation" title="Pause grilling animation" aria-pressed="false" hidden>
      <svg class="grill-control-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use data-grill-playback-icon href="<?= e(url('assets/images/nav-icons.svg#pause')) ?>"></use></svg>
    </button>
    <?php if (!empty($grillInteractive) && empty($grillMenuLink)): ?><span class="grill-feedback" role="status" aria-live="polite" data-grill-feedback>Ihaw preview</span><?php endif; ?>
  </div>
  <?php if (!empty($grillMenuLink)): ?><span class="grill-feedback" role="status" aria-live="polite" data-grill-feedback>Ihaw preview</span><div class="grill-action-row"><a class="btn btn-primary banner-menu-button" href="#menu">View menu</a></div><?php endif; ?>
</div>
