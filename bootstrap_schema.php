<?php
// bootstrap_schema.php
// Idempotent schema bootstrap for first-run environments (e.g., Render) when DB is empty.
// Safe to include after db_connect.php. Skips creation if tables already exist.

if (!isset($conn) || !$conn instanceof mysqli) {
    return; // No connection available.
}

$neededTables = [
    'registration', 'login', 'webauthn_credentials', 'user_videos',
    'password_reset_codes', 'messages', 'group_join_requests', 'group_members'
];

// Quick existence check: assume bootstrap required if first core table absent.
$res = $conn->query("SHOW TABLES LIKE 'registration'");
if ($res && $res->num_rows > 0) {
    return; // Already initialized.
}

$schemaSql = file_get_contents(__DIR__ . '/schema.sql');
if ($schemaSql === false) {
    error_log('bootstrap_schema: schema.sql not readable');
    return;
}

// Split on semicolons while preserving statements; naive but fine for this controlled schema file.
$statements = array_filter(array_map('trim', explode(';', $schemaSql)), function($s){return $s !== '' && stripos($s, 'create table') === 0;});

foreach ($statements as $stmt) {
    try {
        $conn->query($stmt);
    } catch (Throwable $e) {
        error_log('bootstrap_schema error: ' . $e->getMessage());
    }
}

?>