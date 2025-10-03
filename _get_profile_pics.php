<?php
// Returns an associative array: username => profile_pic (or null)
$conn = null;
require_once 'db_connect.php';
if (!$conn) die("DB error");
$users = [];
$res = $conn->query("SELECT username, profile_pic FROM registration");
while ($row = $res->fetch_assoc()) {
    $users[$row['username']] = $row['profile_pic'];
}
header('Content-Type: application/json');
echo json_encode($users);
$conn->close();
