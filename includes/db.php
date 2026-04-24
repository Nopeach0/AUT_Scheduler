<?php
require_once __DIR__ . '/config.php';

$conn = mysqli_init();

mysqli_ssl_set($conn, NULL, NULL, DB_CA, NULL, NULL);

$connected = mysqli_real_connect(
    $conn,
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    NULL,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
}

$conn->set_charset('utf8mb4');
