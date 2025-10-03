<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
if(!$conn){ echo json_encode(['error'=>'db']); exit; }
$username = $_GET['username'] ?? '';
if($username===''){ echo json_encode(['credential_id'=>null]); exit; }
$stmt = $conn->prepare("SELECT credential_id FROM webauthn_credentials WHERE username=? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($cid);
if($stmt->fetch()) {
    echo json_encode(['credential_id'=>$cid]);
} else {
    echo json_encode(['credential_id'=>null]);
}
$stmt->close();
$conn->close();
?>