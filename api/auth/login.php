<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$pass     = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

if (!$email || !$pass) {
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$stmt = $conn->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->bind_result($id, $full_name, $db_email, $password_hash);
$found = $stmt->fetch();
$stmt->close();

if (!$found || !password_verify($pass, $password_hash)) {
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

$_SESSION['user_id']    = $id;
$_SESSION['user_name']  = $full_name;
$_SESSION['user_email'] = $db_email;

if ($remember) {
    ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
    session_regenerate_id(true);
}

$conn->close();
echo json_encode(['success' => true, 'name' => $full_name]);
