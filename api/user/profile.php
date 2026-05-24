<?php
/**
 * profile.php — Public profile view with schedule visibility enforcement.
 *
 * GET /api/user/profile.php?id=USER_ID
 *
 * Returns public user info. If the viewer has permission to see the schedule,
 * also returns note stats. Visibility rules:
 *   public  → anyone can see the schedule
 *   friends → only accepted friends can see it
 *   private → only the owner can see it
 *
 * Does NOT require authentication (unauthenticated viewers get public data only).
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$viewer_id  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$profile_id = (int)($_GET['id'] ?? 0);

if (!$profile_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Fetch target user
$stmt = $conn->prepare(
    'SELECT id, full_name, display_name, bio, avatar_path, schedule_visibility FROM users WHERE id = ?'
);
if (!$stmt) { jsonError('Query preparation failed', 500); }
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$visibility = $user['schedule_visibility'] ?? 'public';

// Determine if the viewer may see the schedule (uses canViewSchedule() from functions.php)
$can_see = canViewSchedule(
    $visibility,
    $viewer_id,
    $profile_id,
    function (int $viewerId, int $ownerId) use ($conn): bool {
        $f = $conn->prepare(
            'SELECT id FROM friendships
             WHERE status = "accepted"
               AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))'
        );
        $f->bind_param('iiii', $viewerId, $ownerId, $ownerId, $viewerId);
        $f->execute();
        $found = $f->get_result()->num_rows > 0;
        $f->close();
        return $found;
    }
);

$response = [
    'id'                  => (int)$user['id'],
    'name'                => $user['display_name'] ?: $user['full_name'],
    'bio'                 => $user['bio'],
    'avatar_path'         => $user['avatar_path'],
    'schedule_visibility' => $visibility,
    'can_see_schedule'    => $can_see,
];

if ($can_see) {
    $ns = $conn->prepare('SELECT category, note_date FROM calendar_notes WHERE user_id = ?');
    $ns->bind_param('i', $profile_id);
    $ns->execute();
    $result = $ns->get_result();
    $notes  = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    $ns->close();
    $response['notes'] = $notes;
}

// Friendship status between viewer and profile owner
if ($viewer_id && $viewer_id !== $profile_id) {
    $fs = $conn->prepare(
        'SELECT id, status, requester_id FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)'
    );
    $fs->bind_param('iiii', $viewer_id, $profile_id, $profile_id, $viewer_id);
    $fs->execute();
    $frow = $fs->get_result()->fetch_assoc();
    $fs->close();

    $response['friendship'] = $frow ? [
        'id'           => (int)$frow['id'],
        'status'       => $frow['status'],
        'is_requester' => (int)$frow['requester_id'] === $viewer_id,
    ] : null;
}

echo json_encode($response);
$conn->close();

function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
