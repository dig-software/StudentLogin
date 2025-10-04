<?php
// Centralized DB connection with environment-variable fallback for container (Railway / Docker) deployment
// Priority order for each value:
// 1. Explicit DB_* env vars (recommended to set in Railway dashboard)
// 2. Railway auto-provisioned MYSQL* vars (if a MySQL service is attached)
// 3. Local development defaults

// Gather basic connection parameters (MySQL / MariaDB compatible)
$servername   = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$username_db  = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$password_db  = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '');
$dbname       = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'class');
$port         = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));
$charset      = getenv('DB_CHARSET') ?: 'utf8mb4';

// Optional TLS / SSL flags (useful for managed MariaDB / SkySQL / cloud DBs)
$wantSSL      = getenv('DB_SSL') === '1';                 // enable SSL
$caFile       = getenv('DB_SSL_CA') ?: '';                // path to CA bundle / cert
$verifyPeer   = getenv('DB_SSL_VERIFY') !== '0';          // default verify on

// Initialize mysqli explicitly so we can set options prior to connect
$conn = mysqli_init();

// If strict mode requested
if (getenv('DB_STRICT') === '1') {
    // Throw mysqli exceptions (PHP 8+) for easier debugging while still masking prod errors
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

if ($wantSSL) {
    // Provide CA cert if given (empty strings for unused params)
    if ($caFile && is_readable($caFile)) {
        // client-key, client-cert, ca, capath, cipher
        @mysqli_ssl_set($conn, null, null, $caFile, null, null);
    } else {
        // Attempt with system bundle (common Debian/Alpine paths) if user not set
        $systemBundles = [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/ca-bundle.pem'
        ];
        foreach ($systemBundles as $b) {
            if (is_readable($b)) { @mysqli_ssl_set($conn, null, null, $b, null, null); break; }
        }
    }
    // Control server cert verification if constant available
    if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
        mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, (bool)$verifyPeer);
    }
}

// Attempt connection (MYSQLI_CLIENT_SSL flag if SSL requested)
$clientFlags = 0;
if ($wantSSL) { $clientFlags |= MYSQLI_CLIENT_SSL; }
@mysqli_real_connect($conn, $servername, $username_db, $password_db, $dbname, $port, null, $clientFlags);

if (!$conn || $conn->connect_errno) {
    // Generic failure message (avoid leaking target host / user)
    die('Connection failed');
}

// Apply charset (ignore failure silently to avoid fatal in environments lacking charset)
@mysqli_set_charset($conn, $charset);

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