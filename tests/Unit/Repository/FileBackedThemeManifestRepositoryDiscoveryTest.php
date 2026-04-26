<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Repository;

use PHPUnit\Framework\TestCase;
use Semitexa\Theme\Contract\SkinDiscoveryInterface;
use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Model\SkinEntry;
use Semitexa\Theme\Repository\FileBackedThemeManifestRepository;

/**
 * Discovery regression suite — guards the three layouts the repository must
 * support and the symlink-dedupe invariant that lets all three coexist.
 *
 * Production-deployed layout: composer-installed under vendor/.
 * Monorepo dev layout:        path-repo under packages/ (often symlinked into
 *                             vendor/ by Composer's `symlink: true`).
 * Project-local theme:        src/theme/<id>/theme.json owned by the project.
 */
final class FileBackedThemeManifestRepositoryDiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/semitexa-theme-discovery-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_discovers_theme_base_from_vendor_only_layout_like_a_composer_deploy(): void
    {
        $this->writeManifest('vendor/semitexa/theme/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));
        $manifests = $repo->load();

        self::assertCount(1, $manifests);
        self::assertSame('theme-base', $manifests[0]->id);
        self::assertSame('vendor', $manifests[0]->source);
    }

    public function test_discovers_theme_base_from_packages_only_layout_like_a_monorepo_dev_checkout(): void
    {
        $this->writeManifest('packages/semitexa-theme/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));
        $manifests = $repo->load();

        self::assertCount(1, $manifests);
        self::assertSame('theme-base', $manifests[0]->id);
        self::assertSame('package', $manifests[0]->source);
    }

    public function test_dedupes_when_vendor_is_symlinked_to_packages_so_path_repo_dev_does_not_explode(): void
    {
        $this->writeManifest('packages/semitexa-theme/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);
        mkdir($this->root . '/vendor/semitexa', 0o755, true);
        if (! @symlink('../../packages/semitexa-theme', $this->root . '/vendor/semitexa/theme')) {
            self::markTestSkipped('symlink() failed in this environment — skipping path-repo dedupe test');
        }

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));
        $manifests = $repo->load(); // would throw "Duplicate theme id" without realpath dedup

        self::assertCount(1, $manifests);
        self::assertSame('theme-base', $manifests[0]->id);
        self::assertSame('vendor', $manifests[0]->source);
    }

    public function test_dedupes_when_vendor_contains_a_mirrored_copy_of_the_same_path_repo_package(): void
    {
        $manifest = [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ];
        $this->writeManifest('packages/semitexa-theme/theme.json', $manifest);
        $this->writeManifest('vendor/semitexa/theme/theme.json', $manifest);

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));
        $manifests = $repo->load();

        self::assertCount(1, $manifests);
        self::assertSame('theme-base', $manifests[0]->id);
        self::assertSame('vendor', $manifests[0]->source);
    }

    public function test_combines_vendor_theme_base_with_project_local_theme_extending_it(): void
    {
        $this->writeManifest('vendor/semitexa/theme/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);
        $this->writeManifest('src/theme/acme-site/theme.json', [
            'id' => 'acme-site',
            'extends' => 'theme-base',
            'active_when' => ['domain' => 'acme.test'],
            'skin' => ['default' => 'default'],
        ]);

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));
        $manifests = $repo->load();

        $bySource = [];
        foreach ($manifests as $m) {
            $bySource[$m->source] = $m->id;
        }
        self::assertSame(['vendor' => 'theme-base', 'project' => 'acme-site'], $bySource);
    }

    public function test_load_throws_helpful_error_when_no_manifest_exists_anywhere(): void
    {
        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins([]));

        $this->expectException(InvalidThemeConfigException::class);
        $this->expectExceptionMessage('No theme manifests discovered');

        $repo->load();
    }

    public function test_duplicate_theme_id_in_two_distinct_files_is_still_fatal(): void
    {
        // Two separate physical files with the same id must still error — the
        // dedupe is by realpath, not by id. The id-collision check stays the
        // authoritative gate against accidental overrides.
        $this->writeManifest('vendor/semitexa/theme/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);
        $this->writeManifest('src/theme/theme-base/theme.json', [
            'id' => 'theme-base',
            'active_when' => ['always' => true],
            'skin' => ['default' => 'default'],
        ]);

        $repo = new FileBackedThemeManifestRepository($this->root, $this->fakeSkins(['default']));

        $this->expectException(InvalidThemeConfigException::class);
        $this->expectExceptionMessage("Duplicate theme id 'theme-base'");

        $repo->load();
    }

    /** @param array<string, mixed> $body */
    private function writeManifest(string $relativePath, array $body): void
    {
        $abs = $this->root . '/' . $relativePath;
        $dir = dirname($abs);
        if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            self::fail("Failed to create fixture dir: {$dir}");
        }
        file_put_contents(
            $abs,
            (string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /** @param list<string> $slugs */
    private function fakeSkins(array $slugs): SkinDiscoveryInterface
    {
        $entries = [];
        foreach ($slugs as $slug) {
            $entries[] = new SkinEntry(
                slug: $slug,
                tokensUrl: '/assets/skins/' . $slug . '/tokens.css',
                tokensFilePath: $this->root . '/fake/' . $slug . '/tokens.css',
                source: 'framework',
            );
        }
        return new class($entries) implements SkinDiscoveryInterface {
            /** @param list<SkinEntry> $entries */
            public function __construct(private readonly array $entries) {}
            public function availableSkins(): array { return $this->entries; }
            public function find(string $slug): ?SkinEntry
            {
                foreach ($this->entries as $e) {
                    if ($e->slug === $slug) { return $e; }
                }
                return null;
            }
            public function availableSlugs(): array
            {
                return array_map(static fn (SkinEntry $e) => $e->slug, $this->entries);
            }
        };
    }

    private function removeTree(string $path): void
    {
        if (! file_exists($path) && ! is_link($path)) {
            return;
        }
        if (is_link($path) || ! is_dir($path)) {
            @unlink($path);
            return;
        }
        $entries = scandir($path) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $entry);
        }
        @rmdir($path);
    }
}
