<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['new_password'])) {
    $username = $_POST['username'];
    $code = $_POST['code'] ?? '';
    $new_password = $_POST['new_password'];
    $conn = null;
    require_once 'db_connect.php';
    if (!$conn) die("Connection failed");

    $bioAuthorized = (isset($_SESSION['reset_bio_user']) && $_SESSION['reset_bio_user'] === $username);

    $allow = false;
    if ($bioAuthorized && $code === '') {
        // Biometric session authorization path
        $allow = true;
    } else {
        // Code path
        if ($code === '') {
            echo "Code required (or use biometric). <a href='reset_password.html'>Back</a>"; exit();
        }
        $stmt = $conn->prepare("SELECT expires_at FROM password_reset_codes WHERE username=? AND code=? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $username, $code);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            $stmt->bind_result($expires_at);
            $stmt->fetch();
            if (strtotime($expires_at) > time()) {
                $allow = true;
            } else {
                echo "Code expired. <a href='reset_request.html'>Request a new code</a>."; $stmt->close(); $conn->close(); exit();
            }
        } else {
            echo "Invalid code or username. <a href='reset_password.html'>Try again</a>."; $stmt->close(); $conn->close(); exit();
        }
        // If using code, clean it up
        $stmt->close();
        if ($allow) {
            $stmt_del = $conn->prepare("DELETE FROM password_reset_codes WHERE username=? AND code=?");
            $stmt_del->bind_param("ss", $username, $code);
            $stmt_del->execute();
            $stmt_del->close();
        }
    }

    if ($allow) {
        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE registration SET password=? WHERE username=?");
        $stmt_update->bind_param("ss", $hashed_new_password, $username);
        $success1 = $stmt_update->execute();
        $stmt_update->close();
        $stmt_update2 = $conn->prepare("UPDATE login SET password=? WHERE username=?");
        $stmt_update2->bind_param("ss", $hashed_new_password, $username);
        $success2 = $stmt_update2->execute();
        $stmt_update2->close();
        if ($bioAuthorized) unset($_SESSION['reset_bio_user']);
        if ($success1 && $success2) { header("Location: login.html?reset=success"); exit(); }
        echo "Password reset failed. <a href='reset_password.html'>Try again</a>.";
    }
    $conn->close();
    exit();
}
?>
