<?php
require_once 'db_connect.php';
if (!isset($_GET['group']) || !isset($_GET['exclude'])) {
    echo json_encode([]);
    exit();
}
$group_id = intval($_GET['group']);
$exclude = explode(',', $_GET['exclude']);
$exclude = array_map(function($u) use ($conn) { return "'".$conn->real_escape_string($u)."'"; }, $exclude);
$where = count($exclude) ? ("WHERE username NOT IN (".implode(',', $exclude).")") : '';
$res = $conn->query("SELECT username FROM registration $where");
$users = [];
while ($row = $res->fetch_assoc()) $users[] = $row['username'];
echo json_encode($users);
?>
