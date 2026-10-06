<?php
/**
 * Mascot — the panel's little robot. "Circuit".
 *
 * Inline SVG so it themes from the tokens (no extra request, no sprite).
 * Mood is set with data-mood; every pose is CSS. `mascot.js` flips the mood
 * when the app toasts, so the character reacts to what actually happened
 * instead of looping forever.
 *
 * @var string $mood  idle | happy | alert | thinking
 * @var int    $size
 */
$mood = $mood ?? 'idle';
$size = (int) ($size ?? 96);
$allowed = ['idle', 'happy', 'alert', 'thinking'];
if (! in_array($mood, $allowed, true)) {
    $mood = 'idle';
}
?>
<svg class="mascot" data-mascot data-mood="<?= esc($mood, 'attr') ?>"
     width="<?= esc((string) $size, 'attr') ?>" height="<?= esc((string) $size, 'attr') ?>"
     viewBox="0 0 96 96" fill="none" role="img" aria-label="Circuit, the panel mascot">
  <ellipse class="mascot__shadow" cx="48" cy="85" rx="20" ry="4"/>

  <g class="mascot__rig">
    <line class="mascot__wire" x1="48" y1="20" x2="48" y2="27"/>
    <circle class="mascot__tip" cx="48" cy="15" r="5"/>

    <path class="mascot__arm mascot__arm--l" d="M19 47 q-9 1 -10 10"/>
    <path class="mascot__arm mascot__arm--r" d="M77 47 q9 1 10 10"/>

    <rect class="mascot__shell" x="17" y="27" width="62" height="49" rx="17"/>
    <rect class="mascot__brow" x="26" y="34" width="44" height="7" rx="3.5"/>

    <g class="mascot__face">
      <g class="mascot__blink">
        <circle class="mascot__eye" cx="37" cy="51" r="7"/>
        <circle class="mascot__eye" cx="59" cy="51" r="7"/>
        <circle class="mascot__pupil" cx="37" cy="51" r="2.6"/>
        <circle class="mascot__pupil" cx="59" cy="51" r="2.6"/>
      </g>
      <path class="mascot__mouth" d="M41 64 q7 5 14 0"/>
    </g>

    <circle class="mascot__cheek" cx="27" cy="60" r="3.4"/>
    <circle class="mascot__cheek" cx="69" cy="60" r="3.4"/>
  </g>
</svg>