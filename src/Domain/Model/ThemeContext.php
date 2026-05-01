<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Model;

/**
 * Immutable per-request context used as input to theme resolution.
 *
 * Populated once per request after tenant + locale resolution finishes.
 * `$tenant` is nullable because bootstrap / public landing pages may
 * precede tenant resolution; resolvers must handle the null case.
 */
final readonly class ThemeContext
{
    public function __construct(
        public ?string $tenant,
        public string $domain,
        public string $locale,
    ) {
    }
}
