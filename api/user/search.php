<?php
/**
 * search.php — Find users by name or email (for friend requests).
 *
 * GET /api/user/search.php?q=QUERY
 *
 * Returns up to 10 users whose name or email contains the query string.
 * Excludes the searching user themselves. Requires authentication.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$like = '%' . $q . '%';
$stmt = $conn->prepare(
    'SELECT id, full_name FROM users
     WHERE (full_name LIKE ? OR email LIKE ?) AND id != ?
     ORDER BY full_name ASC
     LIMIT 10'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query preparation failed']);
    exit;
}
$stmt->bind_param('ssi', $like, $like, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$users  = [];
while ($row = $result->fetch_assoc()) {
    $users[] = ['id' => (int)$row['id'], 'name' => $row['full_name']];
}
$stmt->close();
$conn->close();

echo json_encode($users);
