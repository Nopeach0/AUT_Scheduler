<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ======================= 1. Database Connection Configuration =======================
$host = '127.0.0.1';
$db   = 'kea_buddy';
$user = 'root';
$pass = '';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ======================= 2. Process Request & Update =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Receive dynamic data from frontend
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    $isEnabled = isset($_POST['is_enabled']) ? $_POST['is_enabled'] : null;
    
    if ($userId !== null && $isEnabled !== null) {
        try {
            // Prepare and execute the SQL update statement
            $stmt = $pdo->prepare("UPDATE users SET is_reminder_enabled = ? WHERE id = ?");
            
            if ($stmt->execute([$isEnabled, $userId])) {
                echo "Success: Reminder status for user {$userId} updated to {$isEnabled}";
            } else {
                echo "Error: Database update failed";
            }
        } catch (\PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    } else {
        echo "Error: Missing user_id or is_enabled parameters";
    }
}
?>