<?php
/**
 * CalendarTest.php — Unit tests for calendar notes CRUD logic.
 *
 * Tests cover:
 *  1. Note creation (INSERT)
 *  2. Note retrieval (SELECT for a specific user)
 *  3. Note title, category, and reminder accuracy
 *  4. Note update (UPDATE)
 *  5. User isolation (cannot read another user's notes)
 *  6. Note deletion (DELETE)
 *  7. Cascade delete (notes removed when user is deleted)
 *
 * Run from the project root:
 *   php tests/CalendarTest.php
 *
 * @requires PHP ≥ 7.4 with mysqli extension
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Tests must be run from the command line.\n");
}

require_once __DIR__ . '/../includes/db.php';

// ── Shared assertion helpers ──────────────────────────────────────

$testsPassed = 0;
$testsFailed = 0;
$failures    = [];

/**
 * Assert a condition and record pass/fail.
 *
 * @param string $name      Test description
 * @param bool   $condition TRUE = pass
 * @param string $hint      Extra failure context
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

// ── Setup: create a temporary test user ──────────────────────────
$testEmail = 'cal_test_' . time() . '@test.kea';
$testHash  = password_hash('TempPass!', PASSWORD_DEFAULT);
$testName  = 'Calendar Test User';

$stmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $testName, $testEmail, $testHash);
$stmt->execute();
$testUserId = (int)$conn->insert_id;
$stmt->close();

test('Setup: test user created', $testUserId > 0);

// ── Test 1: Create a note ─────────────────────────────────────────
$title    = 'COMP500 Assignment Due';
$desc     = 'Chapter 5 — database normalisation problems';
$date     = date('Y-m-d');          // today
$time     = '23:59';
$category = 'assignment';
$reminder = 1440;                   // 1 day before

$stmt = $conn->prepare(
    'INSERT INTO calendar_notes
        (user_id, title, description, note_date, note_time, category, reminder_minutes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('isssssi',
    $testUserId, $title, $desc, $date, $time, $category, $reminder
);
$ok     = $stmt->execute();
$noteId = (int)$conn->insert_id;
$stmt->close();

test('Note created successfully',   $ok && $noteId > 0);

// ── Test 2: Retrieve notes for the user ──────────────────────────
$stmt = $conn->prepare(
    'SELECT * FROM calendar_notes WHERE user_id = ? ORDER BY note_date ASC'
);
$stmt->bind_param('i', $testUserId);
$stmt->execute();
$result = $stmt->get_result();
$notes  = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

test('Notes retrieved — correct count',  count($notes) === 1);

$n = $notes[0] ?? [];

// ── Test 3: Data accuracy ─────────────────────────────────────────
test('Note title stored correctly',    ($n['title']            ?? '') === $title);
test('Note category stored correctly', ($n['category']         ?? '') === $category);
test('Note time stored correctly',     ($n['note_time']        ?? '') === $time);
test('Note reminder stored correctly', (int)($n['reminder_minutes'] ?? -1) === $reminder);
test('Note date stored correctly',     ($n['note_date']        ?? '') === $date);

// ── Test 4: Update a note ─────────────────────────────────────────
$newTitle = 'COMP500 — UPDATED title';
$stmt = $conn->prepare(
    'UPDATE calendar_notes SET title = ? WHERE id = ? AND user_id = ?'
);
$stmt->bind_param('sii', $newTitle, $noteId, $testUserId);
$ok = $stmt->execute();
$stmt->close();

// Re-fetch to confirm the update
$stmt = $conn->prepare('SELECT title FROM calendar_notes WHERE id = ?');
$stmt->bind_param('i', $noteId);
$stmt->execute();
$upd = $stmt->get_result()->fetch_assoc();
$stmt->close();

test('Note updated in DB',            $ok);
test('Updated title matches',         ($upd['title'] ?? '') === $newTitle);

// ── Test 5: User isolation ────────────────────────────────────────
// A different user ID must NOT be able to read or delete this note.
$otherId = $testUserId + 99999;

$stmt = $conn->prepare(
    'SELECT id FROM calendar_notes WHERE id = ? AND user_id = ?'
);
$stmt->bind_param('ii', $noteId, $otherId);
$stmt->execute();
$stmt->store_result();
$leaked = $stmt->num_rows > 0;
$stmt->close();

test('User isolation: other user cannot read note', !$leaked);

$stmt = $conn->prepare(
    'DELETE FROM calendar_notes WHERE id = ? AND user_id = ?'
);
$stmt->bind_param('ii', $noteId, $otherId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

test('User isolation: other user cannot delete note', $affected === 0);

// ── Test 6: Delete the note ───────────────────────────────────────
$stmt = $conn->prepare(
    'DELETE FROM calendar_notes WHERE id = ? AND user_id = ?'
);
$stmt->bind_param('ii', $noteId, $testUserId);
$ok = $stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT id FROM calendar_notes WHERE id = ?');
$stmt->bind_param('i', $noteId);
$stmt->execute();
$stmt->store_result();
$stillExists = $stmt->num_rows > 0;
$stmt->close();

test('Note deleted from DB', $ok && !$stillExists);

// ── Test 7: Cascade delete via user removal ───────────────────────
// Insert another note, delete the user, confirm the note is gone.
$stmt = $conn->prepare(
    'INSERT INTO calendar_notes
        (user_id, title, description, note_date, note_time, category, reminder_minutes)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$cascadeTitle = 'Cascade test note';
$stmt->bind_param('isssssi',
    $testUserId, $cascadeTitle, $desc, $date, $time, $category, $reminder
);
$stmt->execute();
$cascadeNoteId = (int)$conn->insert_id;
$stmt->close();

// Delete the user — should cascade to notes
$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $testUserId);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare('SELECT id FROM calendar_notes WHERE id = ?');
$stmt->bind_param('i', $cascadeNoteId);
$stmt->execute();
$stmt->store_result();
$cascadeExists = $stmt->num_rows > 0;
$stmt->close();

test('Cascade delete: notes removed when user deleted', !$cascadeExists);

// ── Summary ───────────────────────────────────────────────────────
echo "\n";
if ($testsFailed === 0) {
    echo "\033[32mAll {$testsPassed} calendar tests passed.\033[0m\n";
} else {
    echo "\033[31m{$testsFailed} test(s) failed: " . implode(', ', $failures) . "\033[0m\n";
}
