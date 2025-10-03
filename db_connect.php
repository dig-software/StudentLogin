<?php
// Centralized DB connection for anonymization
$servername = 'localhost';
$username_db = 'root';
$password_db = '';
$dbname = 'class';
$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die('Connection failed');
}
// Update last_active for logged-in user
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['username'])) {
    $u = $conn->real_escape_string($_SESSION['username']);
    $conn->query("UPDATE registration SET last_active=NOW() WHERE username='$u'");
}
?>