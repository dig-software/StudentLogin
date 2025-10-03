<?php
session_start();
if (!isset($_SESSION['username'])) {
    http_response_code(403);
    exit('Not logged in');
}
$conn = null;
require_once 'db_connect.php';
if (!$conn) {
    http_response_code(500);
    exit('DB error');
}
$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT sender, recipient, message, timestamp, is_read FROM messages WHERE sender=? OR recipient=? ORDER BY timestamp DESC LIMIT 30");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$stmt->bind_result($sender, $recipient, $msg, $ts, $is_read);
$messages = [];
while ($stmt->fetch()) {
    $messages[] = [
        "sender" => $sender,
        "recipient" => $recipient,
        "message" => $msg,
        "timestamp" => $ts,
        "is_read" => $is_read
    ];
}
$stmt->close();
$conn->close();
header('Content-Type: application/json');
echo json_encode($messages);
