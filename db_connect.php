<?php
// Clean, simple database connection (fully reverted to minimal form)
$servername   = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$username_db  = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$password_db  = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '');
$dbname       = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'class');
$port         = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));

$conn = @new mysqli($servername, $username_db, $password_db, $dbname, $port);
if ($conn->connect_error) {
    die('Connection failed');
}
?>