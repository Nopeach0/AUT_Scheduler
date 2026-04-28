<?php
/**
 * events.php — CRUD API for calendar notes.
 *
 * GET    /api/calendar/events.php          → array of all notes for the logged-in user
 * POST   /api/calendar/events.php          → create a new note (JSON body)
 * PUT    /api/calendar/events.php          → update an existing note (JSON body with id)
 * DELETE /api/calendar/events.php?id=123   → delete note by ID
 *
 * All endpoints require an active user session (401 if not logged in).
 */

// Prevent PHP notices from corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth-check.php'; // sets $current_user_id or exits 401
require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ── GET — return all notes for the current user ───────────────
    case 'GET':
        $stmt = $conn->prepare(
            'SELECT id, user_id, title, description, note_date, note_time,
                    category, reminder_minutes, created_at
             FROM calendar_notes
             WHERE user_id = ?
             ORDER BY note_date ASC, note_time ASC'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }

        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];

        while ($row = $result->fetch_assoc()) {
            // Cast numeric columns so JSON output is typed correctly
            $row['id']               = (int)$row['id'];
            $row['reminder_minutes'] = (int)$row['reminder_minutes'];
            $rows[] = $row;
        }

        $stmt->close();
        echo json_encode($rows);
        break;

    // ── POST — create a new note ──────────────────────────────────
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        $title            = trim($data['title']       ?? '');
        $description      = trim($data['description'] ?? '');
        $note_date        = $data['note_date']         ?? '';
        $note_time        = $data['note_time']         ?? '09:00';
        $category         = $data['category']          ?? 'other';
        $reminder_minutes = (int)($data['reminder_minutes'] ?? 0);

        if (!$title || !$note_date) {
            jsonError('Title and date are required', 400);
        }

        $stmt = $conn->prepare(
            'INSERT INTO calendar_notes
                (user_id, title, description, note_date, note_time, category, reminder_minutes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }

        $stmt->bind_param('isssssi',
            $current_user_id, $title, $description,
            $note_date, $note_time, $category, $reminder_minutes
        );

        if (!$stmt->execute()) {
            jsonError('Failed to save note: ' . $stmt->error, 500);
        }

        $new_id = (int)$conn->insert_id;
        $stmt->close();

        echo json_encode([
            'id'               => $new_id,
            'user_id'          => $current_user_id,
            'title'            => $title,
            'description'      => $description,
            'note_date'        => $note_date,
            'note_time'        => $note_time,
            'category'         => $category,
            'reminder_minutes' => $reminder_minutes,
        ]);
        break;

    // ── PUT — update an existing note ────────────────────────────
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);

        $id               = (int)($data['id']              ?? 0);
        $title            = trim($data['title']             ?? '');
        $description      = trim($data['description']       ?? '');
        $note_date        = $data['note_date']               ?? '';
        $note_time        = $data['note_time']               ?? '09:00';
        $category         = $data['category']                ?? 'other';
        $reminder_minutes = (int)($data['reminder_minutes'] ?? 0);

        if (!$id || !$title || !$note_date) {
            jsonError('ID, title, and date are required', 400);
        }

        $stmt = $conn->prepare(
            'UPDATE calendar_notes
             SET title=?, description=?, note_date=?, note_time=?, category=?, reminder_minutes=?
             WHERE id=? AND user_id=?'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }

        // sssssiii: 5 strings then 3 ints (reminder_minutes, id, user_id)
        $stmt->bind_param('sssssiii',
            $title, $description, $note_date, $note_time, $category,
            $reminder_minutes, $id, $current_user_id
        );

        if (!$stmt->execute()) {
            jsonError('Failed to update note: ' . $stmt->error, 500);
        }

        $stmt->close();

        echo json_encode([
            'id'               => $id,
            'user_id'          => $current_user_id,
            'title'            => $title,
            'description'      => $description,
            'note_date'        => $note_date,
            'note_time'        => $note_time,
            'category'         => $category,
            'reminder_minutes' => $reminder_minutes,
        ]);
        break;

    // ── DELETE — remove a note ────────────────────────────────────
    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { jsonError('Note ID required', 400); }

        $stmt = $conn->prepare(
            'DELETE FROM calendar_notes WHERE id=? AND user_id=?'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }

        $stmt->bind_param('ii', $id, $current_user_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

$conn->close();

/**
 * Output a JSON error and halt execution.
 *
 * @param string $message Human-readable error message.
 * @param int    $code    HTTP status code (default 400).
 */
function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
