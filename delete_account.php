<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "Access denied.";
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && $_POST['username'] === $_SESSION['username']) {
    $conn = null;
    require_once 'db_connect.php';
    if (!$conn) die("Connection failed");
    $username = $_SESSION['username'];
    $stmt = $conn->prepare("DELETE FROM registration WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->close();
    $stmt2 = $conn->prepare("DELETE FROM login WHERE username=?");
    $stmt2->bind_param("s", $username);
    $stmt2->execute();
    $stmt2->close();
    session_unset();
    session_destroy();
    echo "<p>Account deleted successfully. <a href='login.html'>Go to Login</a></p>";
    $conn->close();
    exit();
} else {
    echo "Invalid request.";
}
?>
