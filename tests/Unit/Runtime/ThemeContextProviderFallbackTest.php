<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Runtime;

use PHPUnit\Framework\TestCase;
use Semitexa\Core\Log\LoggerInterface;
use Semitexa\Core\Log\StaticLoggerBridge;
use Semitexa\Theme\Application\Service\Runtime\ThemeContextProvider;
use Semitexa\Theme\Application\Service\Runtime\ThemeContextStore;
use Semitexa\Theme\Domain\Contract\ThemeManifestRepositoryInterface;
use Semitexa\Theme\Domain\Model\ThemeAssignment;
use Semitexa\Theme\Domain\Model\ThemeManifest;

/**
 * The provider degrades when a manifest chain cannot be built, and that degrade
 * has to be audible.
 *
 * It was not. The catch carried a comment saying the resolver logged it on the
 * next assignment build; the package contained no logging of any kind, so a
 * theme could disappear under a live worker and leave no trace anywhere. These
 * tests pin both halves: the request still gets served, and the log says why.
 */
final class ThemeContextProviderFallbackTest extends TestCase
{
    protected function tearDown(): void
    {
        // ThemeContextStore and StaticLoggerBridge are both process-wide. Leaving
        // either set would leak into whatever test runs next in this worker.
        ThemeContextStore::reset();
        StaticLoggerBridge::reset();
        parent::tearDown();
    }

    private function assignment(string $theme): ThemeAssignment
    {
        return new ThemeAssignment($theme, 'default', 'ns', '/tokens.css', '/assets/', 0, 'default');
    }

    private function repository(?\Throwable $throw, array $chain = []): ThemeManifestRepositoryInterface
    {
        return new class ($throw, $chain) implements ThemeManifestRepositoryInterface {
            public function __construct(private ?\Throwable $throw, private array $chain) {}

            public function load(): array { return []; }
            public function findRoot(): ThemeManifest { throw new \RuntimeException('not used'); }
            public function findById(string $id): ?ThemeManifest { return null; }

            public function chainOf(string $id): array
            {
                if ($this->throw !== null) {
                    throw $this->throw;
                }
                return $this->chain;
            }
        };
    }

    private function capturingLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            /** @var list<array{0: string, 1: array<string, mixed>}> */
            public array $warnings = [];

            public function error(string $message, array $context = []): void {}
            public function critical(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void
            {
                $this->warnings[] = [$message, $context];
            }
            public function info(string $message, array $context = []): void {}
            public function notice(string $message, array $context = []): void {}
            public function debug(string $message, array $context = []): void {}
        };
    }

    public function test_a_broken_chain_serves_the_assigned_theme_alone_and_says_so(): void
    {
        ThemeContextStore::set($this->assignment('theme-sky'));
        $logger = $this->capturingLogger();
        StaticLoggerBridge::set($logger);

        $provider = new ThemeContextProvider(
            $this->repository(new \RuntimeException('theme.json vanished')),
        );

        // The request is still served — a manifest moving under a live worker
        // must not turn into a 500.
        $this->assertSame(['theme-sky'], $provider->activeChain());

        $this->assertCount(1, $logger->warnings);
        [$message, $context] = $logger->warnings[0];
        $this->assertStringContainsString('Theme chain unavailable', $message);
        $this->assertSame('theme-sky', $context['theme']);
        $this->assertStringContainsString('theme.json vanished', $context['message']);
    }

    public function test_no_assignment_is_not_a_degrade_and_stays_quiet(): void
    {
        ThemeContextStore::reset();
        $logger = $this->capturingLogger();
        StaticLoggerBridge::set($logger);

        $provider = new ThemeContextProvider($this->repository(null));

        // Empty chain here is the documented handoff to the env-THEME default
        // for CLI and bootstrap, not a failure — logging it would cry wolf on
        // every console command.
        $this->assertSame([], $provider->activeChain());
        $this->assertSame([], $logger->warnings);
    }
}
