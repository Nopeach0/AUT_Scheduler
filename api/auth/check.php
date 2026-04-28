<?php
/**
 * check.php — Session status endpoint.
 *
 * Returns JSON indicating whether the current request is from a
 * logged-in user, and if so, provides basic user info (id, name, email).
 *
 * Called by every page's JavaScript on DOMContentLoaded to decide
 * whether to show the user dropdown or the Sign In / Register buttons.
 *
 * GET  /api/auth/check.php
 *  → { logged_in: true,  user: { id, name, email } }
 *  → { logged_in: false }
 */

// Prevent PHP notices from appearing in the JSON output
ini_set('display_errors', '0');

// Start session only if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    // User is authenticated — return their profile info
    echo json_encode([
        'logged_in' => true,
        'user'      => [
            'id'    => (int)$_SESSION['user_id'],
            'name'  => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
        ],
    ]);
} else {
    // No active session
    echo json_encode(['logged_in' => false]);
}
