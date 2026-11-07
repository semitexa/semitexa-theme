<?php

declare(strict_types=1);

namespace Semitexa\Theme\Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A page on a base layout looked broken on a fresh project (2026-11-07): the
 * resolver chose a skin and nothing linked it, so every control rendered with no
 * border and no colour, and the text sat against the window's edge.
 */
final class BaseLayoutHeadTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/../../../src/Application/View/templates';

    /** @return iterable<string, array{string}> */
    public static function layouts(): iterable
    {
        foreach (['one-column', 'two-columns-left', 'two-columns-right', 'three-columns'] as $layout) {
            yield $layout => [$layout];
        }
    }

    #[DataProvider('layouts')]
    public function test_a_base_layout_brings_its_head_before_the_page_assets(string $layout): void
    {
        $twig = (string) file_get_contents(self::TEMPLATES . "/layouts/{$layout}.html.twig");

        $head = strpos($twig, 'partials/head/base-head.html.twig');
        self::assertNotFalse($head);
        self::assertLessThan(strpos($twig, 'asset_head()'), $head, 'what a page adds comes later and wins');
    }

    public function test_the_head_links_the_skin_and_the_layout_css(): void
    {
        $twig = (string) file_get_contents(self::TEMPLATES . '/partials/head/base-head.html.twig');

        self::assertStringContainsString('theme_skin_css()', $twig);
        self::assertStringContainsString("asset('css/layouts.css', 'theme-base')", $twig);
    }

    public function test_the_layout_css_is_published(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../../src/Application/Static/assets.json'), true);

        self::assertIsArray($manifest);
        self::assertContains('layouts.css', $manifest['include']['css']);
    }
}
