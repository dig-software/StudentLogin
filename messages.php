<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "Access denied. Please <a href='login.html'>login</a> first.";
    exit();
}
$conn = null;
require_once 'db_connect.php';
if (!$conn) die("Connection failed");
$username = $_SESSION['username'];
// Handle sending a message (reply form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send group message
    if (isset($_GET['group'], $_POST['group_message'])) {
        $group_id = intval($_GET['group']);
        $msg = trim($_POST['group_message']);
        if ($group_id && $msg) {
            $conn_gmsg = new mysqli($servername, $username_db, $password_db, $dbname);
            $stmt_gmsg = $conn_gmsg->prepare("INSERT INTO group_messages (group_id, sender, message) VALUES (?, ?, ?)");
            $stmt_gmsg->bind_param("iss", $group_id, $username, $msg);
            $stmt_gmsg->execute();
            $stmt_gmsg->close();
            $conn_gmsg->close();
            header("Location: messages.php?group=".$group_id);
            exit();
        }
    }
    // Handle group creation (with cover photo)
    if (isset($_POST['create_group'], $_POST['group_name'], $_POST['group_members']) && is_array($_POST['group_members'])) {
        $group_name = trim($_POST['group_name']);
        $members = $_POST['group_members'];
        $cover_photo = '';
        $upload_dir = 'uploads/';
        if (isset($_FILES['group_cover_photo']) && $_FILES['group_cover_photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['group_cover_photo']['tmp_name'];
            $original_name = basename($_FILES['group_cover_photo']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif'];
            if (in_array($ext, $allowed)) {
                $unique_name = uniqid('group_cover_', true) . '.' . $ext;
                $dest = $upload_dir . $unique_name;
                if (move_uploaded_file($tmp_name, $dest)) {
                    $cover_photo = $unique_name;
                }
            }
        }
        if ($group_name && count($members) > 0) {
            $conn_gc = new mysqli($servername, $username_db, $password_db, $dbname);
            if ($cover_photo) {
                $stmt_gc = $conn_gc->prepare("INSERT INTO groups (name, created_by, cover_photo) VALUES (?, ?, ?)");
                $stmt_gc->bind_param("sss", $group_name, $username, $cover_photo);
            } else {
                $stmt_gc = $conn_gc->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
                $stmt_gc->bind_param("ss", $group_name, $username);
            }
            $stmt_gc->execute();
            $group_id = $conn_gc->insert_id;
            $stmt_gc->close();
            // Add creator as member
            $stmt_gm = $conn_gc->prepare("INSERT INTO group_members (group_id, username) VALUES (?, ?)");
            $stmt_gm->bind_param("is", $group_id, $username);
            $stmt_gm->execute();
            // Add selected members
            foreach ($members as $m) {
                $stmt_gm->bind_param("is", $group_id, $m);
                $stmt_gm->execute();
            }
            $stmt_gm->close();
            $conn_gc->close();
            header("Location: messages.php?group=".$group_id);
            exit();
        }
    }
    // Handle file sharing
    if (isset($_POST['share_file']) && isset($_GET['user'])) {
        $recipient = $_GET['user'];
        $file_path = '';
        $file_type = '';
        $original_name = '';
        $from_profile = 0;
        $upload_dir = 'uploads/';
        if ($_POST['file_source'] === 'profile' && !empty($_POST['profile_video'])) {
            // Sharing from profile videos
            $file_path = $_POST['profile_video'];
            $file_type = 'video';
            $original_name = $_POST['profile_video'];
            $from_profile = 1;
        } elseif ($_POST['file_source'] === 'upload' && isset($_FILES['uploaded_file']) && $_FILES['uploaded_file']['error'] === UPLOAD_ERR_OK) {
            // Uploading a new file
            $tmp_name = $_FILES['uploaded_file']['tmp_name'];
            $original_name = basename($_FILES['uploaded_file']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['mp4','mov','avi','jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','xls','xlsx','txt'];
            if (in_array($ext, $allowed)) {
                $unique_name = uniqid('shared_', true) . '.' . $ext;
                $dest = $upload_dir . $unique_name;
                if (move_uploaded_file($tmp_name, $dest)) {
                    $file_path = $unique_name;
                    $file_type = $ext;
                }
            }
        }
        if ($file_path && $recipient) {
            $conn4 = new mysqli($servername, $username_db, $password_db, $dbname);
            $stmt = $conn4->prepare("INSERT INTO shared_files (sender, recipient, file_path, file_type, original_name, from_profile, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssssi", $username, $recipient, $file_path, $file_type, $original_name, $from_profile);
            $stmt->execute();
            $stmt->close();
            $conn4->close();
            // Optionally, send a message in the conversation
            $msg = ($from_profile ? "Shared a video from their profile." : "Shared a file: " . $original_name);
            $conn5 = new mysqli($servername, $username_db, $password_db, $dbname);
            $stmt2 = $conn5->prepare("INSERT INTO messages (sender, recipient, message) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $username, $recipient, $msg);
            $stmt2->execute();
            $stmt2->close();
            $conn5->close();
            header("Location: messages.php?user=".urlencode($recipient));
            exit();
        }
    }
    // Delete message
    if (isset($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);
        $stmt = $conn->prepare("DELETE FROM messages WHERE id=? AND sender=?");
        $stmt->bind_param("is", $delete_id, $username);
        $stmt->execute();
        $stmt->close();
        // Stay on same conversation
        if (isset($_GET['user'])) {
            header("Location: messages.php?user=".urlencode($_GET['user']));
        } else {
            header("Location: messages.php");
        }
        exit();
    }
    // Edit message
    if (isset($_POST['edit_id'], $_POST['edit_message'])) {
        $edit_id = intval($_POST['edit_id']);
        $edit_message = trim($_POST['edit_message']);
        if ($edit_message !== '') {
            $stmt = $conn->prepare("UPDATE messages SET message=? WHERE id=? AND sender=?");
            $stmt->bind_param("sis", $edit_message, $edit_id, $username);
            $stmt->execute();
            $stmt->close();
        }
        if (isset($_GET['user'])) {
            header("Location: messages.php?user=".urlencode($_GET['user']));
        } else {
            header("Location: messages.php");
        }
        exit();
    }
    // Send new message
    if (isset($_POST['recipient'], $_POST['message'])) {
        $recipient = $_POST['recipient'];
        $message = trim($_POST['message']);
        if ($recipient && $message) {
            $stmt = $conn->prepare("INSERT INTO messages (sender, recipient, message) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $recipient, $message);
            $stmt->execute();
            $stmt->close();
            // Redirect to avoid resubmission
            header("Location: messages.php?user=".urlencode($recipient));
            exit();
        }
    }
}
// Get all users who have sent or received messages with this user
$inbox_users = [];
$res = $conn->query("SELECT DISTINCT IF(sender='$username', recipient, sender) as other_user FROM messages WHERE sender='$username' OR recipient='$username'");
while ($row = $res->fetch_assoc()) {
    $inbox_users[] = $row['other_user'];
}
$selected_user = isset($_GET['user']) ? $_GET['user'] : (count($inbox_users) ? $inbox_users[0] : null);
$selected_group = isset($_GET['group']) ? intval($_GET['group']) : null;
$conversation = [];
if ($selected_group) {
    // Group chat logic
    $conn_gc = new mysqli($servername, $username_db, $password_db, $dbname);
    $stmt_gc = $conn_gc->prepare("SELECT gm.id, gm.sender, gm.message, gm.timestamp FROM group_messages gm WHERE gm.group_id=? ORDER BY gm.timestamp ASC");
    $stmt_gc->bind_param("i", $selected_group);
    $stmt_gc->execute();
    $stmt_gc->bind_result($msg_id, $sender, $msg, $ts);
    while ($stmt_gc->fetch()) {
        $conversation[] = ["id"=>$msg_id, "sender"=>$sender, "message"=>$msg, "timestamp"=>$ts];
    }
    $stmt_gc->close();
    $conn_gc->close();
} elseif ($selected_user) {
    // Mark messages as read
    $conn->query("UPDATE messages SET is_read=1 WHERE sender='" . $conn->real_escape_string($selected_user) . "' AND recipient='" . $conn->real_escape_string($username) . "'");
    $stmt = $conn->prepare("SELECT id, sender, recipient, message, timestamp, is_read FROM messages WHERE (sender=? AND recipient=?) OR (sender=? AND recipient=?) ORDER BY timestamp ASC");
    $stmt->bind_param("ssss", $username, $selected_user, $selected_user, $username);
    $stmt->execute();
    $stmt->bind_result($msg_id, $sender, $recipient, $msg, $ts, $is_read);
    while ($stmt->fetch()) {
        $conversation[] = ["id"=>$msg_id, "sender"=>$sender, "recipient"=>$recipient, "message"=>$msg, "timestamp"=>$ts, "is_read"=>$is_read];
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html><head><title>Messages</title><style>
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

/* Blur/hide effect for entire conversation area */
.conv-blur #conv-list, .conv-blur .reply-form { filter: blur(7px); pointer-events: none; user-select: none; }
.conv-blur #conv-list::after {
    content: 'Click to reveal conversation';
    position: absolute;
    left: 0; right: 0; top: 0; bottom: 0;
    display: flex; align-items: center; justify-content: center;
    color: #1976d2; font-weight: bold; font-size: 1.2em;
    background: rgba(255,255,255,0.7);
    pointer-events: all;
    z-index: 2;
}
.conv-area { position: relative; }

/* Blur/hide effect for messages */
.msg-blur > div:last-child {
    filter: blur(7px);
    cursor: pointer;
    position: relative;
}
.msg-blur > div:last-child::after {
    content: 'Click to reveal';
    position: absolute;
    left: 0; right: 0; top: 0; bottom: 0;
    display: flex; align-items: center; justify-content: center;
    color: #1976d2; font-weight: bold; font-size: 1.1em;
    background: rgba(255,255,255,0.7);
    pointer-events: none;
}
@media (max-width:700px) {
     .inbox-container { flex-direction:column; padding:10px 2vw; }
     .inbox-list { width:100%; border-right:none; border-bottom:1px solid #e0e0e0; padding-right:0; margin-bottom:18px; }
     .conv-area { padding:0; }
     .inbox-user { font-size:1em; padding:8px 4px; }
     .reply-form textarea { font-size:1em; }
     .reply-form input[type=submit] { font-size:1em; padding:7px 10px; }
}
</style></head><body>
<div class="inbox-container">
    <div class="inbox-list">
        <h3>Inbox</h3>
        <button id="createGroupBtn" style="width:90%;margin-bottom:10px;padding:6px 0;border-radius:6px;border:1px solid #1976d2;background:#fff;color:#1976d2;font-weight:500;cursor:pointer;">+ New Group</button>
        <form id="createGroupForm" method="POST" action="messages.php" enctype="multipart/form-data" style="display:none;background:#f5faff;padding:10px 12px;border-radius:8px;margin-bottom:10px;">
            <input type="text" name="group_name" placeholder="Group Name" required style="width:90%;margin-bottom:8px;padding:6px;border-radius:6px;border:1px solid #b0bec5;">
            <label style="font-size:0.95em;">Add Members:</label><br>
            <select name="group_members[]" multiple size="4" style="width:90%;margin-bottom:8px;padding:6px;border-radius:6px;border:1px solid #b0bec5;">
                <?php
                $conn_gm = new mysqli($servername, $username_db, $password_db, $dbname);
                $res_gm = $conn_gm->query("SELECT username FROM registration WHERE username != '".$conn_gm->real_escape_string($username)."'");
                while ($row_gm = $res_gm->fetch_assoc()) {
                    echo "<option value='".htmlspecialchars($row_gm['username'])."'>".htmlspecialchars($row_gm['username'])."</option>";
                }
                $conn_gm->close();
                ?>
            </select><br>
            <label style="font-size:0.95em;">Group Cover Photo (optional):</label><br>
            <input type="file" name="group_cover_photo" accept="image/*" style="margin-bottom:8px;"><br>
            <input type="submit" name="create_group" value="Create Group" style="padding:6px 14px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
            <button type="button" id="cancelCreateGroup" style="padding:6px 14px;border-radius:6px;border:1px solid #b0bec5;background:#eee;color:#1976d2;font-weight:500;">Cancel</button>
        </form>
        <script>
        document.getElementById('createGroupBtn').onclick = function() {
            document.getElementById('createGroupForm').style.display = 'block';
            this.style.display = 'none';
        };
        document.getElementById('cancelCreateGroup').onclick = function() {
            document.getElementById('createGroupForm').style.display = 'none';
            document.getElementById('createGroupBtn').style.display = '';
        };
        </script>
    <input type="text" id="inboxSearch" placeholder="Search users..." style="width:90%;padding:6px 8px;margin-bottom:10px;border-radius:6px;border:1px solid #b0bec5;">
        <form method="POST" style="margin-bottom:18px;">
            <select name="recipient" required style="width:80%;padding:6px;border-radius:6px;border:1px solid #b0bec5;">
                <option value="">Start New Conversation...</option>
                <?php
                // Fetch all users except self and those already in inbox
                $all_users = [];
                $conn2 = new mysqli($servername, $username_db, $password_db, $dbname);
                $res2 = $conn2->query("SELECT username FROM registration WHERE username != '" . $conn2->real_escape_string($username) . "'");
                while ($row2 = $res2->fetch_assoc()) {
                    if (!in_array($row2['username'], $inbox_users)) {
                        echo "<option value='".htmlspecialchars($row2['username'])."'>".htmlspecialchars($row2['username'])."</option>";
                    }
                }
                $conn2->close();
                ?>
            </select>
            <input type="text" name="message" placeholder="Type a message..." style="width:80%;margin-top:6px;padding:6px;border-radius:6px;border:1px solid #b0bec5;" required>
            <input type="submit" value="Send" style="padding:6px 14px;border-radius:6px;border:1px solid #b0bec5;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
        </form>
        <div style="margin-bottom:10px;"><b style="color:#1976d2;">Groups</b></div>
        <?php
        // List groups the user is a member of
        $groups = [];
        $conn_groups = new mysqli($servername, $username_db, $password_db, $dbname);
        $res_groups = $conn_groups->query("SELECT g.id, g.name, g.cover_photo FROM groups g JOIN group_members gm ON g.id=gm.group_id WHERE gm.username='".$conn_groups->real_escape_string($username)."'");
        while ($rowg = $res_groups->fetch_assoc()) {
            $groups[] = $rowg;
        }
        $conn_groups->close();
        foreach($groups as $g): ?>
            <div class="inbox-user" data-groupid="<?php echo $g['id']; ?>" onclick="window.location='group_chat.php?group=<?php echo $g['id']; ?>'">
                <?php if (!empty($g['cover_photo'])): ?>
                    <span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:#e0e0e0;vertical-align:middle;overflow:hidden;margin-right:8px;text-align:center;line-height:32px;font-size:18px;color:#1976d2;">
                        <img src="uploads/<?php echo htmlspecialchars($g['cover_photo']); ?>" style="width:32px;height:32px;object-fit:cover;border-radius:50%;">
                    </span>
                <?php else: ?>
                    <span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:#e0e0e0;vertical-align:middle;overflow:hidden;margin-right:8px;text-align:center;line-height:32px;font-size:18px;color:#1976d2;">👥</span>
                <?php endif; ?>
                <b><?php echo htmlspecialchars($g['name']); ?></b>
            </div>
        <?php endforeach; ?>
        <div style="margin-bottom:10px;"></div>
        <?php
        // Fetch last_active for all inbox users
        $user_status = [];
        if (count($inbox_users)) {
            $conn_status = new mysqli($servername, $username_db, $password_db, $dbname);
            $inbox_usernames = array_map(function($x) use ($conn_status) { return "'".$conn_status->real_escape_string($x)."'"; }, $inbox_users);
            $res_status = $conn_status->query("SELECT username, last_active FROM registration WHERE username IN (".implode(",", $inbox_usernames).")");
            while ($row = $res_status->fetch_assoc()) {
                $user_status[$row['username']] = $row['last_active'];
            }
            $conn_status->close();
        }
        // Fetch unread counts for each user
        $unread_counts = [];
        if (count($inbox_users)) {
            $conn_unread = new mysqli($servername, $username_db, $password_db, $dbname);
            $inbox_usernames = array_map(function($x) use ($conn_unread) { return "'".$conn_unread->real_escape_string($x)."'"; }, $inbox_users);
            $res_unread = $conn_unread->query("SELECT sender, COUNT(*) as cnt FROM messages WHERE recipient='".$conn_unread->real_escape_string($username)."' AND is_read=0 AND sender IN (".implode(",", $inbox_usernames).") GROUP BY sender");
            while ($row = $res_unread->fetch_assoc()) {
                $unread_counts[$row['sender']] = (int)$row['cnt'];
            }
            $conn_unread->close();
        }
        foreach($inbox_users as $u):
            $last_active = isset($user_status[$u]) ? strtotime($user_status[$u]) : 0;
            $is_online = $last_active && (time() - $last_active < 300); // 5 min
            $unread = isset($unread_counts[$u]) ? $unread_counts[$u] : 0;
        ?>
            <div class="inbox-user<?php if($u==$selected_user) echo ' selected'; ?>" data-username="<?php echo htmlspecialchars($u); ?>" onclick="window.location='messages.php?user=<?php echo urlencode($u); ?>'">
                <span class="inbox-avatar" style="display:inline-block;width:32px;height:32px;border-radius:50%;background:#e0e0e0;vertical-align:middle;overflow:hidden;margin-right:8px;position:relative;">
                    <?php if ($unread > 0): ?>
                        <span style="position:absolute;top:-4px;right:-4px;background:#d81b60;color:#fff;border-radius:50%;padding:2px 7px;font-size:0.85em;z-index:2;box-shadow:0 1px 4px #d81b6022;">+<?php echo $unread; ?></span>
                    <?php endif; ?>
                </span>
                <a href="profile.php?user=<?php echo urlencode($u); ?>" target="_blank" style="text-decoration:none;color:inherit;" onclick="event.stopPropagation();"><b><?php echo htmlspecialchars($u); ?></b></a>
                <?php if ($is_online): ?>
                    <span title="Online" style="display:inline-block;width:10px;height:10px;background:#43a047;border-radius:50%;margin-left:7px;vertical-align:middle;"></span>
                <?php elseif ($last_active): ?>
                    <span title="Last seen: <?php echo date('M d, H:i', $last_active); ?>" style="display:inline-block;width:10px;height:10px;background:#b0bec5;border-radius:50%;margin-left:7px;vertical-align:middle;"></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
</div>
<script>
// Inbox search filter
document.getElementById('inboxSearch').addEventListener('input', function() {
    var val = this.value.toLowerCase();
    document.querySelectorAll('.inbox-user').forEach(function(div) {
        var u = div.getAttribute('data-username').toLowerCase();
        div.style.display = u.includes(val) ? '' : 'none';
    });
});
// Fetch and display profile pics in the inbox
fetch('_get_profile_pics.php').then(r=>r.json()).then(function(pics){
        document.querySelectorAll('.inbox-user').forEach(function(div){
                var u = div.getAttribute('data-username');
                var pic = pics[u];
                var avatar = div.querySelector('.inbox-avatar');
                        if(pic) {
                                avatar.innerHTML = '<a href="profile.php?user='+encodeURIComponent(u)+'" target="_blank" tabindex="-1"><img src="uploads/'+encodeURIComponent(pic)+'" style="width:32px;height:32px;object-fit:cover;border-radius:50%;"></a>';
                        } else {
                                avatar.innerHTML = '<a href="profile.php?user='+encodeURIComponent(u)+'" target="_blank" tabindex="-1"><span style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;font-size:18px;color:#888;">👤</span></a>';
                        }
        });
});
// Always blur conversation on load
document.addEventListener('DOMContentLoaded', function() {
    var area = document.getElementById('conv-area');
    if(area) area.classList.add('conv-blur');
});
// Re-blur conversation when switching users
document.querySelectorAll('.inbox-user').forEach(function(div){
    div.addEventListener('click', function(){
        setTimeout(function(){
            var area = document.getElementById('conv-area');
            if(area) area.classList.add('conv-blur');
        }, 100);
    });
});
</script>
    </div>
    <div class="conv-area conv-blur" id="conv-area" onclick="this.classList.remove('conv-blur');">
        <?php if($selected_group): ?>
            <div style="color:#888;">Group chat now opens in a separate page.<br><a href="group_chat.php?group=<?php echo intval($selected_group); ?>" style="color:#1976d2;">Open Group Chat &rarr;</a></div>
        <?php elseif($selected_user): ?>
            <h3>Conversation with <?php echo htmlspecialchars($selected_user); ?></h3>
            <button id="shareFileBtn" style="margin-bottom:16px;padding:7px 16px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">Share File</button>
            <form id="shareFileForm" method="POST" action="messages.php?user=<?php echo urlencode($selected_user); ?>" enctype="multipart/form-data" style="display:none;margin-bottom:18px;background:#f5faff;padding:14px 18px;border-radius:8px;">
                <b>Share a file with <?php echo htmlspecialchars($selected_user); ?>:</b><br><br>
                <label><input type="radio" name="file_source" value="profile" checked> From My Videos</label>
                <label style="margin-left:18px;"><input type="radio" name="file_source" value="upload"> Upload New File</label><br><br>
                <div id="profileVideosBlock">
                    <select name="profile_video" style="width:80%;padding:6px;border-radius:6px;border:1px solid #b0bec5;">
                        <option value="">Select a video...</option>
                        <?php
                        $conn3 = new mysqli($servername, $username_db, $password_db, $dbname);
                        $vids = $conn3->query("SELECT video_filename FROM user_videos WHERE username='" . $conn3->real_escape_string($username) . "'");
                        while ($rowv = $vids->fetch_assoc()) {
                            echo "<option value='".htmlspecialchars($rowv['video_filename'])."'>".htmlspecialchars($rowv['video_filename'])."</option>";
                        }
                        $conn3->close();
                        ?>
                    </select>
                </div>
                <div id="uploadBlock" style="display:none;">
                    <input type="file" name="uploaded_file" accept="video/*,image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt" style="margin-bottom:8px;">
                </div>
                <input type="submit" name="share_file" value="Share" style="padding:6px 18px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
                <button type="button" id="cancelShareFile" style="padding:6px 18px;border-radius:6px;border:1px solid #b0bec5;background:#eee;color:#1976d2;font-weight:500;">Cancel</button>
            </form>
            <script>
            document.getElementById('shareFileBtn').onclick = function() {
                document.getElementById('shareFileForm').style.display = 'block';
                this.style.display = 'none';
            };
            document.getElementById('cancelShareFile').onclick = function() {
                document.getElementById('shareFileForm').style.display = 'none';
                document.getElementById('shareFileBtn').style.display = '';
            };
            document.querySelectorAll('input[name="file_source"]').forEach(function(radio) {
                radio.onchange = function() {
                    if (this.value === 'profile') {
                        document.getElementById('profileVideosBlock').style.display = '';
                        document.getElementById('uploadBlock').style.display = 'none';
                    } else {
                        document.getElementById('profileVideosBlock').style.display = 'none';
                        document.getElementById('uploadBlock').style.display = '';
                    }
                };
            });
            </script>
            <?php foreach($conversation as $m): ?>
                <div class="conv-msg<?php if($m['sender']==$username) echo ' conv-me'; ?>">
                    <div class="conv-meta">
                        <b><?php echo ($m['sender'] == $username) ? 'you' : htmlspecialchars($m['sender']); ?></b> @ <?php echo htmlspecialchars($m['timestamp']); ?>
                        <?php if($m['sender']==$username): ?>
                            <button type="button" class="edit-btn" data-id="<?php echo $m['id']; ?>" style="margin-left:10px;font-size:0.9em;">Edit</button>
                            <form method="POST" action="messages.php?user=<?php echo urlencode($selected_user); ?>" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" onclick="return confirm('Delete this message?');" style="font-size:0.9em;color:#d81b60;background:none;border:none;cursor:pointer;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="msg-content" data-id="<?php echo $m['id']; ?>"><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                    <form method="POST" class="edit-form" action="messages.php?user=<?php echo urlencode($selected_user); ?>" style="display:none;margin-top:6px;">
                        <input type="hidden" name="edit_id" value="<?php echo $m['id']; ?>">
                        <textarea name="edit_message" style="width:90%;min-height:40px;border-radius:6px;border:1px solid #b0bec5;padding:6px;"><?php echo htmlspecialchars($m['message']); ?></textarea>
                        <button type="submit" style="padding:4px 12px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;">Save</button>
                        <button type="button" class="cancel-edit" style="padding:4px 12px;border-radius:6px;border:1px solid #b0bec5;background:#eee;color:#1976d2;font-weight:500;">Cancel</button>
                    </form>
                </div>
            <?php endforeach; ?>
</style></head><body>
<script>
// Inline edit logic
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var msgDiv = btn.closest('.conv-msg');
            msgDiv.querySelector('.msg-content').style.display = 'none';
            msgDiv.querySelector('.edit-form').style.display = 'block';
        });
    });
    document.querySelectorAll('.cancel-edit').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var msgDiv = btn.closest('.conv-msg');
            msgDiv.querySelector('.msg-content').style.display = '';
            msgDiv.querySelector('.edit-form').style.display = 'none';
        });
    });
});
</script>
            </div>
            <form class="reply-form" method="POST" action="messages.php?user=<?php echo urlencode($selected_user); ?>">
                <textarea name="message" required placeholder="Type your reply..."></textarea><br>
                <input type="hidden" name="recipient" value="<?php echo htmlspecialchars($selected_user); ?>">
                <input type="submit" value="Send">
            </form>
        <?php else: ?>
            <div style="color:#888;">No conversations yet.</div>
        <?php endif; ?>
    </div>
<script>
// Always blur conversation on load and when switching users
document.addEventListener('DOMContentLoaded', function() {
  var area = document.getElementById('conv-area');
  if(area) area.classList.add('conv-blur');
});
</script>
</div>
<div style="text-align:center;margin-top:24px;"><a href="view.php" style="color:#1976d2;">&larr; Back to Profile</a></div>
</body></html>
