<?php
/**
 * update-profile.php — Profile customisation endpoint.
 *
 * GET  /api/user/update-profile.php
 *      → { display_name, bio, avatar_path }
 *
 * POST /api/user/update-profile.php  (multipart/form-data)
 *      fields: display_name (required), bio (optional)
 *      file:   avatar       (optional, JPEG/PNG/GIF/WebP, max 2 MB)
 *      → { success, display_name, bio, avatar_path }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // ── GET — return current profile customisation fields ─────────
    case 'GET':
        $stmt = $conn->prepare(
            'SELECT display_name, bio, avatar_path FROM users WHERE id = ?'
        );
        if (!$stmt) { jsonError('Query preparation failed', 500); }
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'display_name' => $row['display_name'],
            'bio'          => $row['bio'],
            'avatar_path'  => $row['avatar_path'],
        ]);
        break;

    // ── POST — update display name, bio, and/or avatar ────────────
    case 'POST':
        $display_name = trim($_POST['display_name'] ?? '');
        $bio          = trim($_POST['bio']          ?? '');

        // Validate display name
        if ($display_name === '') {
            jsonError('Display name cannot be empty', 400);
        }
        if (mb_strlen($display_name) > 100) {
            jsonError('Display name must be 100 characters or fewer', 400);
        }

        // Validate bio length
        if (mb_strlen($bio) > 300) {
            jsonError('Bio must be 300 characters or fewer', 400);
        }

        // ── Avatar upload (optional) ──────────────────────────────
        $avatar_path = null;

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];

            // Validate MIME type using finfo (not the client-supplied type)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                        'image/gif'  => 'gif', 'image/webp' => 'webp'];
            if (!array_key_exists($mime, $allowed)) {
                jsonError('Avatar must be a JPEG, PNG, GIF, or WebP image', 400);
            }

            // Validate file size (max 2 MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                jsonError('Avatar must be smaller than 2 MB', 400);
            }

            // Save to uploads/avatars/{user_id}.{ext}
            $dir = __DIR__ . '/../../uploads/avatars/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = $current_user_id . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                jsonError('Failed to save avatar', 500);
            }

            $avatar_path = 'uploads/avatars/' . $filename;
        }

        // ── Persist to database ───────────────────────────────────
        if ($avatar_path !== null) {
            $stmt = $conn->prepare(
                'UPDATE users SET display_name = ?, bio = ?, avatar_path = ? WHERE id = ?'
            );
            if (!$stmt) { jsonError('Query preparation failed', 500); }
            $stmt->bind_param('sssi', $display_name, $bio, $avatar_path, $current_user_id);
        } else {
            $stmt = $conn->prepare(
                'UPDATE users SET display_name = ?, bio = ? WHERE id = ?'
            );
            if (!$stmt) { jsonError('Query preparation failed', 500); }
            $stmt->bind_param('ssi', $display_name, $bio, $current_user_id);
        }

        if (!$stmt->execute()) {
            jsonError('Failed to update profile', 500);
        }
        $stmt->close();

        // Return the final avatar_path (unchanged if no new upload)
        if ($avatar_path === null) {
            $s2 = $conn->prepare('SELECT avatar_path FROM users WHERE id = ?');
            $s2->bind_param('i', $current_user_id);
            $s2->execute();
            $avatar_path = $s2->get_result()->fetch_assoc()['avatar_path'];
            $s2->close();
        }

        echo json_encode([
            'success'      => true,
            'display_name' => $display_name,
            'bio'          => $bio,
            'avatar_path'  => $avatar_path,
        ]);
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
