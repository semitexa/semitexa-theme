<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin;

use Semitexa\Theme\Application\Service\Skin\Oklch\ContrastScore;
use Semitexa\Theme\Application\Service\Skin\Oklch\Converter;

/**
 * The contrast table of a skin: every pair of tokens the components put one on
 * the other, per mode, with its WCAG 2 ratio and its APCA Lc.
 *
 * Gating pairs are the ones WCAG requires: body and muted text on every
 * surface, text on the accent and on each state fill (1.4.3, 4.5:1), the
 * accent as link text (4.5:1) and the focus ring against the page (1.4.11,
 * 3:1). Advisory pairs are reported but do not fail a skin: the strong border
 * against the page is under 3:1 in every shipped skin (measured 2026-10-14,
 * 1.8 to 2.7), and darkening it redraws every outline: a design decision,
 * not a fix.
 */
final class SkinContrastAudit
{
    /** @var list<array{string, string, float, bool}> foreground, background, required ratio, gating */
    private const PAIRS = [
        ['--ui-text-primary', '--ui-surface-page', ContrastScore::AA_NORMAL, true],
        ['--ui-text-primary', '--ui-surface-panel', ContrastScore::AA_NORMAL, true],
        ['--ui-text-primary', '--ui-surface-raised', ContrastScore::AA_NORMAL, true],
        ['--ui-text-primary', '--ui-surface-sunken', ContrastScore::AA_NORMAL, true],
        ['--ui-text-muted', '--ui-surface-page', ContrastScore::AA_NORMAL, true],
        ['--ui-text-muted', '--ui-surface-panel', ContrastScore::AA_NORMAL, true],
        ['--ui-text-muted', '--ui-surface-raised', ContrastScore::AA_NORMAL, true],
        ['--ui-text-muted', '--ui-surface-sunken', ContrastScore::AA_NORMAL, true],
        ['--ui-accent-brand', '--ui-surface-page', ContrastScore::AA_NORMAL, true],
        ['--ui-text-on-accent', '--ui-accent-brand', ContrastScore::AA_NORMAL, true],
        ['--ui-text-on-success', '--ui-state-success', ContrastScore::AA_NORMAL, true],
        ['--ui-text-on-warning', '--ui-state-warning', ContrastScore::AA_NORMAL, true],
        ['--ui-text-on-danger', '--ui-state-danger', ContrastScore::AA_NORMAL, true],
        ['--ui-text-on-info', '--ui-state-info', ContrastScore::AA_NORMAL, true],
        ['--ui-focus-ring', '--ui-surface-page', ContrastScore::AA_LARGE, true],
        ['--ui-border-strong', '--ui-surface-page', ContrastScore::AA_LARGE, false],
    ];

    /**
     * @param array<string, array<string, string>> $tokensByMode mode => token => value, as in skin.json
     * @return list<array{mode: string, foreground: string, background: string, ratio: float|null, apca: float|null, required: float, gating: bool, pass: bool}>
     */
    public static function audit(array $tokensByMode): array
    {
        $rows = [];
        foreach ($tokensByMode as $mode => $tokens) {
            // What the skin emits, not only what its manifest spells out: an
            // older skin.json without on-state tokens gets them derived.
            $tokens = DualSkinPalette::withDerived($tokens);
            foreach (self::PAIRS as [$foreground, $background, $required, $gating]) {
                $fg = self::opaque($tokens[$foreground] ?? null, $tokens['--ui-surface-page'] ?? null);
                $bg = self::opaque($tokens[$background] ?? null, $tokens['--ui-surface-page'] ?? null);
                // A missing token is a failure, not a pass: the component falls
                // back to something nobody measured.
                $measurable = self::isHex($fg) && self::isHex($bg);
                $ratio = $measurable ? round(ContrastScore::contrast($fg, $bg), 2) : null;
                $rows[] = [
                    'mode' => (string) $mode,
                    'foreground' => $foreground,
                    'background' => $background,
                    'ratio' => $ratio,
                    'apca' => $measurable ? round(ContrastScore::apcaLc($fg, $bg), 1) : null,
                    'required' => $required,
                    'gating' => $gating,
                    // Compared unrounded: 4.496 must not pass as 4.50.
                    'pass' => $measurable && ContrastScore::contrast($fg, $bg) >= $required,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<array{gating: bool, pass: bool}> $rows
     * @return list<array{gating: bool, pass: bool}>
     */
    public static function failures(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => $row['gating'] && !$row['pass']));
    }

    /**
     * A translucent token (a glass panel) is seen over the page, so it is
     * measured composited onto an opaque page colour; anything else is kept.
     */
    private static function opaque(?string $value, ?string $page): ?string
    {
        if ($value === null || !preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/i', trim($value), $m)) {
            return $value;
        }
        $alpha = isset($m[4]) && $m[4] !== '' ? max(0.0, min(1.0, (float) $m[4])) : 1.0;
        if ($alpha < 1.0 && !self::isHex($page)) {
            return null;
        }
        [$pr, $pg, $pb] = $alpha < 1.0 ? Converter::hexToSrgb((string) $page) : [0.0, 0.0, 0.0];
        $mix = static fn (string $channel, float $under): int => (int) round(((float) $channel / 255 * $alpha + $under * (1 - $alpha)) * 255);

        return sprintf('#%02x%02x%02x', $mix($m[1], $pr), $mix($m[2], $pg), $mix($m[3], $pb));
    }

    private static function isHex(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) === 1;
    }
}
