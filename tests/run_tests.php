<?php
/**
 * run_tests.php — Kea Buddy test runner
 *
 * Executes all test suites and prints a combined summary.
 *
 * Usage (from the project root):
 *   php tests/run_tests.php
 *
 * Exit code:
 *   0  — all tests passed
 *   1  — one or more tests failed
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Test runner must be executed from the command line.\n");
}

// ── ANSI helpers ─────────────────────────────────────────────────
function bold(string $s): string  { return "\033[1m{$s}\033[0m"; }
function green(string $s): string { return "\033[32m{$s}\033[0m"; }
function red(string $s): string   { return "\033[31m{$s}\033[0m"; }
function cyan(string $s): string  { return "\033[36m{$s}\033[0m"; }

// ── Test suites to run ────────────────────────────────────────────
$suites = [
    'AuthTest.php'     => 'Authentication Tests',
    'CalendarTest.php' => 'Calendar Notes Tests',
];

echo "\n" . bold('═══════════════════════════════════════════') . "\n";
echo bold('  Kea Buddy — Test Suite') . "\n";
echo bold('═══════════════════════════════════════════') . "\n";

$totalPassed = 0;
$totalFailed = 0;
$suiteResults = [];

foreach ($suites as $file => $label) {
    $path = __DIR__ . '/' . $file;

    if (!file_exists($path)) {
        echo "\n" . red("  ✗ MISSING: {$file}") . "\n";
        $totalFailed++;
        $suiteResults[$label] = ['status' => 'missing', 'passed' => 0, 'failed' => 1];
        continue;
    }

    echo "\n" . cyan("─── {$label} ───") . "\n";

    // Reset per-suite counters (the test files write into these globals)
    $testsPassed = 0;
    $testsFailed = 0;
    $failures    = [];

    // Run the suite in this process so it shares the DB connection from db.php
    require $path;

    $totalPassed += $testsPassed;
    $totalFailed += $testsFailed;

    $suiteResults[$label] = [
        'status' => $testsFailed === 0 ? 'pass' : 'fail',
        'passed' => $testsPassed,
        'failed' => $testsFailed,
    ];

    // Reset for next suite
    $testsPassed = 0;
    $testsFailed = 0;
    $failures    = [];
}

// ── Combined summary ──────────────────────────────────────────────
echo "\n" . bold('═══════════════════════════════════════════') . "\n";
echo bold('  Suite Results') . "\n";
echo bold('═══════════════════════════════════════════') . "\n";

foreach ($suiteResults as $label => $r) {
    $icon = $r['status'] === 'pass' ? green('✓') : red('✗');
    printf("  %s  %-30s %2d passed, %2d failed\n",
        $icon, $label, $r['passed'], $r['failed']
    );
}

echo bold('───────────────────────────────────────────') . "\n";
$total = $totalPassed + $totalFailed;
printf("  Total: %d/%d tests passed\n", $totalPassed, $total);

if ($totalFailed === 0) {
    echo "\n  " . green('All tests passed! ✓') . "\n\n";
    exit(0);
} else {
    echo "\n  " . red("{$totalFailed} test(s) failed.") . "\n\n";
    exit(1);
}
