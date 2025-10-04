<?php
// Database connection (simplified)
$conn = null;
require_once 'db_connect.php';
if (!$conn) die("Connection failed");



$name = $_POST["name"];
$username = $_POST["username"];
$reg_number = $_POST["reg_number"];
$phone = $_POST["phone"];
$email = $_POST["email"];
$course = $_POST["course"];
$password = $_POST["password"];

// Handle profile picture upload
$profile_pic = null;
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_', true) . '.' . $ext;
    $target = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
        $profile_pic = $filename;
    }
}

// Basic validation
if (empty($name) || empty($username) || empty($reg_number) || empty($phone) || empty($email) || empty($course) || empty($password)) {
    echo "All fields are required.";
    exit;
}



$stmt = $conn->prepare("SELECT username FROM registration WHERE username=? OR email=? OR reg_number=?");
$stmt->bind_param("sss", $username, $email, $reg_number);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo "Username, email, or registration number already exists.";
    exit;
}
$stmt->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);


// Insert user

$show_videos = isset($_POST['show_videos']) ? (int)$_POST['show_videos'] : 1;
$stmt = $conn->prepare("INSERT INTO registration (name, username, reg_number, phone, email, course, profile_pic, password, show_videos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssi", $name, $username, $reg_number, $phone, $email, $course, $profile_pic, $hashed_password, $show_videos);
if ($stmt->execute()) {
    // Removed duplicate insert into `login` table; `registration` already stores the password.

    // Store WebAuthn credential if provided (supports multiple JSON shapes)
    if (!empty($_POST['webauthn_credential'])) {
        $cred = json_decode($_POST['webauthn_credential'], true);
        if (is_array($cred) && isset($cred['id'])) {
            $credential_id = $cred['id']; // base64url credential id
            // Older shape may have rawId; if not, reuse id
            $rawId = $cred['rawId'] ?? $credential_id;
            // Very minimal storage: we just persist credential_id & rawId placeholder (public_key column)
            // Avoid duplicate insert if already exists
            $chk = $conn->prepare("SELECT 1 FROM webauthn_credentials WHERE username=? AND credential_id=? LIMIT 1");
            $chk->bind_param("ss", $username, $credential_id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows === 0) {
                $stmt_wa = $conn->prepare("INSERT INTO webauthn_credentials (username, credential_id, public_key) VALUES (?, ?, ?)");
                $stmt_wa->bind_param("sss", $username, $credential_id, $rawId);
                $stmt_wa->execute();
                $stmt_wa->close();
            }
            $chk->close();
        }
    }
    header("Location: /StudentLogin/login.html");
    exit();
} else {
    echo "Error: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>
