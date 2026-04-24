<?php

declare(strict_types=1);

namespace Semitexa\Theme\Contract;

use Semitexa\Theme\Exception\ThemeResolutionException;
use Semitexa\Theme\Model\ThemeAssignment;
use Semitexa\Theme\Model\ThemeContext;

/**
 * Resolves the active theme + skin for a request context.
 *
 * Contract:
 *  - Resolution MUST always succeed for a valid config (default rule is an
 *    invariant of ThemeRulesSet). A resolver that cannot produce an
 *    assignment throws ThemeResolutionException — never returns null.
 *  - Implementations should be pure with respect to $ctx: same input →
 *    same output within a given rule set. Side-effect-free.
 *  - Implementations must NOT call AssetCollector, Twig, or any rendering
 *    layer. The returned ThemeAssignment is the handoff.
 */
interface ThemeResolverInterface
{
    /**
     * @throws ThemeResolutionException when configuration is invalid (e.g. default missing)
     */
    public function resolve(ThemeContext $ctx): ThemeAssignment;
}
