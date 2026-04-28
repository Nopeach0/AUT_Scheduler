<?php
/**
 * db.php — Database connection for Kea Buddy
 *
 * Connects to TiDB Cloud (MySQL-compatible) using SSL when the CA
 * certificate is present, and falls back to a plain connection when
 * SSL is unavailable (e.g., local dev without the cert file).
 *
 * Also ensures the application's required tables exist on first run
 * so no manual SQL migration step is needed.
 */

// Prevent PHP notices/warnings from corrupting JSON API responses
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

require_once __DIR__ . '/config.php';

// ── Attempt connection (SSL → plain fallback) ──────────────────────
$conn = mysqli_init();

$caFile  = defined('DB_CA') ? DB_CA : '';
$useSSL  = $caFile !== '' && file_exists($caFile);

if ($useSSL) {
    // Attach the CA certificate for TiDB Cloud
    mysqli_ssl_set($conn, null, null, $caFile, null, null);
}

$flags     = $useSSL ? MYSQLI_CLIENT_SSL : 0;
$connected = @mysqli_real_connect(
    $conn,
    DB_HOST, DB_USER, DB_PASS, DB_NAME,
    DB_PORT, null, $flags
);

// Fallback: retry without SSL if the SSL attempt failed
if (!$connected && $useSSL) {
    $conn      = mysqli_init();
    $connected = @mysqli_real_connect(
        $conn,
        DB_HOST, DB_USER, DB_PASS, DB_NAME,
        DB_PORT
    );
}

if (!$connected) {
    // Return a clean JSON 503 so the frontend can display a friendly message
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json');
    }
    die(json_encode([
        'error' => 'Database temporarily unavailable. Please try again later.'
    ]));
}

$conn->set_charset('utf8mb4');

// ── Auto-create tables on first run ───────────────────────────────
// These queries are idempotent (IF NOT EXISTS) so they are safe to
// run on every request with negligible overhead.

$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        full_name    VARCHAR(100)  NOT NULL,
        email        VARCHAR(150)  NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS calendar_notes (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        user_id          INT          NOT NULL,
        title            VARCHAR(255) NOT NULL,
        description      TEXT,
        note_date        DATE         NOT NULL,
        note_time        VARCHAR(5)   NOT NULL DEFAULT '09:00',
        category         VARCHAR(20)  NOT NULL DEFAULT 'other',
        reminder_minutes INT          NOT NULL DEFAULT 0,
        created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, note_date)
    )
");
