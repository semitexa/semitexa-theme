<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

enum TokenContract: string
{
    case TextPrimary = '--ui-text-primary';
    case TextMuted = '--ui-text-muted';
    case TextOnAccent = '--ui-text-on-accent';

    case SurfacePage = '--ui-surface-page';
    case SurfacePanel = '--ui-surface-panel';
    case SurfaceRaised = '--ui-surface-raised';
    case SurfaceSunken = '--ui-surface-sunken';

    case BorderSubtle = '--ui-border-subtle';
    case BorderStrong = '--ui-border-strong';

    case AccentBrand = '--ui-accent-brand';
    case AccentBrandContrast = '--ui-accent-brand-contrast';

    case StateSuccess = '--ui-state-success';
    case StateWarning = '--ui-state-warning';
    case StateDanger = '--ui-state-danger';
    case StateInfo = '--ui-state-info';

    case FocusRing = '--ui-focus-ring';

    case Chart1 = '--ui-chart-1';
    case Chart2 = '--ui-chart-2';
    case Chart3 = '--ui-chart-3';
    case Chart4 = '--ui-chart-4';
    case Chart5 = '--ui-chart-5';
    case Chart6 = '--ui-chart-6';
    case Chart7 = '--ui-chart-7';
    case Chart8 = '--ui-chart-8';

    // v2 non-color tokens. Added in epic skin-generator-v2 Phase A+B.
    // Grammar + primitives consume these via `var(--ui-X, <fallback>)` so
    // v1 skins that omit them still render at historical defaults.

    case RadiusNone = '--ui-radius-none';
    case RadiusSm = '--ui-radius-sm';
    case RadiusMd = '--ui-radius-md';
    case RadiusLg = '--ui-radius-lg';
    case RadiusPill = '--ui-radius-pill';

    case ShadowXs = '--ui-shadow-xs';
    case ShadowSm = '--ui-shadow-sm';
    case ShadowMd = '--ui-shadow-md';
    case ShadowLg = '--ui-shadow-lg';
    case ShadowColor = '--ui-shadow-color';

    case MotionDurationFast = '--ui-motion-duration-fast';
    case MotionDurationNormal = '--ui-motion-duration-normal';
    case MotionDurationSlow = '--ui-motion-duration-slow';
    case MotionEasingStandard = '--ui-motion-easing-standard';
    case MotionEasingEmphasized = '--ui-motion-easing-emphasized';

    case SurfaceBlur = '--ui-surface-blur';
    case SurfaceSaturation = '--ui-surface-saturation';
}
