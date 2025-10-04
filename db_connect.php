<?php
// Centralized DB connection (MySQL / MariaDB) with flexible env & URL / JSON / base64 config parsing.
// Priority order for individual values:
// 1. Connection URL style env (DB_URL / DATABASE_URL / JAWSDB_URL / CLEARDB_DATABASE_URL)
// 2. Explicit DB_* env vars
// 3. Provider-specific MYSQL* (Railway / some hosts)
// 4. Local development defaults

// Optional .env file loader (only if file exists & not already loaded). Non-fatal.
if (!function_exists('studentlogin_load_env')) {
    @require_once __DIR__ . '/load_env.php';
    if (function_exists('studentlogin_load_env')) {
        studentlogin_load_env(__DIR__ . '/.env');
    }
}

// Support a single JSON blob (SKYSQL_CREDS) -> {"host":"...","port":4048,"user":"...","password":"...","db":"studentlogin"}
if (getenv('SKYSQL_CREDS') && !getenv('DB_HOST')) {
    $json = json_decode(getenv('SKYSQL_CREDS'), true);
    if (is_array($json)) {
        foreach ([
            'host' => 'DB_HOST', 'port' => 'DB_PORT', 'user' => 'DB_USER',
            'password' => 'DB_PASS', 'db' => 'DB_NAME'
        ] as $k => $envK) {
            if (isset($json[$k]) && getenv($envK) === false) {
                putenv($envK . '=' . $json[$k]);
            }
        }
        // Auto-enable SSL if JSON indicates or port looks like SkySQL TLS port (> 4000)
        if (!getenv('DB_SSL')) putenv('DB_SSL=1');
        if (!getenv('DB_SSL_VERIFY')) putenv('DB_SSL_VERIFY=1');
    }
}

// Support base64-app config (APP_CONFIG_B64) with key=value lines
if (getenv('APP_CONFIG_B64')) {
    $decoded = base64_decode(getenv('APP_CONFIG_B64'), true);
    if ($decoded !== false) {
        $lines = preg_split('/\r?\n/', $decoded);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && $line[0] !== '#') {
                [$k,$v] = array_map('trim', explode('=', $line, 2));
                if ($k !== '' && getenv($k) === false) { putenv($k.'='.$v); }
            }
        }
    }
}

// Optional connection URL (e.g. mysql://user:pass@host:4047/dbname?ssl-mode=REQUIRED)
$rawUrl = getenv('DB_URL') ?: (getenv('DATABASE_URL') ?: (getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL')));

// Base defaults (may be overridden by URL parsing or explicit vars)
$servername   = 'localhost';
$username_db  = 'root';
$password_db  = '';
$dbname       = 'class';
$port         = 3306;

if ($rawUrl) {
    // Support mysql:// and mariadb:// schemes; silently ignore parse errors.
    $parsed = @parse_url($rawUrl);
    if ($parsed && isset($parsed['host'])) {
        $servername  = $parsed['host'];
        if (isset($parsed['user'])) $username_db = $parsed['user'];
        if (isset($parsed['pass'])) $password_db = $parsed['pass'];
        if (isset($parsed['path'])) {
            $p = ltrim($parsed['path'], '/');
            if ($p !== '') $dbname = $p;
        }
        if (isset($parsed['port'])) $port = (int)$parsed['port'];
        // Extract query params (e.g., ssl-mode=REQUIRED)
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (isset($q['database']) && $q['database'] !== '') $dbname = $q['database'];
            // Provide minimal mapping for ssl requirements if present.
            if (isset($q['ssl-mode']) && strtoupper($q['ssl-mode']) !== 'DISABLED') {
                putenv('DB_SSL=1');
            }
        }
    }
}

// Override with explicit env vars if provided (these take precedence over URL parts)
$servername   = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: $servername);
$username_db  = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: $username_db);
$password_db  = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: $password_db);
$dbname       = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: $dbname);
$port         = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: $port));
$charset      = getenv('DB_CHARSET') ?: 'utf8mb4';

// Auto-correct case where someone pasted multiple KEY=VALUE pairs into DB_HOST causing hostname to include spaces, e.g.:
// "serverless-eastus.sysp0000.db3.skysql.com DB_PORT=4048 DB_USER=user DB_PASS=pass ..."
if (strpos($servername, ' DB_') !== false) {
    $parts = preg_split('/\s+/', trim($servername));
    if ($parts && count($parts) > 1) {
        $servername = array_shift($parts); // first token is the real host
        foreach ($parts as $tok) {
            if (strpos($tok, '=') !== false) {
                [$k,$v] = explode('=', $tok, 2);
                if (preg_match('/^DB_(HOST|PORT|USER|PASS|NAME|SSL|SSL_VERIFY|CHARSET)$/', $k)) {
                    // Only set if not already provided explicitly in environment
                    if (getenv($k) === false) { putenv($k.'='.$v); }
                    // Reflect any corrected critical values immediately
                    switch ($k) {
                        case 'DB_PORT': $port = (int)$v; break;
                        case 'DB_USER': $username_db = $v; break;
                        case 'DB_PASS': $password_db = $v; break;
                        case 'DB_NAME': $dbname = $v; break;
                    }
                }
            }
        }
    }
}

// Debug (non-production) if DB_DEBUG=1: emit minimal connection params (omit password)
if (getenv('DB_DEBUG') === '1') {
    error_log('[DB_DEBUG] host='.$servername.' port='.$port.' user='.$username_db.' db='.$dbname.' ssl='.(getenv('DB_SSL')?:'0'));
}

// Optional TLS / SSL flags (useful for managed MariaDB / SkySQL / PlanetScale / cloud DBs)
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