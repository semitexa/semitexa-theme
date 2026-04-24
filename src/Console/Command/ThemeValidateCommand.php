<?php

declare(strict_types=1);

namespace Semitexa\Theme\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Model\ThemeManifest;
use Semitexa\Theme\Runtime\ThemeResolverBootstrap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CI-safe validation of theme manifests.
 *
 * Loads every `theme.json` (packaged + project) via the manifest
 * repository, which runs full structural validation:
 *  - every manifest parses as JSON object with required fields
 *  - ids are unique
 *  - `extends` targets exist
 *  - no cycles in the extends chain
 *  - exactly one manifest declares `active_when.always = true`
 *  - non-root manifests declare at least one filter axis
 *  - every referenced skin slug exists in skins-base
 *
 * Exits 0 on clean, 1 on any `InvalidThemeConfigException`. Designed
 * for `composer.json post-install` or CI pipelines to fail fast before
 * a broken config reaches runtime.
 *
 * On success, lists the discovered theme set so humans reviewing CI
 * logs can spot-check what the validator saw.
 */
#[AsCommand(
    name: 'theme:validate',
    description: 'Validate every theme.json manifest and report problems — exit code 1 on any error.',
)]
final class ThemeValidateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit a JSON envelope instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $asJson = (bool) $input->getOption('json');

        try {
            $manifests = ThemeResolverBootstrap::repository()->load();
        } catch (InvalidThemeConfigException $e) {
            return $this->fail($output, $asJson, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail($output, $asJson, 'Unexpected validator error: ' . $e::class . ' — ' . $e->getMessage());
        }

        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.validate/v1',
                'verdict' => 'ok',
                'count' => count($manifests),
                'manifests' => array_map($this->manifestToArray(...), $manifests),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $output->writeln('<info>✓ All theme manifests valid</info>');
        $output->writeln('');
        $output->writeln('  ' . count($manifests) . ' manifest(s) discovered:');
        $output->writeln('');
        foreach ($manifests as $m) {
            $skinSummary = $m->skin->isConditional()
                ? 'conditional (default=' . $m->skin->default . ', ' . count($m->skin->conditions) . ' rule(s))'
                : 'fixed=' . $m->skin->default;
            $output->writeln(sprintf(
                '    <comment>%-16s</comment> extends=<info>%-12s</info> source=%-7s skin=%s',
                $m->id,
                $m->extends ?? '(root)',
                $m->source,
                $skinSummary,
            ));
        }
        return Command::SUCCESS;
    }

    private function fail(OutputInterface $output, bool $asJson, string $message): int
    {
        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.validate/v1',
                'verdict' => 'fail',
                'error' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $output->writeln('<error>✗ Theme config invalid</error>');
            $output->writeln('');
            $output->writeln('  ' . $message);
        }
        return Command::FAILURE;
    }

    /** @return array<string, mixed> */
    private function manifestToArray(ThemeManifest $m): array
    {
        return [
            'id' => $m->id,
            'extends' => $m->extends,
            'source' => $m->source,
            'path' => $m->path,
            'active_when' => [
                'always' => $m->activeWhen->always,
                'tenant' => $m->activeWhen->tenant,
                'domain' => $m->activeWhen->domain,
                'locale' => $m->activeWhen->locale,
            ],
            'skin' => $m->skin->isConditional()
                ? ['default' => $m->skin->default, 'conditions_count' => count($m->skin->conditions)]
                : ['fixed' => $m->skin->default],
        ];
    }
}
