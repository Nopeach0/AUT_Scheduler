<?php
/**
 * SchedulePrivacyTest.php — Unit tests for schedule visibility business logic.
 *
 * User Story:
 *   "As a user, I want to be able to adjust who gets to see my schedule
 *    on my profile so only my friends can see my calendar."
 *
 * TDD Cycle (RED → GREEN):
 *   RED   — run this file BEFORE creating includes/functions.php.
 *            Two tests fail immediately because the functions do not exist.
 *   GREEN — create includes/functions.php with isValidVisibility() and
 *            canViewSchedule(), then re-run to see all tests pass.
 *
 * These are pure unit tests. No database connection is required.
 * The canViewSchedule() function uses dependency injection (a callable)
 * so the friendship check can be swapped for a mock during testing.
 *
 * Run from the project root:
 *   php tests/SchedulePrivacyTest.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Tests must be run from the command line.\n");
}

// Load the implementation under test (does NOT exist yet on the first run)
if (file_exists(__DIR__ . '/../includes/functions.php')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// ── Assertion helper ──────────────────────────────────────────────────────
// Guard against redeclaration when included by run_tests.php
if (!isset($testsPassed)) { $testsPassed = 0; }
if (!isset($testsFailed)) { $testsFailed = 0; }
if (!isset($failures))    { $failures    = []; }

if (!function_exists('test')) {
    function test(string $name, bool $condition, string $hint = ''): void {
        global $testsPassed, $testsFailed, $failures;
        if ($condition) {
            echo "\033[32m✓ PASS\033[0m  {$name}\n";
            $testsPassed++;
        } else {
            $suffix = $hint ? " ({$hint})" : '';
            echo "\033[31m✗ FAIL\033[0m  {$name}{$suffix}\n";
            $testsFailed++;
            $failures[] = $name;
        }
    }
}

echo "─── Schedule Privacy Tests ───\n\n";

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — isValidVisibility()
//
// Written FIRST. Fails until includes/functions.php is created (RED phase).
// ═══════════════════════════════════════════════════════════════════════════

test(
    'isValidVisibility() function is defined in includes/functions.php',
    function_exists('isValidVisibility'),
    'Create includes/functions.php with isValidVisibility(string $value): bool'
);

if (function_exists('isValidVisibility')) {
    test('isValidVisibility: "public" is a valid option',           isValidVisibility('public'));
    test('isValidVisibility: "friends" is a valid option',          isValidVisibility('friends'));
    test('isValidVisibility: "private" is a valid option',          isValidVisibility('private'));
    test('isValidVisibility: "everyone" is not valid (rejected)',   !isValidVisibility('everyone'));
    test('isValidVisibility: empty string is not valid (rejected)', !isValidVisibility(''));
    test('isValidVisibility: "Public" (wrong case) is rejected',    !isValidVisibility('Public'));
} else {
    echo "\033[33m  ⚠  Skipping isValidVisibility() tests.\033[0m\n";
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — canViewSchedule()
//
// Also fails until functions.php exists.
// Uses callable injection so no real database is needed.
// ═══════════════════════════════════════════════════════════════════════════

test(
    'canViewSchedule() function is defined in includes/functions.php',
    function_exists('canViewSchedule'),
    'Add canViewSchedule(string $visibility, ?int $viewerId, int $ownerId, callable $checkFriendship): bool'
);

if (!function_exists('canViewSchedule')) {
    echo "\033[33m  ⚠  Skipping canViewSchedule() tests — implement includes/functions.php first.\033[0m\n\n";
} else {
    // Mock callables replace the real DB friendship query
    $alwaysFriends = fn(?int $v, int $o) => true;   // simulates accepted friendship
    $neverFriends  = fn(?int $v, int $o) => false;  // simulates no friendship

    $owner   = 1;   // arbitrary owner ID
    $friend  = 2;   // arbitrary viewer ID (friend)
    $stranger = 3;  // arbitrary viewer ID (not a friend)

    // ── PUBLIC schedule ─────────────────────────────────────────────────
    test('Public: owner can always view own schedule',
        canViewSchedule('public', $owner, $owner, $neverFriends));
    test('Public: logged-in non-friend can view',
        canViewSchedule('public', $stranger, $owner, $neverFriends));
    test('Public: unauthenticated user (null viewerId) can view',
        canViewSchedule('public', null, $owner, $neverFriends));

    // ── PRIVATE schedule ────────────────────────────────────────────────
    test('Private: owner can view own schedule',
        canViewSchedule('private', $owner, $owner, $neverFriends));
    test('Private: a logged-in non-friend is denied',
        !canViewSchedule('private', $stranger, $owner, $neverFriends));
    test('Private: even an accepted friend is denied',
        !canViewSchedule('private', $friend, $owner, $alwaysFriends));
    test('Private: unauthenticated user is denied',
        !canViewSchedule('private', null, $owner, $neverFriends));

    // ── FRIENDS-ONLY schedule ───────────────────────────────────────────
    test('Friends-only: owner can view own schedule',
        canViewSchedule('friends', $owner, $owner, $neverFriends));
    test('Friends-only: accepted friend can view',
        canViewSchedule('friends', $friend, $owner, $alwaysFriends));
    test('Friends-only: non-friend is denied',
        !canViewSchedule('friends', $stranger, $owner, $neverFriends));
    test('Friends-only: unauthenticated user is denied',
        !canViewSchedule('friends', null, $owner, $alwaysFriends));
}

// ── Summary ───────────────────────────────────────────────────────────────
echo "\n";
if ($testsFailed === 0) {
    echo "\033[32mAll {$testsPassed} schedule privacy tests passed.\033[0m\n";
} else {
    echo "\033[31m{$testsFailed} test(s) failed: " . implode(', ', $failures) . "\033[0m\n";
}
