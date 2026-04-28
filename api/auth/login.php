<?php
/**
 * login.php — Authenticate a user and start a session.
 *
 * Accepts POST: email, password, remember (optional checkbox value "1").
 * Returns JSON { success: true, name: "..." } on success,
 *         or   { error: "..." } on failure.
 */

// Prevent any PHP notice/warning from breaking the JSON response
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$pass     = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Basic input validation
if (!$email || !$pass) {
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

// Look up the user by email
$stmt = $conn->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = ?');
if (!$stmt) {
    echo json_encode(['error' => 'Server error. Please try again.']);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($id, $full_name, $db_email, $password_hash);
$found = $stmt->fetch();
$stmt->close();

// Verify credentials — constant-time compare prevents timing attacks
if (!$found || !password_verify($pass, $password_hash)) {
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// Regenerate session ID on privilege change to prevent session fixation
session_regenerate_id(true);

$_SESSION['user_id']    = $id;
$_SESSION['user_name']  = $full_name;
$_SESSION['user_email'] = $db_email;

// Optional persistent session (30-day cookie)
if ($remember) {
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.cookie_lifetime', $lifetime);
    setcookie(session_name(), session_id(), time() + $lifetime, '/', '', false, true);
}

$conn->close();
echo json_encode(['success' => true, 'name' => $full_name]);
