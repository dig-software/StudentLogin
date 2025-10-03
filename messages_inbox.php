<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "Access denied. Please <a href='login.html'>login</a> first.";
    exit();
}
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "class";
$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$username = $_SESSION['username'];
// Get all users who have sent or received messages with this user
$inbox_users = [];
$res = $conn->query("SELECT DISTINCT IF(sender='$username', recipient, sender) as other_user FROM messages WHERE sender='$username' OR recipient='$username'");
while ($row = $res->fetch_assoc()) {
    $inbox_users[] = $row['other_user'];
}
$selected_user = isset($_GET['user']) ? $_GET['user'] : (count($inbox_users) ? $inbox_users[0] : null);
$conversation = [];
if ($selected_user) {
    // Mark messages as read
    $conn->query("UPDATE messages SET is_read=1 WHERE sender='" . $conn->real_escape_string($selected_user) . "' AND recipient='" . $conn->real_escape_string($username) . "'");
    $stmt = $conn->prepare("SELECT sender, recipient, message, timestamp, is_read FROM messages WHERE (sender=? AND recipient=?) OR (sender=? AND recipient=?) ORDER BY timestamp ASC");
    $stmt->bind_param("ssss", $username, $selected_user, $selected_user, $username);
    $stmt->execute();
    $stmt->bind_result($sender, $recipient, $msg, $ts, $is_read);
    while ($stmt->fetch()) {
        $conversation[] = ["sender"=>$sender, "recipient"=>$recipient, "message"=>$msg, "timestamp"=>$ts, "is_read"=>$is_read];
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html><head><title>Inbox</title><style>
body { background:#e6f0ff; font-family:Segoe UI,Arial,sans-serif; }
.inbox-container { max-width:900px; margin:40px auto; background:#fff; border-radius:12px; box-shadow:0 2px 12px #1976d233; padding:28px; display:flex; gap:32px; }
.inbox-list { width:220px; border-right:1px solid #e0e0e0; padding-right:18px; }
.inbox-list h3 { color:#1976d2; margin-bottom:12px; }
.inbox-user { padding:10px 8px; border-radius:6px; margin-bottom:6px; cursor:pointer; background:#f5faff; transition:background 0.2s; }
.inbox-user.selected, .inbox-user:hover { background:#e3f2fd; }
.inbox-user b { color:#1976d2; }
.conv-area { flex:1; }
.conv-msg { margin-bottom:16px; padding:10px 14px; border-radius:8px; background:#f5faff; box-shadow:0 1px 4px #1976d211; }
.conv-me { background:#e3f2fd; }
.conv-meta { font-size:0.95em; color:#1976d2; margin-bottom:4px; }
.reply-form textarea { width:100%; min-height:60px; border-radius:6px; border:1px solid #b0bec5; padding:8px; }
.reply-form input[type=submit] { padding:8px 18px; border-radius:6px; border:1px solid #b0bec5; background:#1976d2; color:#fff; font-weight:500; cursor:pointer; }
</style></head><body>
<div class="inbox-container">
    <div class="inbox-list">
        <h3>Inbox</h3>
        <?php foreach($inbox_users as $u): ?>
            <div class="inbox-user<?php if($u==$selected_user) echo ' selected'; ?>" onclick="window.location='messages_inbox.php?user=<?php echo urlencode($u); ?>'">
                <b><?php echo htmlspecialchars($u); ?></b>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="conv-area">
        <?php if($selected_user): ?>
            <h3>Conversation with <?php echo htmlspecialchars($selected_user); ?></h3>
            <div id="conv-list">
            <?php foreach($conversation as $m): ?>
                <div class="conv-msg<?php if($m['sender']==$username) echo ' conv-me'; ?>">
                    <div class="conv-meta">
                        <b><?php echo htmlspecialchars($m['sender']); ?></b> @ <?php echo htmlspecialchars($m['timestamp']); ?>
                    </div>
                    <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                </div>
            <?php endforeach; ?>
            </div>
            <form class="reply-form" method="POST" action="messages_inbox.php?user=<?php echo urlencode($selected_user); ?>">
                <textarea name="message" required placeholder="Type your reply..."></textarea><br>
                <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($selected_user); ?>">
                <input type="submit" value="Send">
            </form>
        <?php else: ?>
            <div style="color:#888;">No conversations yet.</div>
        <?php endif; ?>
    </div>
</div>
<div style="text-align:center;margin-top:24px;"><a href="view.php" style="color:#1976d2;">&larr; Back to Profile</a></div>
</body></html>
