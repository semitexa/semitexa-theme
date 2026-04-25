<?php

declare(strict_types=1);

namespace Semitexa\Theme\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Support\ProjectRoot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffold a project-local theme that extends the framework-canonical
 * `theme-base`. Every fresh semitexa project should run this once so it has a
 * writable override surface from day one:
 *
 *   src/theme/{slug}/
 *   ├── theme.json            (id=<slug>, extends=theme-base, active_when=<domain>)
 *   └── theme-base/
 *       └── templates/
 *           ├── layouts/      (empty — drop overrides of theme-base layouts here)
 *           ├── pages/        (empty — drop overrides of theme-base pages here)
 *           └── partials/     (empty — drop overrides of theme-base partials here)
 *
 * Idempotent: re-running without `--force` skips an existing manifest.
 */
#[AsCommand(
    name: 'theme:scaffold',
    description: 'Scaffold a project-local theme at src/theme/{slug}/ extending theme-base.',
)]
final class ThemeScaffoldCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Theme id (default: sanitized project dir basename)')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain condition for active_when (default: {slug}.test)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing theme.json')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit a JSON envelope instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asJson = (bool) $input->getOption('json');
        $force = (bool) $input->getOption('force');
        $projectRoot = ProjectRoot::get();

        $slug = $input->getOption('slug');
        if (! is_string($slug) || $slug === '') {
            $slug = $this->slugify(basename($projectRoot));
        } else {
            $slug = $this->slugify($slug);
        }
        if ($slug === '' || $slug === 'theme-base') {
            return $this->fail($output, $asJson, "Invalid slug '{$slug}' — must be non-empty and not 'theme-base'.");
        }
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return $this->fail($output, $asJson, "Invalid slug '{$slug}' — expected lowercase letters, numbers, and hyphens only.");
        }

        $domain = $input->getOption('domain');
        if (! is_string($domain) || $domain === '') {
            $domain = $slug . '.test';
        }

        $themeDir = $projectRoot . '/src/theme/' . $slug;
        $manifestPath = $themeDir . '/theme.json';
        $existed = is_file($manifestPath);

        if ($existed && ! $force) {
            return $this->noop($output, $asJson, $slug, $domain, $themeDir, $manifestPath);
        }

        foreach ([
            $themeDir . '/theme-base/templates/layouts',
            $themeDir . '/theme-base/templates/pages',
            $themeDir . '/theme-base/templates/partials',
        ] as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0o775, true) && ! is_dir($dir)) {
                return $this->fail($output, $asJson, "Failed to create directory: {$dir}");
            }
        }

        $manifest = [
            'id' => $slug,
            'extends' => 'theme-base',
            'active_when' => ['domain' => $domain],
            'skin' => ['default' => 'default'],
        ];
        try {
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->fail($output, $asJson, 'Failed to encode manifest JSON: ' . $e->getMessage());
        }
        if (file_put_contents($manifestPath, $json . "\n") === false) {
            return $this->fail($output, $asJson, "Failed to write manifest: {$manifestPath}");
        }

        $status = $existed ? 'overwritten' : 'created';
        return $this->ok($output, $asJson, $slug, $domain, $themeDir, $manifestPath, $status);
    }

    private function ok(
        OutputInterface $output,
        bool $asJson,
        string $slug,
        string $domain,
        string $themeDir,
        string $manifestPath,
        string $status,
    ): int {
        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.scaffold/v1',
                'verdict' => 'ok',
                'status' => $status,
                'slug' => $slug,
                'extends' => 'theme-base',
                'domain' => $domain,
                'theme_dir' => $themeDir,
                'manifest_path' => $manifestPath,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $output->writeln("<info>✓ Theme {$status}: {$slug}</info>");
        $output->writeln('');
        $output->writeln("  Dir:      {$themeDir}");
        $output->writeln("  Extends:  theme-base");
        $output->writeln("  Domain:   {$domain}");
        $output->writeln('');
        $output->writeln('  Customize <comment>active_when</comment> in theme.json for your environments, then:');
        $output->writeln('    <info>bin/semitexa theme:validate</info>');
        return Command::SUCCESS;
    }

    private function noop(
        OutputInterface $output,
        bool $asJson,
        string $slug,
        string $domain,
        string $themeDir,
        string $manifestPath,
    ): int {
        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.scaffold/v1',
                'verdict' => 'noop',
                'status' => 'skipped',
                'slug' => $slug,
                'domain' => $domain,
                'theme_dir' => $themeDir,
                'manifest_path' => $manifestPath,
                'note' => 'Already exists — pass --force to overwrite.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }
        $output->writeln("<comment>⤸ Theme already exists: {$slug}</comment>");
        $output->writeln('');
        $output->writeln("  Manifest: {$manifestPath}");
        $output->writeln('  Pass <info>--force</info> to overwrite.');
        return Command::SUCCESS;
    }

    private function fail(OutputInterface $output, bool $asJson, string $message): int
    {
        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.scaffold/v1',
                'verdict' => 'fail',
                'error' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $output->writeln("<error>✗ {$message}</error>");
        }
        return Command::FAILURE;
    }

    private function slugify(string $name): string
    {
        $s = strtolower($name);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
