<?php
// Endpoint: enable_biometric.php
// Purpose: Store a new WebAuthn credential for the logged-in user ("enable biometric login").
// NOTE: This is a minimal implementation (NO full attestation / signature validation).
// In production, you must verify the attestation object and store the parsed public key.

header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';
if(!$conn){ echo json_encode(['success'=>false,'error'=>'db']); exit; }

if(!isset($_SESSION['username'])) { echo json_encode(['success'=>false,'error'=>'auth']); exit; }
$username = $_SESSION['username'];

$raw = $_POST['credential'] ?? '';
if($raw===''){ echo json_encode(['success'=>false,'error'=>'missing']); exit; }
$cred = json_decode($raw, true);
if(!is_array($cred) || empty($cred['id'])) { echo json_encode(['success'=>false,'error'=>'bad_format']); exit; }
$credential_id = $cred['id'];
$rawId = $cred['rawId'] ?? $credential_id; // fallback

// Check if already enabled (at least one credential)
$chk = $conn->prepare('SELECT credential_id FROM webauthn_credentials WHERE username=? AND credential_id=? LIMIT 1');
$chk->bind_param('ss',$username,$credential_id);
$chk->execute();
$chk->store_result();
if($chk->num_rows>0){
    $chk->close();
    echo json_encode(['success'=>true,'status'=>'exists']);
    $conn->close();
    exit;
}
$chk->close();

// Option: limit to single credential. Uncomment to wipe older ones.
// $conn->query("DELETE FROM webauthn_credentials WHERE username='".$conn->real_escape_string($username)."'");

$ins = $conn->prepare('INSERT INTO webauthn_credentials (username, credential_id, public_key) VALUES (?, ?, ?)');
$ins->bind_param('sss',$username,$credential_id,$rawId);
if($ins->execute()){
    echo json_encode(['success'=>true,'status'=>'added']);
}else{
    echo json_encode(['success'=>false,'error'=>'insert_failed']);
}
$ins->close();
$conn->close();
