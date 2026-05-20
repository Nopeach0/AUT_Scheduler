<?php
/**
 * friends/index.php — Friend management API.
 *
 * GET    /api/friends/index.php             → list friends & pending requests
 * POST   /api/friends/index.php             → send / accept / reject request
 * DELETE /api/friends/index.php?user_id=X  → remove a friend or cancel request
 *
 * POST body actions:
 *   { action: "send",   user_id: N }        — send a friend request
 *   { action: "accept", request_id: N }     — accept an incoming request
 *   { action: "reject", request_id: N }     — reject / cancel a request
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ── GET — list all friend relationships for current user ───────
    case 'GET':
        $stmt = $conn->prepare(
            'SELECT
                f.id, f.status,
                CASE WHEN f.requester_id = ? THEN "sent" ELSE "received" END AS direction,
                u.id AS user_id, u.full_name AS user_name
             FROM friendships f
             JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
             WHERE f.requester_id = ? OR f.addressee_id = ?
             ORDER BY f.status DESC, u.full_name ASC'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }
        $stmt->bind_param('iiii',
            $current_user_id, $current_user_id,
            $current_user_id, $current_user_id
        );
        $stmt->execute();
        $result = $stmt->get_result();
        $list   = [];
        while ($row = $result->fetch_assoc()) {
            $row['id']      = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $list[] = $row;
        }
        $stmt->close();
        echo json_encode($list);
        break;

    // ── POST — send / accept / reject ─────────────────────────────
    case 'POST':
        $data   = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        if ($action === 'send') {
            $target = (int)($data['user_id'] ?? 0);
            if (!$target || $target === $current_user_id) {
                jsonError('Invalid target user', 400);
            }

            // Check if a relationship already exists in either direction
            $chk = $conn->prepare(
                'SELECT id FROM friendships
                 WHERE (requester_id = ? AND addressee_id = ?)
                    OR (requester_id = ? AND addressee_id = ?)'
            );
            $chk->bind_param('iiii', $current_user_id, $target, $target, $current_user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                jsonError('Friend request already exists', 409);
            }
            $chk->close();

            $ins = $conn->prepare(
                'INSERT INTO friendships (requester_id, addressee_id) VALUES (?, ?)'
            );
            $ins->bind_param('ii', $current_user_id, $target);
            if (!$ins->execute()) { jsonError('Failed to send request', 500); }
            $new_id = (int)$conn->insert_id;
            $ins->close();
            echo json_encode(['success' => true, 'id' => $new_id]);

        } elseif ($action === 'accept') {
            $rid = (int)($data['request_id'] ?? 0);
            if (!$rid) { jsonError('request_id required', 400); }

            $upd = $conn->prepare(
                'UPDATE friendships SET status = "accepted"
                 WHERE id = ? AND addressee_id = ? AND status = "pending"'
            );
            $upd->bind_param('ii', $rid, $current_user_id);
            $upd->execute();
            if ($upd->affected_rows === 0) { jsonError('Request not found', 404); }
            $upd->close();
            echo json_encode(['success' => true]);

        } elseif ($action === 'reject') {
            $rid = (int)($data['request_id'] ?? 0);
            if (!$rid) { jsonError('request_id required', 400); }

            $del = $conn->prepare(
                'DELETE FROM friendships
                 WHERE id = ? AND (addressee_id = ? OR requester_id = ?)'
            );
            $del->bind_param('iii', $rid, $current_user_id, $current_user_id);
            $del->execute();
            $del->close();
            echo json_encode(['success' => true]);

        } else {
            jsonError('Invalid action', 400);
        }
        break;

    // ── DELETE — remove friend or cancel outgoing request ─────────
    case 'DELETE':
        $friend_id = (int)($_GET['user_id'] ?? 0);
        if (!$friend_id) { jsonError('user_id required', 400); }

        $del = $conn->prepare(
            'DELETE FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?)
                OR (requester_id = ? AND addressee_id = ?)'
        );
        $del->bind_param('iiii',
            $current_user_id, $friend_id,
            $friend_id, $current_user_id
        );
        $del->execute();
        $del->close();
        echo json_encode(['success' => true]);
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
