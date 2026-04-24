<?php

declare(strict_types=1);

namespace Semitexa\Theme\Model;

/**
 * A single discovered skin — the unit SkinDiscoveryInterface emits.
 *
 * Carries the slug plus the ready-to-serve URL and absolute FS path so the
 * resolver and validator don't reconstruct them. URL depends on which
 * source the skin came from (packaged skins-base vs future per-project
 * overrides), so we compute it once at discovery time.
 */
final readonly class SkinEntry
{
    public function __construct(
        public string $slug,
        public string $tokensUrl,       // e.g. '/assets/skins-base/skins/batumi/tokens.css'
        public string $tokensFilePath,  // absolute filesystem path (used by validator)
        public string $source,          // e.g. 'skins-base' (or 'project' in a future leaf)
    ) {
    }
}
