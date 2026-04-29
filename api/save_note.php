<?php
require_once('../database/db_connect.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $note_content = $_POST['content'];
    $event_date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO user_notes (user_id, content, note_date) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $note_content, $event_date);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Note saved!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Execution failed"]);
    }
    $stmt->close();
}
?>
