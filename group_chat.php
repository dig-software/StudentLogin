
<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "Access denied. Please <a href='login.html'>login</a> first.";
    exit();
}
require_once 'db_connect.php';
if (!$conn) die("Connection failed");
$username = $_SESSION['username'];
$group_id = isset($_GET['group']) ? intval($_GET['group']) : 0;
if (!$group_id) {
    echo "Invalid group.";
    exit();
}

// Fetch group info and check membership
$stmt = $conn->prepare("SELECT name, created_by, cover_photo, allow_member_file_sharing, group_wallpaper, IFNULL(hold_files,0), group_password FROM groups WHERE id=?");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$stmt->bind_result($group_name, $created_by, $cover_photo, $allow_member_file_sharing, $group_wallpaper, $hold_files, $group_password_hash);
if (!$stmt->fetch()) {
    echo "Group not found.";
    exit();
}
$stmt->close();

$is_admin = ($username === $created_by);

// --- Group password authentication for non-admins ---
if (!$is_admin) {
    if (!isset($_SESSION['group_auth'])) $_SESSION['group_auth'] = [];
    $auth_key = (string)$group_id;
    $needs_auth = !isset($_SESSION['group_auth'][$auth_key]) || $_SESSION['group_auth'][$auth_key] !== true;
    if ($needs_auth) {
        $show_pwd_form = true;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_pwd_auth'])) {
            $input_pwd = $_POST['group_pwd_auth'];
            if (password_verify($input_pwd, $group_password_hash)) {
                $_SESSION['group_auth'][$auth_key] = true;
                header("Location: group_chat.php?group=".$group_id);
                exit();
            } else {
                $pwd_error = "Incorrect group password.";
            }
        }
        // Show password form and exit
        ?>
        <!DOCTYPE html>
        <html><head><title>Group Authentication</title>
        <style>
        body { background:#e6f0ff; font-family:Segoe UI,Arial,sans-serif; }
        .auth-container { max-width:400px; margin:80px auto; background:#fff; border-radius:12px; box-shadow:0 2px 12px #1976d233; padding:32px; }
        .auth-title { font-size:1.3em; color:#1976d2; margin-bottom:18px; }
        .auth-error { color:#d81b60; margin-bottom:10px; }
        </style>
        </head><body>
        <div class="auth-container">
            <div class="auth-title">Enter Group Password</div>
            <?php if (!empty($pwd_error)): ?><div class="auth-error"><?php echo htmlspecialchars($pwd_error); ?></div><?php endif; ?>
            <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>">
                <input type="password" name="group_pwd_auth" placeholder="Group Password" required style="width:100%;padding:8px;margin-bottom:12px;border-radius:6px;border:1px solid #b0bec5;">
                <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;width:100%;">Authenticate</button>
            </form>
        </div>
        </body></html>
        <?php
        exit();
    }
}

// Admin: Set or change group password (must be after group info is fetched)
if ($is_admin && isset($_POST['set_group_password']) && isset($_POST['new_group_password'])) {
    $new_pwd = trim($_POST['new_group_password']);
    if ($new_pwd !== '') {
        $pwd_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE groups SET group_password=? WHERE id=?");
        $stmt->bind_param("si", $pwd_hash, $group_id);
        $stmt->execute();
        $stmt->close();
        $group_password_hash = $pwd_hash;
        $success_msg = "Group password updated.";
        // Send new password to all group members (except admin)
        $stmt_members = $conn->prepare("SELECT username FROM group_members WHERE group_id=? AND username<>?");
        $stmt_members->bind_param("is", $group_id, $username);
        $stmt_members->execute();
        $stmt_members->bind_result($member_user);
        $recipients = [];
        while ($stmt_members->fetch()) {
            $recipients[] = $member_user;
        }
        $stmt_members->close();
        if (!empty($recipients)) {
            $stmt_msg = $conn->prepare("INSERT INTO messages (sender, recipient, message) VALUES (?, ?, ?)");
            foreach ($recipients as $to_user) {
                $msg = "The group password has been updated. New password: " . $new_pwd;
                $stmt_msg->bind_param("sss", $username, $to_user, $msg);
                $stmt_msg->execute();
            }
            $stmt_msg->close();
        }
    }
}

// Admin: Handle join request approval/denial (with password prompt on approve)
if ($is_admin && isset($_POST['handle_join_request']) && isset($_POST['request_user']) && isset($_POST['request_action'])) {
    $req_user = $_POST['request_user'];
    $action = $_POST['request_action']; // 'approve' or 'deny'
    // Only process if request is still pending
    $stmt = $conn->prepare("SELECT status FROM group_join_requests WHERE group_id=? AND username=? ORDER BY requested_at DESC LIMIT 1");
    $stmt->bind_param("is", $group_id, $req_user);
    $stmt->execute();
    $stmt->bind_result($req_status);
    if ($stmt->fetch() && $req_status === 'pending') {
        $stmt->close();
        if ($action === 'approve') {
            // If password not submitted yet, show prompt
            if (!isset($_POST['send_group_password'])) {
                ?>
                <!DOCTYPE html>
                <html><head><title>Send Group Password</title>
                <style>
                body { background:#e6f0ff; font-family:Segoe UI,Arial,sans-serif; }
                .auth-container { max-width:400px; margin:80px auto; background:#fff; border-radius:12px; box-shadow:0 2px 12px #1976d233; padding:32px; }
                .auth-title { font-size:1.3em; color:#1976d2; margin-bottom:18px; }
                .auth-error { color:#d81b60; margin-bottom:10px; }
                </style>
                </head><body>
                <div class="auth-container">
                    <div class="auth-title">Enter Group Password to Send to User</div>
                    <?php if (!empty($pwd_error)): ?><div class="auth-error"><?php echo htmlspecialchars($pwd_error); ?></div><?php endif; ?>
                    <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>">
                        <input type="hidden" name="handle_join_request" value="1">
                        <input type="hidden" name="request_user" value="<?php echo htmlspecialchars($req_user); ?>">
                        <input type="hidden" name="request_action" value="approve">
                        <input type="hidden" name="send_group_password" value="1">
                        <input type="password" name="plain_group_password" placeholder="Group Password" required style="width:100%;padding:8px;margin-bottom:12px;border-radius:6px;border:1px solid #b0bec5;">
                        <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;width:100%;">Send Password</button>
                    </form>
                </div>
                </body></html>
                <?php
                exit();
            }
            // Validate password entered by admin
            $plain_pwd = $_POST['plain_group_password'];
            $stmt_pwd = $conn->prepare("SELECT group_password FROM groups WHERE id=?");
            $stmt_pwd->bind_param("i", $group_id);
            $stmt_pwd->execute();
            $stmt_pwd->bind_result($group_pwd_hash);
            if ($stmt_pwd->fetch() && password_verify($plain_pwd, $group_pwd_hash)) {
                $stmt_pwd->close();
                // Add to group_members if not already
                $stmt_check = $conn->prepare("SELECT 1 FROM group_members WHERE group_id=? AND username=?");
                $stmt_check->bind_param("is", $group_id, $req_user);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows === 0) {
                    $stmt_check->close();
                    $stmt_add = $conn->prepare("INSERT INTO group_members (group_id, username) VALUES (?, ?)");
                    $stmt_add->bind_param("is", $group_id, $req_user);
                    $stmt_add->execute();
                    $stmt_add->close();
                } else { $stmt_check->close(); }
                // Update join request status
                $stmt_upd = $conn->prepare("UPDATE group_join_requests SET status='approved' WHERE group_id=? AND username=? AND status='pending'");
                $stmt_upd->bind_param("is", $group_id, $req_user);
                $stmt_upd->execute();
                $stmt_upd->close();
                // Send group password to user's inbox (messages table)
                $stmt_msg = $conn->prepare("INSERT INTO messages (sender, recipient, message) VALUES (?, ?, ?)");
                $msg = "Your request to join the group has been approved. Group password: " . $plain_pwd;
                $stmt_msg->bind_param("sss", $username, $req_user, $msg);
                $stmt_msg->execute();
                $stmt_msg->close();
            } else {
                if ($stmt_pwd) $stmt_pwd->close();
                $pwd_error = "Incorrect password. Please enter the current group password.";
                // Redisplay the form with error
                ?>
                <!DOCTYPE html>
                <html><head><title>Send Group Password</title>
                <style>
                body { background:#e6f0ff; font-family:Segoe UI,Arial,sans-serif; }
                .auth-container { max-width:400px; margin:80px auto; background:#fff; border-radius:12px; box-shadow:0 2px 12px #1976d233; padding:32px; }
                .auth-title { font-size:1.3em; color:#1976d2; margin-bottom:18px; }
                .auth-error { color:#d81b60; margin-bottom:10px; }
                </style>
                </head><body>
                <div class="auth-container">
                    <div class="auth-title">Enter Group Password to Send to User</div>
                    <div class="auth-error"><?php echo htmlspecialchars($pwd_error); ?></div>
                    <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>">
                        <input type="hidden" name="handle_join_request" value="1">
                        <input type="hidden" name="request_user" value="<?php echo htmlspecialchars($req_user); ?>">
                        <input type="hidden" name="request_action" value="approve">
                        <input type="hidden" name="send_group_password" value="1">
                        <input type="password" name="plain_group_password" placeholder="Group Password" required style="width:100%;padding:8px;margin-bottom:12px;border-radius:6px;border:1px solid #b0bec5;">
                        <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;width:100%;">Send Password</button>
                    </form>
                </div>
                </body></html>
                <?php
                exit();
            }
        } elseif ($action === 'deny') {
            $stmt_upd = $conn->prepare("UPDATE group_join_requests SET status='denied' WHERE group_id=? AND username=? AND status='pending'");
            $stmt_upd->bind_param("is", $group_id, $req_user);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
    } else { if ($stmt) $stmt->close(); }
    // Refresh to avoid resubmission
    header("Location: group_chat.php?group=".$group_id);
    exit();
}
// Admin: Fetch pending join requests for this group
$pending_requests = [];
if ($is_admin) {
    $stmt = $conn->prepare("SELECT username, requested_at FROM group_join_requests WHERE group_id=? AND status='pending' ORDER BY requested_at ASC");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $stmt->bind_result($req_user, $req_time);
    while ($stmt->fetch()) {
        $pending_requests[] = ["username"=>$req_user, "requested_at"=>$req_time];
    }
    $stmt->close();
}

// Admin toggles global hold_files
if ($is_admin && isset($_POST['toggle_hold_files'])) {
    $new_hold = ($_POST['toggle_hold_files'] == '1') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE groups SET hold_files=? WHERE id=?");
    $stmt->bind_param("ii", $new_hold, $group_id);
    $stmt->execute();
    $stmt->close();
    $hold_files = $new_hold;
}
// Admin toggles per-user hold
if ($is_admin && isset($_POST['toggle_user_hold']) && isset($_POST['user_hold_val'])) {
    $user = $_POST['toggle_user_hold'];
    $val = ($_POST['user_hold_val'] == '1') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE group_members SET hold_files=? WHERE group_id=? AND username=?");
    $stmt->bind_param("iis", $val, $group_id, $user);
    $stmt->execute();
    $stmt->close();
}
// Admin toggles per-file hold (shared file)
if ($is_admin && isset($_POST['toggle_file_hold'])) {
    $file_id = intval($_POST['toggle_file_hold']);
    $val = ($_POST['file_hold_val'] == '1') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE group_shared_files SET is_held=? WHERE id=? AND group_id=?");
    $stmt->bind_param("iii", $val, $file_id, $group_id);
    $stmt->execute();
    $stmt->close();
}
// Admin toggles per-voice-note hold (group message)
if ($is_admin && isset($_POST['toggle_voice_hold'])) {
    $msg_id = intval($_POST['toggle_voice_hold']);
    $val = ($_POST['voice_hold_val'] == '1') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE group_messages SET is_held=? WHERE id=? AND group_id=?");
    $stmt->bind_param("iii", $val, $msg_id, $group_id);
    $stmt->execute();
    $stmt->close();
}

// Handle delete voice note (message)
if (isset($_POST['delete_voice_note_msg'])) {
    $msg_id = intval($_POST['delete_voice_note_msg']);
    // Fetch message info
    $stmt = $conn->prepare("SELECT sender, message, is_held FROM group_messages WHERE id=? AND group_id=?");
    $stmt->bind_param("ii", $msg_id, $group_id);
    $stmt->execute();
    $stmt->bind_result($msg_sender, $msg_text, $msg_held);
    if ($stmt->fetch()) {
        // Check per-user hold
        $stmt->close();
        $stmt = $conn->prepare("SELECT hold_files FROM group_members WHERE group_id=? AND username=?");
        $stmt->bind_param("is", $group_id, $msg_sender);
        $stmt->execute();
        $stmt->bind_result($user_hold);
        $stmt->fetch();
        $stmt->close();
        $can_delete = $is_admin || ($msg_sender === $username && !$msg_held && !$user_hold && !$hold_files);
        if ($can_delete && strpos($msg_text, '[voice_note]') === 0) {
            $vn_file = substr($msg_text, 12);
            @unlink('uploads/' . $vn_file);
            $stmt = $conn->prepare("DELETE FROM group_messages WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $msg_id, $group_id);
            $stmt->execute();
            $stmt->close();
            header("Location: group_chat.php?group=".$group_id);
            exit();
        }
    } else { if ($stmt) $stmt->close(); }
}

// Handle delete group shared file
if (isset($_POST['delete_group_file'])) {
    $file_id = intval($_POST['delete_group_file']);
    $stmt = $conn->prepare("SELECT uploader, file_path, is_held FROM group_shared_files WHERE id=? AND group_id=?");
    $stmt->bind_param("ii", $file_id, $group_id);
    $stmt->execute();
    $stmt->bind_result($uploader, $file_path, $file_held);
    if ($stmt->fetch()) {
        // Check per-user hold
        $stmt->close();
        $stmt = $conn->prepare("SELECT hold_files FROM group_members WHERE group_id=? AND username=?");
        $stmt->bind_param("is", $group_id, $uploader);
        $stmt->execute();
        $stmt->bind_result($user_hold);
        $stmt->fetch();
        $stmt->close();
        $can_delete = $is_admin || ($uploader === $username && !$file_held && !$user_hold && !$hold_files);
        if ($can_delete) {
            @unlink('uploads/' . $file_path);
            $stmt = $conn->prepare("DELETE FROM group_shared_files WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $file_id, $group_id);
            $stmt->execute();
            $stmt->close();
            header("Location: group_chat.php?group=".$group_id);
            exit();
        }
    } else { if ($stmt) $stmt->close(); }
}

// Admin toggles voice note privilege for a member
if ($is_admin && isset($_POST['toggle_voice_note_user']) && isset($_POST['voice_note_val'])) {
    $toggle_user = $_POST['toggle_voice_note_user'];
    $voice_note_val = ($_POST['voice_note_val'] == '1') ? 1 : 0;
    // Only allow toggling for group members (not admin themselves)
    if ($toggle_user !== $created_by) {
        $stmt = $conn->prepare("UPDATE group_members SET can_voice_note=? WHERE group_id=? AND username=?");
        $stmt->bind_param("iis", $voice_note_val, $group_id, $toggle_user);
        $stmt->execute();
        $stmt->close();
    }
    // Refresh to reflect change
    header("Location: group_chat.php?group=".$group_id);
    exit();
}
// Handle group wallpaper upload (admin only)
if ($is_admin && isset($_POST['set_wallpaper']) && isset($_FILES['wallpaper_file']) && $_FILES['wallpaper_file']['error'] === UPLOAD_ERR_OK) {
    $tmp_name = $_FILES['wallpaper_file']['tmp_name'];
    $original_name = basename($_FILES['wallpaper_file']['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif'];
    $upload_dir = 'uploads/';
    if (in_array($ext, $allowed)) {
        $unique_name = uniqid('groupwall_', true) . '.' . $ext;
        $dest = $upload_dir . $unique_name;
        if (move_uploaded_file($tmp_name, $dest)) {
            $stmt = $conn->prepare("UPDATE groups SET group_wallpaper=? WHERE id=?");
            $stmt->bind_param("si", $unique_name, $group_id);
            $stmt->execute();
            $stmt->close();
            $group_wallpaper = $unique_name;
        }
    }
}
// Admin toggles member file sharing
if ($is_admin && isset($_POST['toggle_file_sharing'])) {
    $new_val = ($_POST['toggle_file_sharing'] == '1') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE groups SET allow_member_file_sharing=? WHERE id=?");
    $stmt->bind_param("ii", $new_val, $group_id);
    $stmt->execute();
    $stmt->close();
    $allow_member_file_sharing = $new_val;
}
// Remove group file (admin only)
if ($is_admin && isset($_POST['remove_group_file'])) {
    $file_id = intval($_POST['remove_group_file']);
    $stmt = $conn->prepare("SELECT file_path FROM group_shared_files WHERE id=? AND group_id=?");
    $stmt->bind_param("ii", $file_id, $group_id);
    $stmt->execute();
    $stmt->bind_result($file_path);
    if ($stmt->fetch()) {
        @unlink('uploads/' . $file_path);
    }
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM group_shared_files WHERE id=? AND group_id=?");
    $stmt->bind_param("ii", $file_id, $group_id);
    $stmt->execute();
    $stmt->close();
}
// Handle group file sharing
$can_share_file = $is_admin || $allow_member_file_sharing;
if ($can_share_file && isset($_POST['share_group_file'])) {
    $upload_dir = 'uploads/';
    $file_path = '';
    $file_type = '';
    $original_name = '';
    if (isset($_FILES['group_uploaded_file']) && $_FILES['group_uploaded_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['group_uploaded_file']['tmp_name'];
        $original_name = basename($_FILES['group_uploaded_file']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed = ['mp4','mov','avi','jpg','jpeg','png','gif','pdf','doc','docx','ppt','pptx','xls','xlsx','txt'];
        if (in_array($ext, $allowed)) {
            $unique_name = uniqid('groupfile_', true) . '.' . $ext;
            $dest = $upload_dir . $unique_name;
            if (move_uploaded_file($tmp_name, $dest)) {
                $file_path = $unique_name;
                $file_type = $ext;
            }
        }
    }
    if ($file_path) {
        $stmt = $conn->prepare("INSERT INTO group_shared_files (group_id, uploader, file_path, file_type, original_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $group_id, $username, $file_path, $file_type, $original_name);
        $stmt->execute();
        $stmt->close();
    }
}
// Check if user is a member
$stmt = $conn->prepare("SELECT 1 FROM group_members WHERE group_id=? AND username=?");
$stmt->bind_param("is", $group_id, $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo "You are not a member of this group.";
    exit();
}
$stmt->close();
// Remove member logic (admin only)
if ($is_admin && isset($_POST['remove_member'])) {
    $remove_user = $_POST['remove_member'];
    if ($remove_user !== $created_by) { // admin can't remove self
        $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id=? AND username=?");
        $stmt->bind_param("is", $group_id, $remove_user);
        $stmt->execute();
        $stmt->close();
    }
}
// Add member logic (admin only)
if ($is_admin && isset($_POST['add_member'])) {
    $add_user = $_POST['add_member'];
    // Only add if not already a member
    $stmt = $conn->prepare("SELECT 1 FROM group_members WHERE group_id=? AND username=?");
    $stmt->bind_param("is", $group_id, $add_user);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO group_members (group_id, username) VALUES (?, ?)");
        $stmt->bind_param("is", $group_id, $add_user);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt->close();
    }
}
// Send group message
if (isset($_POST['group_message'])) {
    $msg = trim($_POST['group_message']);
    if ($msg) {
        $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $group_id, $username, $msg);
        $stmt->execute();
        $stmt->close();
        header("Location: group_chat.php?group=".$group_id);
        exit();
    }
}

// Handle voice note upload and send as message
if (isset($_FILES['voice_note']) && $_FILES['voice_note']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    $tmp_name = $_FILES['voice_note']['tmp_name'];
    $original_name = basename($_FILES['voice_note']['name']);
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed = ['webm','mp3','wav'];
    if (in_array($ext, $allowed)) {
        $unique_name = uniqid('voice_', true) . '.' . $ext;
        $dest = $upload_dir . $unique_name;
        if (move_uploaded_file($tmp_name, $dest)) {
            // Save as a message with [voice_note] prefix
            $msg = '[voice_note]' . $unique_name;
            $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $group_id, $username, $msg);
            $stmt->execute();
            $stmt->close();
            header("Location: group_chat.php?group=".$group_id);
            exit();
        }
    }
}
// Fetch members
$members = [];
$res = $conn->query("SELECT username FROM group_members WHERE group_id=".$group_id);
while ($row = $res->fetch_assoc()) $members[] = $row['username'];
// Fetch group shared files (with is_held)
$group_files = [];
$res = $conn->query("SELECT id, uploader, file_path, file_type, original_name, timestamp, is_held FROM group_shared_files WHERE group_id=".$group_id." ORDER BY timestamp DESC");
while ($row = $res->fetch_assoc()) $group_files[] = $row;
// Fetch messages (with is_held)
$conversation = [];
$stmt = $conn->prepare("SELECT id, sender, message, timestamp, is_held FROM group_messages WHERE group_id=? ORDER BY timestamp ASC");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$stmt->bind_result($msg_id, $sender, $msg, $ts, $msg_is_held);
while ($stmt->fetch()) {
    $conversation[] = ["id"=>$msg_id, "sender"=>$sender, "message"=>$msg, "timestamp"=>$ts, "is_held"=>$msg_is_held];
}
$stmt->close();
// Fetch can_voice_note for this user (before HTML)
$can_voice_note = false;
$stmt_vn_self = $conn->prepare("SELECT can_voice_note FROM group_members WHERE group_id=? AND username=?");
$stmt_vn_self->bind_param("is", $group_id, $username);
$stmt_vn_self->execute();
$stmt_vn_self->bind_result($can_voice_note_val_self);
if ($stmt_vn_self->fetch()) {
    $can_voice_note = ($can_voice_note_val_self == 1);
}
$stmt_vn_self->close();
?>
<!DOCTYPE html>
<html><head><title>Group Chat: <?php echo htmlspecialchars($group_name); ?></title><style>
body { background:#e6f0ff; font-family:Segoe UI,Arial,sans-serif; transition:background 0.3s, color 0.3s; }
.dark-mode body { background:#181a1b; color:#e0e0e0; }
.group-container { max-width:900px; margin:40px auto; background:#fff; border-radius:12px; box-shadow:0 2px 12px #1976d233; padding:28px; position:relative; }
.dark-mode .group-container { background:#23272b; color:#e0e0e0; }
.group-header { display:flex; align-items:center; gap:18px; margin-bottom:18px; }
.group-cover { width:80px; height:80px; border-radius:10px; object-fit:cover; box-shadow:0 2px 8px #1976d233; }
.group-title { font-size:1.5em; color:#1976d2; }
.dark-mode .group-title { color:#90caf9; }
.member-list { margin-bottom:18px; }
.member { display:inline-block; background:#f5faff; color:#1976d2; border-radius:6px; padding:6px 12px; margin:2px 6px 2px 0; }
.dark-mode .member { background:#23272b; color:#90caf9; }
.remove-btn { background:#d81b60; color:#fff; border:none; border-radius:4px; padding:2px 8px; margin-left:8px; cursor:pointer; font-size:0.95em; }
.conv-msg { margin-bottom:16px; padding:10px 14px; border-radius:8px; background:#f5faff; box-shadow:0 1px 4px #1976d211; }
.dark-mode .conv-msg { background:#23272b; }
.conv-me { background:#e3f2fd; }
.dark-mode .conv-me { background:#1a222b; }
.conv-meta { font-size:0.95em; color:#1976d2; margin-bottom:4px; }
.dark-mode .conv-meta { color:#90caf9; }
.reply-form textarea { width:100%; min-height:60px; border-radius:6px; border:1px solid #b0bec5; padding:8px; }
.dark-mode .reply-form textarea { background:#23272b; color:#e0e0e0; border:1px solid #444; }
.reply-form input[type=submit] { padding:8px 18px; border-radius:6px; border:1px solid #b0bec5; background:#1976d2; color:#fff; font-weight:500; cursor:pointer; }
.dark-mode .reply-form input[type=submit] { background:#1565c0; border:1px solid #444; }
.group-wallpaper-bg { position:fixed; left:0; top:0; width:100vw; height:100vh; z-index:-1; background-size:cover; background-position:center; opacity:0.18; pointer-events:none; }
</style>
<style>
@media (max-width:700px) {
    .group-container { padding:12px 2vw; }
    .group-header { flex-direction:column; align-items:flex-start; gap:10px; }
    .video-row { gap:10px; flex-direction:column; align-items:center; }
    .bio-block { font-size:1em; }
    .group-title { font-size:1.1em; }
    .member { font-size:0.98em; padding:5px 8px; }
    .conv-msg { font-size:1em; }
    .reply-form textarea { font-size:1em; }
    .reply-form input[type=submit] { font-size:1em; padding:7px 10px; }
}
</style>
<script>
// Dark mode toggle
function setDarkMode(on) {
    if(on) document.documentElement.classList.add('dark-mode');
    else document.documentElement.classList.remove('dark-mode');
    localStorage.setItem('darkMode', on ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', function() {
    setDarkMode(localStorage.getItem('darkMode') === '1');
    document.getElementById('darkModeToggle').onclick = function() {
        var on = !document.documentElement.classList.contains('dark-mode');
        setDarkMode(on);
    };
});
</script>
</head><body>
<?php if (!empty($group_wallpaper)): ?>
<div class="group-wallpaper-bg" style="background-image:url('uploads/<?php echo htmlspecialchars($group_wallpaper); ?>');"></div>
<?php endif; ?>
<button id="darkModeToggle" style="position:fixed;top:18px;right:18px;z-index:10;padding:7px 16px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">Toggle Dark Mode</button>
<div class="group-container">
    <div class="group-header">
        <?php if ($is_admin): ?>
        <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="margin-left:24px;display:inline-block;">
            <label style="font-size:0.95em;">Set/Change Group Password: </label>
            <input type="password" name="new_group_password" placeholder="New Password" required style="padding:4px 8px;border-radius:6px;border:1px solid #b0bec5;">
            <input type="submit" name="set_group_password" value="Save" style="padding:4px 12px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
        </form>
        <?php 
        // Show password status: set if not null/empty and not all spaces
        $pwd_set = isset($group_password_hash) && strlen(trim($group_password_hash)) > 0;
        ?>
        <?php if ($pwd_set): ?>
            <span style="margin-left:12px;color:#388e3c;font-size:0.98em;">Password is set</span>
        <?php else: ?>
            <span style="margin-left:12px;color:#d81b60;font-size:0.98em;">No password set</span>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (isset($success_msg)): ?>
            <span style="margin-left:12px;color:#388e3c;font-size:0.98em;">✔ <?php echo htmlspecialchars($success_msg); ?></span>
        <?php endif; ?>
        <?php if (!empty($cover_photo)): ?>
            <img src="uploads/<?php echo htmlspecialchars($cover_photo); ?>" class="group-cover" alt="Group Cover Photo">
        <?php endif; ?>
        <span class="group-title"><?php echo htmlspecialchars($group_name); ?></span>
        <?php if ($is_admin): ?>
            <form method="POST" enctype="multipart/form-data" action="group_chat.php?group=<?php echo $group_id; ?>" style="margin-left:24px;display:inline-block;">
                <label style="font-size:0.95em;">Set Group Wallpaper: </label>
                <input type="file" name="wallpaper_file" accept="image/*" style="margin-bottom:0;">
                <input type="submit" name="set_wallpaper" value="Upload" style="padding:4px 12px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
            </form>
        <?php endif; ?>
    </div>
    <?php if ($is_admin && count($pending_requests) > 0): ?>
    <div style="background:#fffbe7;border:1px solid #ffa000;padding:14px 18px;border-radius:8px;margin-bottom:18px;">
        <b>Pending Join Requests:</b>
        <ul style="list-style:none;padding-left:0;">
        <?php foreach($pending_requests as $req): ?>
            <li style="margin-bottom:7px;">
                <span style="color:#1976d2;font-weight:500;">User: <?php echo htmlspecialchars($req['username']); ?></span>
                <span style="color:#888;font-size:0.95em;">@ <?php echo htmlspecialchars($req['requested_at']); ?></span>
                <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;margin-left:10px;">
                    <input type="hidden" name="handle_join_request" value="1">
                    <input type="hidden" name="request_user" value="<?php echo htmlspecialchars($req['username']); ?>">
                    <input type="hidden" name="request_action" value="approve">
                    <button type="submit" style="background:#43a047;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.95em;">Approve</button>
                </form>
                <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;margin-left:4px;">
                    <input type="hidden" name="handle_join_request" value="1">
                    <input type="hidden" name="request_user" value="<?php echo htmlspecialchars($req['username']); ?>">
                    <input type="hidden" name="request_action" value="deny">
                    <button type="submit" style="background:#d81b60;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.95em;">Deny</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <div class="member-list">
        <b>Members:</b>
        <?php foreach($members as $m): ?>
            <span class="member"><?php echo htmlspecialchars($m); ?></span>
            <?php
            $can_voice_note = false;
            $user_hold_files = 0;
            $stmt_vn = $conn->prepare("SELECT can_voice_note, hold_files FROM group_members WHERE group_id=? AND username=?");
            $stmt_vn->bind_param("is", $group_id, $m);
            $stmt_vn->execute();
            $stmt_vn->bind_result($can_voice_note_val, $user_hold_files_val);
            if ($stmt_vn->fetch()) {
                $can_voice_note = ($can_voice_note_val == 1);
                $user_hold_files = $user_hold_files_val;
            }
            $stmt_vn->close();
            ?>
            <?php if ($is_admin && $m !== $created_by): ?>
                <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:6px;">
                    <input type="hidden" name="toggle_voice_note_user" value="<?php echo htmlspecialchars($m); ?>">
                    <input type="hidden" name="voice_note_val" value="<?php echo $can_voice_note ? '0' : '1'; ?>">
                    <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:0.95em;">
                        <?php echo $can_voice_note ? 'Disable' : 'Enable'; ?> Voice Note
                    </button>
                </form>
                <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:4px;">
                    <input type="hidden" name="toggle_user_hold" value="<?php echo htmlspecialchars($m); ?>">
                    <input type="hidden" name="user_hold_val" value="<?php echo $user_hold_files ? '0' : '1'; ?>">
                    <button type="submit" style="background:#ffa000;color:#fff;border:none;border-radius:4px;padding:2px 8px;cursor:pointer;font-size:0.95em;">
                        <?php echo $user_hold_files ? 'Release Hold' : 'Hold'; ?> Files
                    </button>
                </form>
                <?php if ($user_hold_files): ?><span style="color:#ffa000;font-size:0.95em;margin-left:4px;">[User Held]</span><?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($is_admin): ?>
            <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:18px;">
                <select name="remove_member" required style="padding:4px 10px;border-radius:6px;border:1px solid #b0bec5;">
                    <option value="">Select member to remove</option>
                    <?php foreach($members as $m): if($m !== $created_by): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                    <?php endif; endforeach; ?>
                </select>
                <button type="submit" class="remove-btn" onclick="return confirm('Remove this member?');">Remove</button>
            </form>
            <form method="POST" id="addMemberForm" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:18px;">
                <select name="add_member" id="addMemberSelect" required style="padding:4px 10px;border-radius:6px;border:1px solid #b0bec5;min-width:120px;">
                    <option value="">Select user to add</option>
                </select>
                <button type="submit" style="background:#43a047;color:#fff;border:none;border-radius:4px;padding:2px 10px;margin-left:4px;cursor:pointer;font-size:0.95em;">Add</button>
            </form>
            <script>
            // Fetch addable users via AJAX
            document.addEventListener('DOMContentLoaded', function() {
                var select = document.getElementById('addMemberSelect');
                var exclude = <?php echo json_encode($members); ?>;
                fetch('_get_addable_members.php?group=<?php echo $group_id; ?>&exclude='+encodeURIComponent(exclude.join(',')))
                    .then(r=>r.json())
                    .then(function(users){
                        users.forEach(function(u){
                            var opt = document.createElement('option');
                            opt.value = u;
                            opt.textContent = u;
                            select.appendChild(opt);
                        });
                    });
            });
            </script>
        <?php endif; ?>
    </div>
    <div style="margin-bottom:18px;">
        <b>Shared Files:</b>
        <?php if ($is_admin): ?>
            <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:18px;">
                <input type="hidden" name="toggle_file_sharing" value="<?php echo $allow_member_file_sharing ? '0' : '1'; ?>">
                <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.95em;">
                    <?php echo $allow_member_file_sharing ? 'Disable' : 'Enable'; ?> Member File Sharing
                </button>
            </form>
            <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline-block;margin-left:18px;">
                <input type="hidden" name="toggle_hold_files" value="<?php echo $hold_files ? '0' : '1'; ?>">
                <button type="submit" style="background:#d81b60;color:#fff;border:none;border-radius:4px;padding:2px 10px;cursor:pointer;font-size:0.95em;">
                    <?php echo $hold_files ? 'Release Hold on Files' : 'Hold All Files'; ?>
                </button>
            </form>
        <?php endif; ?>
        <ul style="list-style:none;padding-left:0;">
        <?php foreach($group_files as $f): ?>
            <li style="margin-bottom:7px;">
                <a href="uploads/<?php echo htmlspecialchars($f['file_path']); ?>" target="_blank" style="color:#1976d2;font-weight:500;">
                    <?php echo htmlspecialchars($f['original_name']); ?>
                </a>
                <span style="color:#888;font-size:0.95em;">(by <?php echo htmlspecialchars($f['uploader']); ?> @ <?php echo htmlspecialchars($f['timestamp']); ?>)</span>
                <?php if ($is_admin): ?>
                    <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;">
                        <input type="hidden" name="toggle_file_hold" value="<?php echo $f['id']; ?>">
                        <input type="hidden" name="file_hold_val" value="<?php echo $f['is_held'] ? '0' : '1'; ?>">
                        <button type="submit" style="background:#ffa000;color:#fff;border:none;border-radius:4px;padding:2px 8px;margin-left:8px;cursor:pointer;font-size:0.95em;">
                            <?php echo $f['is_held'] ? 'Release Hold' : 'Hold'; ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($is_admin || ($f['uploader'] === $username && !$f['is_held'] && !$hold_files)): ?>
                    <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;">
                        <input type="hidden" name="delete_group_file" value="<?php echo $f['id']; ?>">
                        <button type="submit" style="background:#d81b60;color:#fff;border:none;border-radius:4px;padding:2px 8px;margin-left:8px;cursor:pointer;font-size:0.95em;">Delete</button>
                    </form>
                <?php endif; ?>
                <?php if ($f['is_held']): ?><span style="color:#ffa000;font-size:0.95em;margin-left:6px;">[Held]</span><?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php if ($can_share_file): ?>
            <form method="POST" enctype="multipart/form-data" action="group_chat.php?group=<?php echo $group_id; ?>" class="group-share-form" style="margin-top:10px;background:#f5faff;padding:10px 12px;border-radius:8px;display:inline-block;">
                <b>Share a file with the group:</b><br>
                <input type="file" name="group_uploaded_file" accept="video/*,image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt" required style="margin-bottom:8px;">
                <input type="submit" name="share_group_file" value="Share" style="padding:6px 18px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">
            </form>
            <style>
            .dark-mode .group-share-form {
                background: #23272b !important;
                color: #e0e0e0;
                border: 1px solid #444;
            }
            .dark-mode .group-share-form input[type="file"] {
                background: #181a1b;
                color: #e0e0e0;
                border: 1px solid #444;
            }
            .dark-mode .group-share-form input[type="submit"] {
                background: #1565c0;
                border: 1px solid #444;
                color: #fff;
            }
            </style>
        <?php endif; ?>
    </div>
    <div id="conv-list">
        <?php foreach($conversation as $m): ?>
            <div class="conv-msg<?php if($m['sender']==$username) echo ' conv-me'; ?>">
                <div class="conv-meta">
                    <b><?php echo ($m['sender'] == $username) ? 'you' : htmlspecialchars($m['sender']); ?></b> @ <?php echo htmlspecialchars($m['timestamp']); ?>
                </div>
                <div class="msg-content">
                <?php if (strpos($m['message'], '[voice_note]') === 0):
                    $vn_file = substr($m['message'], 12);
                ?>
                    <audio controls src="uploads/<?php echo htmlspecialchars($vn_file); ?>" style="max-width:220px;"></audio>
                    <?php if ($is_admin): ?>
                        <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;">
                            <input type="hidden" name="toggle_voice_hold" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="voice_hold_val" value="<?php echo $m['is_held'] ? '0' : '1'; ?>">
                            <button type="submit" style="background:#ffa000;color:#fff;border:none;border-radius:4px;padding:2px 8px;margin-left:8px;cursor:pointer;font-size:0.95em;">
                                <?php echo $m['is_held'] ? 'Release Hold' : 'Hold'; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($is_admin || ($m['sender'] === $username && !$m['is_held'] && !$hold_files)): ?>
                        <form method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" style="display:inline;">
                            <input type="hidden" name="delete_voice_note_msg" value="<?php echo $m['id']; ?>">
                            <button type="submit" style="background:#d81b60;color:#fff;border:none;border-radius:4px;padding:2px 8px;margin-left:8px;cursor:pointer;font-size:0.95em;">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($m['is_held']): ?><span style="color:#ffa000;font-size:0.95em;margin-left:6px;">[Held]</span><?php endif; ?>
                <?php else: ?>
                    <?php echo nl2br(htmlspecialchars($m['message'])); ?>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <form class="reply-form" method="POST" action="group_chat.php?group=<?php echo $group_id; ?>" enctype="multipart/form-data">
        <textarea name="group_message" required placeholder="Type your message..."></textarea><br>
        <input type="submit" value="Send">
        <?php if ($can_voice_note || $is_admin): ?>
        <button type="button" id="openRecordModalBtn" style="margin-left:10px;padding:8px 14px;border-radius:6px;background:#43a047;color:#fff;border:none;cursor:pointer;">🎤 Record Voice Note</button>
        <!-- Voice Note Modal -->
        <div id="voiceNoteModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;padding:28px 22px;border-radius:12px;max-width:350px;margin:auto;box-shadow:0 2px 16px #1976d299;position:relative;">
                <span id="closeModalBtn" style="position:absolute;top:10px;right:16px;font-size:1.5em;cursor:pointer;">&times;</span>
                <h3 style="margin-top:0;">Voice Note</h3>
                <div id="recorderUI">
                    <button type="button" id="startRecordBtn" style="padding:8px 14px;border-radius:6px;background:#43a047;color:#fff;border:none;cursor:pointer;">Start Recording</button>
                    <button type="button" id="stopRecordBtn" style="padding:8px 14px;border-radius:6px;background:#d81b60;color:#fff;border:none;cursor:pointer;display:none;">Stop</button>
                    <div id="recordingStatus" style="margin:10px 0 0 0;color:#1976d2;"></div>
                </div>
                <div id="previewUI" style="display:none;">
                    <audio id="modalAudioPreview" controls style="width:100%;margin-top:10px;"></audio>
                    <div style="margin-top:12px;">
                        <button type="button" id="deleteVoiceBtn" style="background:#d81b60;color:#fff;border:none;border-radius:4px;padding:6px 14px;cursor:pointer;">Delete</button>
                        <button type="button" id="sendVoiceBtnModal" style="background:#1976d2;color:#fff;border:none;border-radius:4px;padding:6px 14px;margin-left:10px;cursor:pointer;">Send</button>
                    </div>
                </div>
                <input type="file" id="voiceNoteInputModal" name="voice_note" accept="audio/webm,audio/mp3,audio/wav" style="display:none;">
            </div>
        </div>
        <script>
        let mediaRecorder, audioChunks = [], audioBlob = null;
        const openModalBtn = document.getElementById('openRecordModalBtn');
        const modal = document.getElementById('voiceNoteModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const startBtn = document.getElementById('startRecordBtn');
        const stopBtn = document.getElementById('stopRecordBtn');
        const statusDiv = document.getElementById('recordingStatus');
        const previewUI = document.getElementById('previewUI');
        const recorderUI = document.getElementById('recorderUI');
        const audioPreview = document.getElementById('modalAudioPreview');
        const deleteBtn = document.getElementById('deleteVoiceBtn');
        const sendBtn = document.getElementById('sendVoiceBtnModal');
        const voiceInput = document.getElementById('voiceNoteInputModal');
        let stream = null;

        openModalBtn.onclick = function() {
            modal.style.display = 'flex';
            recorderUI.style.display = '';
            previewUI.style.display = 'none';
            statusDiv.textContent = '';
            audioBlob = null;
        };
        closeModalBtn.onclick = function() {
            modal.style.display = 'none';
            if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        };
        startBtn.onclick = async function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Voice recording not supported in this browser.');
                return;
            }
            startBtn.style.display = 'none';
            stopBtn.style.display = '';
            statusDiv.textContent = 'Recording...';
            audioChunks = [];
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = e => {
                audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const url = URL.createObjectURL(audioBlob);
                audioPreview.src = url;
                previewUI.style.display = '';
                recorderUI.style.display = 'none';
                // Set file input for form submission
                const file = new File([audioBlob], 'voice_note.webm', { type: 'audio/webm' });
                const dt = new DataTransfer();
                dt.items.add(file);
                voiceInput.files = dt.files;
                if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
            };
            mediaRecorder.start();
        };
        stopBtn.onclick = function() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                stopBtn.style.display = 'none';
                startBtn.style.display = '';
                statusDiv.textContent = '';
            }
        };
        deleteBtn.onclick = function() {
            previewUI.style.display = 'none';
            recorderUI.style.display = '';
            audioPreview.src = '';
            audioBlob = null;
            voiceInput.value = '';
        };
        sendBtn.onclick = function() {
            if (!audioBlob) return;
            // Submit via hidden form
            const form = document.createElement('form');
            form.method = 'POST';
            form.enctype = 'multipart/form-data';
            form.action = window.location.href;
            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'voice_note';
            // Use the already set file input
            if (voiceInput.files.length > 0) {
                form.appendChild(voiceInput.cloneNode());
            }
            document.body.appendChild(form);
            // Actually use the existing file input
            voiceInput.name = 'voice_note';
            form.appendChild(voiceInput);
            form.submit();
        };
        // Close modal on outside click
        window.onclick = function(e) {
            if (e.target === modal) closeModalBtn.onclick();
        };
        </script>
        <?php endif; ?>
    </form>
    <div style="text-align:center;margin-top:24px;"><a href="messages.php" style="color:#1976d2;">&larr; Back to Messages</a></div>
</div>
</body></html>
<?php
$conn->close();
?>
