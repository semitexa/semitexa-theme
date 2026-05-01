<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Model;

/**
 * Declares when a theme (or a skin rule inside a theme) is active.
 *
 * `always=true` is the explicit root marker — matches every request at
 * the lowest specificity level. Exactly one manifest in a valid config
 * has `always=true`; every other manifest narrows via tenant / domain /
 * locale (any subset).
 *
 * Non-null fields contribute to specificity exactly like ThemeRule did
 * in the previous central-config model. The 7-level algorithm is
 * unchanged — only the source of rules changed.
 */
final readonly class ActiveWhen
{
    public function __construct(
        public bool $always,
        public ?string $tenant,
        public ?string $domain,
        public ?string $locale,
    ) {
    }

    public static function always(): self
    {
        return new self(true, null, null, null);
    }

    /** @param array<string, mixed> $raw */
    public static function fromArray(array $raw): self
    {
        $always = (bool) ($raw['always'] ?? false);
        $tenant = self::str($raw, 'tenant');
        $domain = self::str($raw, 'domain');
        $locale = self::str($raw, 'locale');
        return new self($always, $tenant, $domain, $locale);
    }

    public function isRootFallback(): bool
    {
        return $this->always && $this->tenant === null && $this->domain === null && $this->locale === null;
    }

    /** @param array<string, mixed> $raw */
    private static function str(array $raw, string $key): ?string
    {
        if (! array_key_exists($key, $raw) || $raw[$key] === null) {
            return null;
        }
        $v = trim((string) $raw[$key]);
        return $v === '' ? null : $v;
    }
}
