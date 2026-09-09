<?php

declare(strict_types=1);

/**
 * Regression guard for the canonical skin token format.
 *
 * For every skin under `src/skins/<slug>/`:
 *
 *   1. `skin.json` exists and is at schema_version 3.0 (validated by
 *      SkinManifest::fromJson — non-3.0 throws explicitly).
 *   2. Both `tokens.light` and `tokens.dark` cover the full TokenContract
 *      surface (validated by DualSkinPalette construction).
 *   3. `tokens.css` matches a fresh TokenEmitter rebuild byte-for-byte.
 *      This is the hard guarantee that skin.json is the source of truth and
 *      tokens.css is a derived artifact — no hand edits possible.
 *   4. `tokens.css` carries the canonical structural markers in canonical
 *      order: header comment, `color-scheme: light dark`, all 7 section
 *      dividers in order, both data-skin-mode pinning rules.
 *   5. `tokens.css` does not reintroduce the forbidden legacy theme markers
 *      retired in `ep-skin-modes-and-site-adoption`.
 *
 * Usage:
 *   bin/semitexa lint:skin-tokens              — pretty output
 *   bin/semitexa lint:skin-tokens --json       — JSON envelope
 *
 * Exit code: 0 = clean, 1 = at least one skin failed.
 *
 * This script ships inside semitexa/theme, so it cannot assume it sits at
 * <projectRoot>/scripts/ and reach the project with `__DIR__ . '/..'`. Depth
 * from here to the project root differs between a vendor install, a monorepo
 * package and a project-local copy, so the caller passes SEMITEXA_PROJECT_ROOT
 * and the fallback walks up looking for vendor/autoload.php rather than
 * counting directories.
 */

$projectRoot = getenv('SEMITEXA_PROJECT_ROOT');
if (!is_string($projectRoot) || $projectRoot === '' || !is_dir($projectRoot)) {
    $projectRoot = '';
    $dir = __DIR__;
    while (true) {
        if (is_file($dir . '/vendor/autoload.php')) {
            $projectRoot = $dir;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}

$autoload = $projectRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Cannot locate vendor/autoload.php from project root {$projectRoot}.\n");
    fwrite(STDERR, "Set SEMITEXA_PROJECT_ROOT, or run this through: bin/semitexa lint:skin-tokens\n");
    exit(1);
}

require $autoload;

use Semitexa\Theme\Application\Service\Skin\SkinManifest;
use Semitexa\Theme\Application\Service\Skin\TokenEmitter;
use Semitexa\Theme\Application\Service\Skin\TokenSchema;

$asJson = in_array('--json', $argv, true);
$skinsRoot = $projectRoot . '/src/skins';

if (!is_dir($skinsRoot)) {
    fwrite(STDERR, "No src/skins/ directory found at {$skinsRoot}.\n");
    exit(0);
}

$forbiddenMarkers = [
    'theme_prefix',
    'data-demo-theme',
    'data-os-theme',
    'data-platform-theme',
    'semitexa_demo_theme',
    'semitexa_os_site_theme',
    'semitexa_platform_site_theme',
];

$entries = scandir($skinsRoot) ?: [];
$slugs = [];
foreach ($entries as $name) {
    if ($name === '.' || $name === '..') {
        continue;
    }
    if (is_dir("$skinsRoot/$name")) {
        $slugs[] = $name;
    }
}
sort($slugs);

$results = [];
$failures = 0;
$emitter = new TokenEmitter();

foreach ($slugs as $slug) {
    $skinDir = "$skinsRoot/$slug";
    $manifestPath = "$skinDir/skin.json";
    $cssPath = "$skinDir/tokens.css";
    $issues = [];

    if (!is_file($manifestPath)) {
        $issues[] = 'missing skin.json';
        $results[$slug] = ['status' => 'fail', 'issues' => $issues];
        $failures++;
        continue;
    }
    if (!is_file($cssPath)) {
        $issues[] = 'missing tokens.css';
        $results[$slug] = ['status' => 'fail', 'issues' => $issues];
        $failures++;
        continue;
    }

    try {
        $manifest = SkinManifest::fromJson((string) file_get_contents($manifestPath));
    } catch (\InvalidArgumentException $e) {
        $issues[] = 'skin.json invalid: ' . $e->getMessage();
        $results[$slug] = ['status' => 'fail', 'issues' => $issues];
        $failures++;
        continue;
    }

    // (3) deterministic re-emit check.
    $expected = $emitter->emit($manifest->tokens, $manifest->emitterContext());
    $actual = (string) file_get_contents($cssPath);
    if ($actual !== $expected) {
        $issues[] = 'tokens.css does not match canonical emit — re-run `bin/semitexa skins:rebuild ' . $slug . ' --write`';
    }

    // (4) structural markers in canonical order.
    if (!str_contains($actual, "color-scheme: light dark;")) {
        $issues[] = 'missing `color-scheme: light dark` in :root';
    }
    if (!str_contains($actual, ':root[data-skin-mode="light"]')) {
        $issues[] = 'missing light mode-pinning rule (`:root[data-skin-mode="light"]`)';
    }
    if (!str_contains($actual, ':root[data-skin-mode="dark"]')) {
        $issues[] = 'missing dark mode-pinning rule (`:root[data-skin-mode="dark"]`)';
    }

    $sections = TokenSchema::sections();
    $lastPos = -1;
    foreach ($sections as $section) {
        $marker = "/* {$section} —";
        $pos = strpos($actual, $marker);
        if ($pos === false) {
            $issues[] = "missing canonical section marker `{$marker}`";
            continue;
        }
        if ($pos <= $lastPos) {
            $issues[] = "section `{$section}` appears out of canonical order";
        }
        $lastPos = $pos;
    }

    // (5) forbidden legacy markers.
    foreach ($forbiddenMarkers as $marker) {
        if (str_contains($actual, $marker)) {
            $issues[] = "forbidden legacy marker present: `{$marker}`";
        }
    }

    // Canonical artifact rule: explicit "do not edit manually" hint must remain.
    if (!str_contains($actual, 'do not edit manually')) {
        $issues[] = 'tokens.css missing the "do not edit manually" header line';
    }

    $results[$slug] = [
        'status' => $issues === [] ? 'ok' : 'fail',
        'manifest' => $manifest->algorithm,
        'source' => $manifest->source,
        'issues' => $issues,
    ];
    if ($issues !== []) {
        $failures++;
    }
}

if ($asJson) {
    echo json_encode([
        'artifact' => 'semitexa.lint.skin-tokens/v1',
        'status' => $failures === 0 ? 'clean' : 'fail',
        'failures' => $failures,
        'skins' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit($failures === 0 ? 0 : 1);
}

$reset = "\033[0m";
$green = "\033[32m";
$red   = "\033[31m";
$cyan  = "\033[36m";

if ($slugs === []) {
    echo "No skins under src/skins/ — nothing to check.\n";
    exit(0);
}

echo "{$cyan}lint:skin-tokens{$reset} — checking " . count($slugs) . " skin(s):\n\n";

foreach ($results as $slug => $r) {
    $tag = $r['status'] === 'ok'
        ? "{$green}OK  {$reset}"
        : "{$red}FAIL{$reset}";
    $meta = isset($r['manifest']) ? "  ({$r['source']}/{$r['manifest']})" : '';
    echo "  {$tag}  {$slug}{$meta}\n";
    foreach ($r['issues'] as $issue) {
        echo "        - {$issue}\n";
    }
}

echo "\n";
if ($failures === 0) {
    echo "{$green}All canonical token checks passed.{$reset}\n";
    exit(0);
}
echo "{$red}{$failures} skin(s) failed canonical token checks.{$reset}\n";
exit(1);
