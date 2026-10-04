<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Skin\Oklch;

/**
 * The text colour that sits on a filled colour (a brand button, a danger badge).
 *
 * White when it clears WCAG AA (4.5:1), near-black when only near-black does,
 * and white when neither does. The last case is the common one for a mid-tone
 * saturated fill in a dark skin: both candidates land around 4.3:1 and the
 * WCAG 2 ratio nudges towards near-black by a hair, which renders as muddy dark
 * text on a bright fill. WCAG 2 is known to under-rate light text on saturated
 * mid-tones; APCA scores white clearly ahead there. A tie the ratio cannot
 * decide is settled by legibility instead.
 */
final class OnColor
{
    public const LIGHT = '#ffffff';
    public const DARK = '#111111';
    private const AA = 4.5;

    public static function pick(string $fillHex): string
    {
        if (preg_match('/\A#[0-9a-fA-F]{6}\z/', $fillHex) !== 1) {
            return self::LIGHT;
        }
        if (ContrastScore::contrast($fillHex, self::LIGHT) >= self::AA) {
            return self::LIGHT;
        }
        if (ContrastScore::contrast($fillHex, self::DARK) >= self::AA) {
            return self::DARK;
        }
        return self::LIGHT;
    }
}
