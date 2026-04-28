<?php
/**
 * AuthTest.php — Unit tests for user authentication logic.
 *
 * Tests cover:
 *  1. Database connection
 *  2. Required tables exist
 *  3. Password hashing / verification
 *  4. Email validation
 *  5. User registration to DB
 *  6. Login lookup and credential check
 *  7. Wrong-password rejection
 *  8. Duplicate-email detection
 *
 * Run from the project root:
 *   php tests/AuthTest.php
 *
 * @requires PHP ≥ 7.4 with mysqli extension
 */

// Tests must only run from the CLI to protect the live database
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Tests must be run from the command line.\n");
}

require_once __DIR__ . '/../includes/db.php';

// ── Minimal assertion helpers ─────────────────────────────────────

/** @var int $testsPassed Global passed count used by run_tests.php */
$testsPassed = 0;
/** @var int $testsFailed Global failed count used by run_tests.php */
$testsFailed = 0;
/** @var string[] $failures Names of failed tests */
$failures = [];

/**
 * Assert a condition and record the result.
 *
 * @param string $name      Human-readable test name
 * @param bool   $condition TRUE = pass, FALSE = fail
 * @param string $hint      Optional extra context on failure
 */
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

// ── Test 1: Database connection ──────────────────────────────────
test(
    'Database connection established',
    isset($conn) && ($conn instanceof mysqli),
    'Check DB_HOST / DB_USER / DB_PASS in includes/config.php'
);

// ── Test 2: Required tables exist ────────────────────────────────
$usersResult = $conn->query("SHOW TABLES LIKE 'users'");
test('Table: users exists', $usersResult && $usersResult->num_rows > 0);

$notesResult = $conn->query("SHOW TABLES LIKE 'calendar_notes'");
test('Table: calendar_notes exists', $notesResult && $notesResult->num_rows > 0);

// ── Test 3: Password hashing / verification ───────────────────────
$hash = password_hash('MyP@ssw0rd!', PASSWORD_DEFAULT);
test('password_hash() produces a non-empty string', !empty($hash));
test('password_verify() accepts the correct password', password_verify('MyP@ssw0rd!', $hash));
test('password_verify() rejects a wrong password',   !password_verify('WrongPass!',  $hash));

// ── Test 4: Email validation ──────────────────────────────────────
test('Valid AUT email passes filter',    filter_var('jsmith@aut.ac.nz', FILTER_VALIDATE_EMAIL) !== false);
test('Invalid email string is rejected', filter_var('not-an-email',      FILTER_VALIDATE_EMAIL) === false);
test('Email without domain is rejected', filter_var('nodomainpart@',      FILTER_VALIDATE_EMAIL) === false);

// ── Test 5: Register a test user ──────────────────────────────────
$testEmail = 'auth_test_' . time() . '@test.kea';
$testName  = 'Auth Test User';
$testHash  = password_hash('SecurePass99!', PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $testName, $testEmail, $testHash);
$ok          = $stmt->execute();
$testUserId  = (int)$conn->insert_id;
$stmt->close();

test('User registered to DB successfully', $ok && $testUserId > 0);

// ── Test 6: Login lookup ──────────────────────────────────────────
$stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE email = ?');
$stmt->bind_param('s', $testEmail);
$stmt->execute();
$result = $stmt->get_result();
$found  = $result->fetch_assoc();
$stmt->close();

test('Login lookup: user found by email',     $found !== null);
test('Login lookup: ID matches inserted ID',  $found && (int)$found['id'] === $testUserId);
test('Login lookup: correct password verifies', $found && password_verify('SecurePass99!', $found['password_hash']));

// ── Test 7: Wrong password rejected ──────────────────────────────
test('Wrong password is rejected',
    $found && !password_verify('IncorrectPassword!', $found['password_hash'])
);

// ── Test 8: Duplicate email detection ────────────────────────────
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $testEmail);
$stmt->execute();
$stmt->store_result();
$dup = $stmt->num_rows;
$stmt->close();

test('Duplicate email detected (num_rows > 0)', $dup > 0);

// Attempt a second INSERT to verify the UNIQUE constraint fires
$stmt2 = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
$stmt2->bind_param('sss', $testName, $testEmail, $testHash);
$dupOk = @$stmt2->execute(); // suppress the expected duplicate-key warning
$stmt2->close();

test('UNIQUE constraint prevents duplicate email', !$dupOk);

// ── Cleanup ───────────────────────────────────────────────────────
$del = $conn->prepare('DELETE FROM users WHERE id = ?');
$del->bind_param('i', $testUserId);
$del->execute();
$del->close();

// ── Summary ───────────────────────────────────────────────────────
echo "\n";
if ($testsFailed === 0) {
    echo "\033[32mAll {$testsPassed} auth tests passed.\033[0m\n";
} else {
    echo "\033[31m{$testsFailed} test(s) failed: " . implode(', ', $failures) . "\033[0m\n";
}
