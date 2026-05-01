<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Console\Command;

use Semitexa\Core\Attribute\AsCommand;
use Semitexa\Core\Support\ProjectRoot;
use Semitexa\Theme\Discovery\SkinDiscovery;
use Semitexa\Theme\Discovery\ThemeDiscovery;
use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Exception\ThemeResolutionException;
use Semitexa\Theme\Domain\Model\ThemeAssignment;
use Semitexa\Theme\Domain\Model\ThemeContext;
use Semitexa\Theme\Application\Service\Runtime\ThemeResolverBootstrap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only diagnostic: runs the resolver against a synthetic request
 * context and prints the resulting ThemeAssignment + config inventory.
 *
 * Why this exists (not just a CLI convenience):
 *   - Answers "why is tenant X seeing skin Y?" deterministically without
 *     HTTP, SSR, or a running server. Useful in CI and for production ops.
 *   - Validation aid for the 7-level fallback chain: exercise each level
 *     with explicit flags and assert matched_rule_idx.
 *   - Safe: pure read — does not mutate var/config/themes.json, the
 *     ThemeContextStore, or any asset. No side effects.
 *
 * Flags:
 *   --tenant=<id>   tenant id for the synthetic context (omit = no tenant)
 *   --domain=<host> domain for the synthetic context (required)
 *   --locale=<xx>   locale for the synthetic context (defaults to 'en')
 *   --json          emit a JSON envelope instead of the human-readable form
 *
 * Exit codes:
 *   0 — resolution succeeded, output produced
 *   1 — config invalid or resolver error (message printed to stderr-style
 *       channel; JSON form still emits an envelope with "error" populated)
 */
#[AsCommand(
    name: 'theme:resolve',
    description: 'Resolve theme + skin for a (tenant, domain, locale) context — read-only diagnostic.',
)]
final class ThemeResolveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant id (omit for no-tenant context)')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Request domain (host header)')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale code', 'en')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Emit a JSON envelope instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = $this->nullIfBlank($input->getOption('tenant'));
        $domain = $this->nullIfBlank($input->getOption('domain'));
        $locale = $this->nullIfBlank($input->getOption('locale')) ?? 'en';
        $asJson = (bool) $input->getOption('json');

        if ($domain === null) {
            return $this->fail($output, $asJson, 'The --domain option is required.', ['tenant' => $tenant, 'domain' => null, 'locale' => $locale]);
        }

        $ctx = new ThemeContext($tenant, strtolower($domain), $locale);

        $root = ProjectRoot::get();
        $themeDiscovery = new ThemeDiscovery($root);
        $skinDiscovery = new SkinDiscovery($root);

        try {
            $assignment = ThemeResolverBootstrap::resolver()->resolve($ctx);
        } catch (InvalidThemeConfigException $e) {
            return $this->fail($output, $asJson, 'Invalid theme config: ' . $e->getMessage(), [
                'tenant' => $tenant, 'domain' => $domain, 'locale' => $locale,
            ]);
        } catch (ThemeResolutionException $e) {
            return $this->fail($output, $asJson, 'Resolver error: ' . $e->getMessage(), [
                'tenant' => $tenant, 'domain' => $domain, 'locale' => $locale,
            ]);
        }

        $inventory = [
            'config_source' => $root . '/var/config/themes.json',
            'available_themes' => $themeDiscovery->availableThemes(),
            'available_skins' => $skinDiscovery->availableSlugs(),
        ];

        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.resolve/v1',
                'context' => ['tenant' => $tenant, 'domain' => $ctx->domain, 'locale' => $locale],
                'assignment' => $this->assignmentToArray($assignment),
                'inventory' => $inventory,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $this->writeHuman($output, $ctx, $assignment, $inventory);
        return Command::SUCCESS;
    }

    /** @param array<string, mixed> $ctx */
    private function fail(OutputInterface $output, bool $asJson, string $message, array $ctx): int
    {
        if ($asJson) {
            $output->writeln((string) json_encode([
                'artifact' => 'semitexa.theme.resolve/v1',
                'context' => $ctx,
                'error' => $message,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $output->writeln("<error>{$message}</error>");
        }
        return Command::FAILURE;
    }

    /** @param array{config_source: string, available_themes: list<string>, available_skins: list<string>} $inventory */
    private function writeHuman(OutputInterface $output, ThemeContext $ctx, ThemeAssignment $a, array $inventory): void
    {
        $output->writeln('<info>Theme resolution</info>');
        $output->writeln('');
        $output->writeln('  <comment>Context</comment>');
        $output->writeln('    tenant   ' . ($ctx->tenant ?? '<fg=gray>(none)</>'));
        $output->writeln('    domain   ' . $ctx->domain);
        $output->writeln('    locale   ' . $ctx->locale);
        $output->writeln('');
        $output->writeln('  <comment>Assignment</comment>');
        $output->writeln('    theme              ' . $a->theme);
        $output->writeln('    skin               ' . $a->skin);
        $output->writeln('    layout namespace   ' . $a->layoutNamespace);
        $output->writeln('    skin CSS URL       ' . $a->skinCssUrl);
        $output->writeln('    asset base path    ' . $a->assetBasePath);
        $output->writeln('    matched rule       level ' . $a->matchedRuleIdx . ' (' . $a->matchedRuleLabel . ')');
        $output->writeln('');
        $output->writeln('  <comment>Inventory</comment>');
        $output->writeln('    config             ' . $inventory['config_source']);
        $output->writeln('    themes             ' . (implode(', ', $inventory['available_themes']) ?: '<fg=gray>(none)</>'));
        $output->writeln('    skins              ' . (implode(', ', $inventory['available_skins']) ?: '<fg=gray>(none)</>'));
    }

    /** @return array<string, mixed> */
    private function assignmentToArray(ThemeAssignment $a): array
    {
        return [
            'theme' => $a->theme,
            'skin' => $a->skin,
            'layout_namespace' => $a->layoutNamespace,
            'skin_css_url' => $a->skinCssUrl,
            'asset_base_path' => $a->assetBasePath,
            'matched_rule_idx' => $a->matchedRuleIdx,
            'matched_rule_label' => $a->matchedRuleLabel,
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }
}
