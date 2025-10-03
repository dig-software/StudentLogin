<?php
// Endpoint: disable_biometric.php
// Purpose: Remove ALL WebAuthn credentials for logged-in user IF they prove possession via an assertion.
// Minimal placeholder (no cryptographic signature verification). Assumes front-end already got assertion.id.
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
if(!$conn){ echo json_encode(['success'=>false,'error'=>'db']); exit; }
if(!isset($_SESSION['username'])) { echo json_encode(['success'=>false,'error'=>'auth']); exit; }
$username = $_SESSION['username'];

$assert_id = $_POST['credential_id'] ?? '';
if($assert_id===''){ echo json_encode(['success'=>false,'error'=>'missing']); exit; }

// Verify that this credential belongs to the user
$chk = $conn->prepare('SELECT 1 FROM webauthn_credentials WHERE username=? AND credential_id=? LIMIT 1');
$chk->bind_param('ss',$username,$assert_id);
$chk->execute();
$chk->store_result();
if($chk->num_rows!==1){
  $chk->close();
  echo json_encode(['success'=>false,'error'=>'not_owned']);
  $conn->close();
  exit;
}
$chk->close();

// Delete all credentials for a clean disable
$del = $conn->prepare('DELETE FROM webauthn_credentials WHERE username=?');
$del->bind_param('s',$username);
if($del->execute()){
  echo json_encode(['success'=>true,'status'=>'disabled']);
}else{
  echo json_encode(['success'=>false,'error'=>'delete_failed']);
}
$del->close();
$conn->close();
