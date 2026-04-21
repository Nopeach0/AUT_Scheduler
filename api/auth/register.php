<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$name  = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';
$conf  = $_POST['confirm_password'] ?? '';

if (!$name || !$email || !$pass || !$conf) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email address']);
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

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode(['error' => 'Email already registered']);
    exit;
}
$stmt->close();

$hash = password_hash($pass, PASSWORD_DEFAULT);
$stmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $name, $email, $hash);

if ($stmt->execute()) {
    $_SESSION['user_id']    = $conn->insert_id;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true]);
    exit;
}

$stmt->close();
$conn->close();
echo json_encode(['error' => 'Registration failed, please try again']);
