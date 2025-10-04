<?php
// Helper: generate_skysql_creds.php
// Outputs a SKYSQL_CREDS JSON blob based on currently set DB_* variables.
// Usage (local): php generate_skysql_creds.php
// Then copy the JSON into your Render environment as the single variable SKYSQL_CREDS.

$map = [
  'DB_HOST' => 'host',
  'DB_PORT' => 'port',
  'DB_USER' => 'user',
  'DB_PASS' => 'password',
  'DB_NAME' => 'db'
];

$out = [];
foreach ($map as $env => $key) {
    $val = getenv($env);
    if ($val === false || $val === '') {
        fwrite(STDERR, "Warning: $env not set; provide it or pass manually.\n");
    } else {
        // Cast port to int if numeric
        if ($key === 'port') {
            $out[$key] = (int)$val;
        } else {
            $out[$key] = $val;
        }
    }
}

if (!isset($out['host'])) { fwrite(STDERR, "Error: DB_HOST missing.\n"); }
if (!isset($out['user'])) { fwrite(STDERR, "Error: DB_USER missing.\n"); }
if (!isset($out['password'])) { fwrite(STDERR, "Error: DB_PASS missing.\n"); }
if (!isset($out['db'])) { fwrite(STDERR, "Error: DB_NAME missing.\n"); }

echo json_encode($out, JSON_UNESCAPED_SLASHES) . PHP_EOL;
?>
