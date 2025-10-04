<?php
// Simple redirect so hitting the root goes to login page.
// Attempt schema bootstrap silently (in case this is first run on host like Render).
require_once __DIR__ . '/db_connect.php';
@require_once __DIR__ . '/bootstrap_schema.php';
header('Location: login.html');
exit();
