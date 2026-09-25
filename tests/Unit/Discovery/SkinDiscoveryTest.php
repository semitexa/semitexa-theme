<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Discovery;

use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Discovery\SkinDiscovery;

/**
 * The resolver calls find() on every request through a worker-lifetime
 * SkinDiscovery, so the filesystem scan must happen once per instance while
 * keeping the discovery contract (project overrides framework, alphabetical,
 * tokens.css required).
 */
final class SkinDiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/semitexa-skin-discovery-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_discovers_framework_and_project_skins_with_project_winning(): void
    {
        $this->writeSkin(SkinDiscovery::FRAMEWORK_SKINS_DIR, 'default');
        $this->writeSkin(SkinDiscovery::FRAMEWORK_SKINS_DIR, 'ocean');
        $this->writeSkin(SkinDiscovery::PROJECT_SKINS_DIR, 'ocean');
        $this->writeSkin(SkinDiscovery::PROJECT_SKINS_DIR, 'brand');
        mkdir($this->root . SkinDiscovery::PROJECT_SKINS_DIR . '/no-tokens', 0o755, true);

        $discovery = new SkinDiscovery($this->root);

        self::assertSame(['brand', 'default', 'ocean'], $discovery->availableSlugs());
        self::assertSame('project', $discovery->find('ocean')?->source);
        self::assertSame('framework', $discovery->find('default')?->source);
        self::assertSame('/assets/skins/brand/tokens.css', $discovery->find('brand')?->tokensUrl);
        self::assertNull($discovery->find('no-tokens'));
        self::assertNull($discovery->find('missing'));
    }

    public function test_scans_the_filesystem_once_per_instance(): void
    {
        $this->writeSkin(SkinDiscovery::FRAMEWORK_SKINS_DIR, 'default');
        $discovery = new SkinDiscovery($this->root);
        self::assertNotNull($discovery->find('default'));

        // Installed after the first scan: invisible to this (worker-lifetime)
        // instance, picked up by a fresh one — i.e. after a worker restart.
        $this->writeSkin(SkinDiscovery::PROJECT_SKINS_DIR, 'late');

        self::assertNull($discovery->find('late'));
        self::assertSame(['default'], $discovery->availableSlugs());
        self::assertNotNull((new SkinDiscovery($this->root))->find('late'));
    }

    private function writeSkin(string $sourceDir, string $slug): void
    {
        $dir = $this->root . $sourceDir . '/' . $slug;
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }
        file_put_contents($dir . '/tokens.css', ':root{}');
    }

    private function removeTree(string $path): void
    {
        if (! file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
