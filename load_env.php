<?php
// Minimal .env loader (no external dependencies). Lines: KEY=VALUE, ignores # comments.
if (!function_exists('studentlogin_load_env')) {
    function studentlogin_load_env(string $path): void {
        if (!is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            [$k,$v] = explode('=', $line, 2);
            $k = trim($k); $v = trim($v);
            if ($k === '') continue;
            if (getenv($k) === false) { putenv($k.'='.$v); }
        }
    }
}
?>
