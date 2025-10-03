<?php
// Returns biometric status for logged-in user OR a specified username (admin/future)
// Simple: status is based on presence of at least one row in webauthn_credentials
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
if(!$conn){ echo json_encode(['error'=>'db']); exit; }

$username = $_SESSION['username'] ?? ($_GET['username'] ?? '');
if($username==='') { echo json_encode(['error'=>'no_user']); exit; }

$stmt = $conn->prepare('SELECT COUNT(*) FROM webauthn_credentials WHERE username=?');
$stmt->bind_param('s',$username);
$stmt->execute();
$stmt->bind_result($cnt);
$stmt->fetch();
$stmt->close();

echo json_encode([
  'username' => $username,
  'credential_count' => (int)$cnt,
  'biometric_enabled' => $cnt > 0
]);
$conn->close();
