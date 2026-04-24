<?php

declare(strict_types=1);

namespace Semitexa\Theme\Support;

use Semitexa\Theme\Model\ActiveWhen;
use Semitexa\Theme\Model\ThemeContext;

/**
 * Shared 7-level specificity matcher used by both theme selection
 * (across manifests) and conditional skin selection (within a theme).
 *
 * Level table (more specific first):
 *   0  tenant + domain + locale
 *   1  tenant + domain
 *   2  tenant + locale
 *   3  tenant
 *   4  domain + locale
 *   5  domain
 *   6  locale
 *   7  fallback (ActiveWhen::always or explicit "no filters" marker)
 *
 * `match()` returns the first candidate that matches at the highest
 * specificity, with declaration-order tiebreak within a level.
 */
final class SpecificityMatcher
{
    public const LEVEL_LABELS = [
        0 => 'tenant+domain+locale',
        1 => 'tenant+domain',
        2 => 'tenant+locale',
        3 => 'tenant',
        4 => 'domain+locale',
        5 => 'domain',
        6 => 'locale',
        7 => 'fallback',
    ];

    private const LEVELS = [
        0 => ['tenant', 'domain', 'locale'],
        1 => ['tenant', 'domain'],
        2 => ['tenant', 'locale'],
        3 => ['tenant'],
        4 => ['domain', 'locale'],
        5 => ['domain'],
        6 => ['locale'],
    ];

    /**
     * @param list<array{active_when: ActiveWhen, payload: mixed}> $candidates
     * @return array{idx: int, label: string, payload: mixed}|null
     */
    public static function match(array $candidates, ThemeContext $ctx): ?array
    {
        foreach (self::LEVELS as $levelIdx => $requiredFields) {
            foreach ($candidates as $c) {
                if (self::candidateMatchesLevel($c['active_when'], $ctx, $requiredFields)) {
                    return [
                        'idx' => $levelIdx,
                        'label' => self::LEVEL_LABELS[$levelIdx],
                        'payload' => $c['payload'],
                    ];
                }
            }
        }

        // Fallback level: any candidate whose ActiveWhen is the root marker (`always=true`
        // with no filters). First one wins.
        foreach ($candidates as $c) {
            if ($c['active_when']->isRootFallback()) {
                return [
                    'idx' => 7,
                    'label' => self::LEVEL_LABELS[7],
                    'payload' => $c['payload'],
                ];
            }
        }

        return null;
    }

    /**
     * A candidate matches a level when its ActiveWhen constrains EXACTLY
     * the required fields (all others null) AND each constrained field
     * equals the context value. This prevents a more-specific candidate
     * from being claimed by a less-specific level.
     *
     * @param list<string> $requiredFields
     */
    private static function candidateMatchesLevel(ActiveWhen $aw, ThemeContext $ctx, array $requiredFields): bool
    {
        if ($aw->always) {
            return false; // root fallback is handled separately at level 7
        }
        foreach (['tenant', 'domain', 'locale'] as $field) {
            $awValue = $aw->$field;
            $shouldBeSet = in_array($field, $requiredFields, true);

            if ($shouldBeSet) {
                if ($awValue === null || $awValue !== $ctx->$field) {
                    return false;
                }
            } elseif ($awValue !== null) {
                return false;
            }
        }
        return true;
    }
}
