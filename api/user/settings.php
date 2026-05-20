<?php
/**
 * settings.php — User schedule visibility setting.
 *
 * GET  /api/user/settings.php  → { schedule_visibility: "public"|"friends"|"private" }
 * POST /api/user/settings.php  → save schedule_visibility, returns { success, schedule_visibility }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    case 'GET':
        $stmt = $conn->prepare('SELECT schedule_visibility FROM users WHERE id = ?');
        if (!$stmt) { jsonError('Query preparation failed', 500); }
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['schedule_visibility' => $row['schedule_visibility'] ?? 'public']);
        break;

    case 'POST':
        $data       = json_decode(file_get_contents('php://input'), true);
        $visibility = $data['schedule_visibility'] ?? '';

        if (!in_array($visibility, ['public', 'friends', 'private'], true)) {
            jsonError('Invalid visibility value', 400);
        }

        $stmt = $conn->prepare('UPDATE users SET schedule_visibility = ? WHERE id = ?');
        if (!$stmt) { jsonError('Query preparation failed', 500); }
        $stmt->bind_param('si', $visibility, $current_user_id);
        if (!$stmt->execute()) { jsonError('Failed to save setting', 500); }
        $stmt->close();

        echo json_encode(['success' => true, 'schedule_visibility' => $visibility]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

$conn->close();

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
