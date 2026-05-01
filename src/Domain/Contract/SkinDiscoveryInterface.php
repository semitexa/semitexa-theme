<?php

declare(strict_types=1);

namespace Semitexa\Theme\Domain\Contract;

use Semitexa\Theme\Domain\Model\SkinEntry;

/**
 * Enumerates skins known to the resolver.
 *
 * Contract:
 *  - A skin is valid if its tokens.css exists on disk.
 *  - Later sources override earlier sources on slug collision (the
 *    implementation decides the chain).
 *  - Returned entries carry a ready-to-serve URL. The resolver is not
 *    responsible for knowing how a given source routes to HTTP — that
 *    coupling lives here.
 */
interface SkinDiscoveryInterface
{
    /** @return list<SkinEntry> alphabetical by slug */
    public function availableSkins(): array;

    public function find(string $slug): ?SkinEntry;

    /** @return list<string> slug list — convenience for validation messages */
    public function availableSlugs(): array;
}
