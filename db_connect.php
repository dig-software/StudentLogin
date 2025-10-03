<?php
// Centralized DB connection with environment-variable fallback for container (Railway / Docker) deployment
// Priority order for each value:
// 1. Explicit DB_* env vars (recommended to set in Railway dashboard)
// 2. Railway auto-provisioned MYSQL* vars (if a MySQL service is attached)
// 3. Local development defaults

$servername   = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$username_db  = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$password_db  = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '');
$dbname       = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'class');
$port         = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));

$conn = @new mysqli($servername, $username_db, $password_db, $dbname, $port);
if ($conn->connect_error) {
    // Avoid leaking credentials; generic message
    die('Connection failed');
}

// Optional: Strengthen session cookie settings when served over HTTPS
if (PHP_SAPI !== 'cli') {
    if (function_exists('session_set_cookie_params')) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $params = session_get_cookie_params();
        // Re-set only if not already configured earlier
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

// Update last_active for logged-in user
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['username'])) {
    $u = $conn->real_escape_string($_SESSION['username']);
    $conn->query("UPDATE registration SET last_active=NOW() WHERE username='$u'");
}
?>