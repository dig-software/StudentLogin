<?php
// Minimal biometric verification for password reset (NOT production-secure)
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
if(!$conn){ echo json_encode(['success'=>false,'error'=>'db']); exit; }
$username = $_POST['username'] ?? '';
$credential_id = $_POST['credential_id'] ?? '';
if($username==='' || $credential_id===''){ echo json_encode(['success'=>false,'error'=>'missing']); exit; }
$stmt = $conn->prepare("SELECT 1 FROM webauthn_credentials WHERE username=? AND credential_id=? LIMIT 1");
$stmt->bind_param("ss", $username, $credential_id);
$stmt->execute();
$stmt->store_result();
if($stmt->num_rows===1){
    $_SESSION['reset_bio_user'] = $username;
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'not_match']);
}
$stmt->close();
$conn->close();
?>