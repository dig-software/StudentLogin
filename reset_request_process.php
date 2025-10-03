<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'];
    $conn = null;
    require_once 'db_connect.php';
    if (!$conn) die("Connection failed");
    // Check if username exists
    $stmt = $conn->prepare("SELECT username FROM registration WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 1) {
        // Generate random code
        $code = rand(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        // Store code in DB
        $stmt_insert = $conn->prepare("INSERT INTO password_reset_codes (username, code, expires_at) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $username, $code, $expires_at);
        $stmt_insert->execute();
        $stmt_insert->close();
        echo "<h2>Password Reset Code</h2>";
        echo "<p>Your one-time code is: <b>$code</b></p>";
        echo "<p>This code will expire in 2 minutes.</p>";
        echo "<a href='reset_password.html'>Continue to password reset</a>";
    } else {
        echo "Username not found. <a href='reset_request.html'>Try again</a>.";
    }
    $stmt->close();
    $conn->close();
    exit();
}
?>
