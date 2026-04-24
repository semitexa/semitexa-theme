<?php

declare(strict_types=1);

namespace Semitexa\Theme\Runtime;

use Semitexa\Core\Attribute\AsServerLifecycleListener;
use Semitexa\Core\Server\Lifecycle\ServerLifecycleContext;
use Semitexa\Core\Server\Lifecycle\ServerLifecyclePhase;
use Semitexa\Core\Server\Lifecycle\ServerLifecycleListenerInterface;
use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Ssr\Asset\ModuleAssetRegistry;
use Semitexa\Theme\Discovery\SkinDiscovery;

/**
 * Registers the `skins` asset alias at worker boot so both framework default
 * (`vendor/semitexa/skins-base/…/skins/`) and project-local (`src/skins/`)
 * skin dirs serve under the unified URL prefix `/assets/skins/<slug>/tokens.css`.
 *
 * Runs in `WorkerStartFinalize` phase — after `BootAssetRegistryListener`
 * (`WorkerStartAfterContainer`) has called `ModuleAssetRegistry::initialize()`.
 * `registerAlias()` prepends on repeat calls, so registration order matters:
 * framework first (lower priority), project last (higher priority).
 *
 * Without this listener, generated skins in `src/skins/` are discoverable by
 * PHP but unreachable by the browser — templates would emit 404 stylesheet URLs.
 */
#[AsServerLifecycleListener(
    phase: ServerLifecyclePhase::WorkerStartFinalize->value,
    priority: 0,
    requiresContainer: false,
)]
final class BootProjectSkinsAssetAliasListener implements ServerLifecycleListenerInterface
{
    public function handle(ServerLifecycleContext $context): void
    {
        $discovery = new SkinDiscovery(ProjectRoot::get());
        foreach ($discovery->rootsForAssetAlias() as $absoluteDir) {
            ModuleAssetRegistry::registerAlias('skins', $absoluteDir);
        }
    }
}
