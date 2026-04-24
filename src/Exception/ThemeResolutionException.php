<?php

declare(strict_types=1);

namespace Semitexa\Theme\Exception;

/**
 * Raised when a ThemeResolverInterface cannot produce an assignment.
 *
 * Most often indicates a malformed rule set slipped past validation,
 * or a rule references a theme/skin that was removed after boot.
 */
class ThemeResolutionException extends \RuntimeException
{
}
