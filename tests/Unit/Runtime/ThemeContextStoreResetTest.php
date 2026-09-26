<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Semitexa\Core\Lifecycle\PerRequestStateRegistry;
use Semitexa\Theme\Application\Service\Runtime\ThemeContextStore;
use Semitexa\Theme\Domain\Model\ThemeAssignment;

final class ThemeContextStoreResetTest extends TestCase
{
    protected function tearDown(): void
    {
        ThemeContextStore::reset();
        parent::tearDown();
    }

    #[Test]
    public function the_end_of_a_unit_of_work_clears_the_assigned_theme(): void
    {
        // The queue worker runs jobs outside a coroutine and calls resetAll()
        // after each: a theme one tenant's job assigned must not reach the next.
        ThemeContextStore::set(new ThemeAssignment('acme', 'default', 'ns', '/tokens.css', '/assets/', 0, 'default'));

        PerRequestStateRegistry::resetAll();

        self::assertNull(ThemeContextStore::getOrNull());
    }
}
