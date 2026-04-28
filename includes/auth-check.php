<?php
/**
 * auth-check.php — Session authentication middleware.
 *
 * Include this file at the top of any API endpoint that requires
 * the user to be logged in.  On success it exposes $current_user_id
 * for use in the including script.  On failure it halts with HTTP 401.
 *
 * Usage:
 *   require_once __DIR__ . '/../../includes/auth-check.php';
 *   // $current_user_id is now available
 */

// Suppress error output so it cannot corrupt JSON API responses
ini_set('display_errors', '0');

// Start a session only if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Return 401 with a JSON body so the frontend can handle it gracefully
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please sign in to continue.']);
    exit;
}

/** @var int $current_user_id The authenticated user's ID. */
$current_user_id = (int)$_SESSION['user_id'];
