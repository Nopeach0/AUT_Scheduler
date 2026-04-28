<?php
/**
 * register.php — Register a new user account.
 *
 * Accepts POST: full_name, email, password, confirm_password.
 * Returns JSON { success: true } on success,
 *         or   { error: "..." } on failure.
 */

// Prevent PHP notices from corrupting the JSON response
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Read and sanitise inputs ───────────────────────────────────────
$name  = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email']     ?? '');
$pass  =       $_POST['password']         ?? '';
$conf  =       $_POST['confirm_password'] ?? '';

// ── Validate inputs ────────────────────────────────────────────────
if (!$name || !$email || !$pass || !$conf) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Please enter a valid email address']);
    exit;
}

if ($pass !== $conf) {
    echo json_encode(['error' => 'Passwords do not match']);
    exit;
}

if (strlen($pass) < 8) {
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

// ── Check for existing account ────────────────────────────────────
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
if (!$stmt) {
    echo json_encode(['error' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['error' => 'An account with that email already exists']);
    exit;
}
$stmt->close();

// ── Create the account ────────────────────────────────────────────
$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
if (!$stmt) {
    echo json_encode(['error' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param('sss', $name, $email, $hash);

if (!$stmt->execute()) {
    $stmt->close();
    echo json_encode(['error' => 'Registration failed. Please try again.']);
    exit;
}

// Auto-login after successful registration
$newId = (int)$conn->insert_id;
$stmt->close();

session_regenerate_id(true);
$_SESSION['user_id']    = $newId;
$_SESSION['user_name']  = $name;
$_SESSION['user_email'] = $email;

$conn->close();
echo json_encode(['success' => true, 'name' => $name]);
