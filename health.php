<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';
$ok = true;
try {
    if (!$conn || $conn->connect_error) { $ok = false; }
    else { $res = $conn->query('SELECT 1'); if(!$res) $ok = false; }
} catch (Throwable $e) { $ok = false; }
echo json_encode(['status' => $ok ? 'up' : 'down']);
?>
<?php
// Lightweight health / readiness probe.
header('Content-Type: application/json');
$status = [
  'status' => 'ok',
  'time'   => gmdate('c')
];

// Try DB connection (non-fatal if it fails, but report)
try {
    require_once __DIR__ . '/db_connect.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $res = $conn->query('SELECT 1');
        $status['db'] = $res ? 'up' : 'query-failed';
    } else {
        $status['db'] = 'no-conn';
    }
} catch (Throwable $e) {
    $status['db'] = 'error';
}

echo json_encode($status);
?>