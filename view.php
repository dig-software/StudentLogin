<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Details</title>
    <style>
    body { background: linear-gradient(135deg, #1976d2 0%, #e6f0ff 100%); margin:0; font-family:Segoe UI,Arial,sans-serif; transition:background 0.3s, color 0.3s; }
    .dark-mode body { background: #181a1b; color: #e0e0e0; }
    .profile-card { background:#fff; max-width:800px; margin:40px auto 0 auto; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.10); padding:32px 24px 24px 24px; transition:background 0.3s, color 0.3s; }
    .dark-mode .profile-card { background:#23272b; color:#e0e0e0; }
    .profile-pic { display:block; margin:0 auto 18px auto; border-radius:50%; box-shadow:0 2px 8px rgba(0,0,0,0.08); transition:transform 0.2s; }
    .profile-pic:hover { transform:scale(1.05); }
    .profile-table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    .profile-table th, .profile-table td { padding:8px 12px; border-bottom:1px solid #e0e0e0; }
    .profile-table th { text-align:left; color:#1976d2; font-weight:600; background:#f5faff; }
    .dark-mode .profile-table th { color:#90caf9; background:#23272b; }
    .profile-table tr:last-child td { border-bottom:none; }
    .bio-block { max-width:600px; margin:0 auto 18px auto; background:#f5faff; border-radius:8px; padding:12px 16px; font-size:1.05em; }
    .dark-mode .bio-block { background:#23272b; color:#e0e0e0; }
    .video-row { display:flex; gap:24px; flex-wrap:wrap; justify-content:center; margin-bottom:15px; }
    .video-card { display:flex; flex-direction:column; align-items:center; background:#f8fafd; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:10px 10px 6px 10px; transition:box-shadow 0.2s,transform 0.2s; }
    .dark-mode .video-card { background:#23272b; }
    .video-card:hover { box-shadow:0 6px 18px rgba(25,118,210,0.13); transform:translateY(-3px) scale(1.03); }
    .video-card video { border-radius:8px; }
    .action-bar { margin-top:40px; text-align:center; }
    .action-bar a, .action-bar button { display:inline-block; margin:0 10px; padding:8px 18px; border-radius:6px; border:none; background:#1976d2; color:#fff; text-decoration:none; font-weight:500; font-size:1em; transition:background 0.2s,box-shadow 0.2s; cursor:pointer; box-shadow:0 2px 8px rgba(25,118,210,0.07); }
    .action-bar a:hover, .action-bar button:hover { background:#1251a3; }
    .action-bar button { background:#e53935; }
    .action-bar button:hover { background:#b71c1c; }
    .dark-mode .action-bar a, .dark-mode .action-bar button { background:#1565c0; color:#fff; }
    .dark-mode .action-bar a:hover, .dark-mode .action-bar button:hover { background:#1976d2; }
    @media (max-width:700px) {
        .profile-card { padding:12px 2vw; }
        .video-row { gap:10px; flex-direction:column; align-items:center; }
        .bio-block { font-size:1em; }
        .profile-table th, .profile-table td { font-size:0.98em; padding:6px 6px; }
        .action-bar a, .action-bar button { font-size:0.98em; padding:7px 10px; }
        .video-card { width:95vw; max-width:320px; }
        .video-card video { width:100%; height:auto; }
    }
    </style>
    <style>
    .dark-mode .my-groups-section b,
    .dark-mode .received-files-section b {
        color: #90caf9;
    }
    .dark-mode .my-groups-list .group-link {
        background: #23272b !important;
        color: #90caf9 !important;
        box-shadow: 0 1px 4px #2228;
    }
    .dark-mode .my-groups-list .group-link:hover {
        background: #1976d2 !important;
        color: #fff !important;
    }
    .dark-mode .received-files-list .received-file-row {
        background: #23272b !important;
        color: #e0e0e0 !important;
        box-shadow: 0 1px 4px #2228;
    }
    .dark-mode .received-files-list .received-file-row a {
        color: #90caf9 !important;
    }
    .dark-mode .received-files-list .received-file-row button {
        background: #181a1b !important;
        color: #d81b60 !important;
        border: 1px solid #d81b60 !important;
    }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1976d2">
    <meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="icon-192.png">
    </head>
    <body>
    <button id="darkModeToggle" style="position:fixed;top:18px;right:18px;z-index:10;padding:7px 16px;border-radius:6px;border:1px solid #1976d2;background:#1976d2;color:#fff;font-weight:500;cursor:pointer;">Toggle Dark Mode</button>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('service-worker.js');
        });
    }
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
<?php
if (!isset($_SESSION['username'])) {
    echo "Access denied. Please <a href='login.html'>login</a> first.";
    exit();
}

$conn = null;
require_once 'db_connect.php';
if (!$conn) die("Connection failed");

$username = $_SESSION['username'];
// Fetch user details
$stmt = $conn->prepare("SELECT name, username, reg_number, phone, email, course, profile_pic, bio FROM registration WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

// Handle delete received file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shared_file'])) {
    $file_id = intval($_POST['delete_shared_file']);
    $conn_del = new mysqli($servername, $username_db, $password_db, $dbname);
    $stmt_del = $conn_del->prepare("DELETE FROM shared_files WHERE id=? AND recipient=?");
    $stmt_del->bind_param("is", $file_id, $username);
    $stmt_del->execute();
    $stmt_del->close();
    $conn_del->close();
    header("Location: view.php");
    exit();
}

if ($stmt->num_rows === 1) {
    $stmt->bind_result($name, $username, $reg_number, $phone, $email, $course, $profile_pic, $bio);
    $stmt->fetch();
    echo "<h2>Student Details</h2>";
    // Card container
    echo "<div class='profile-card'>";
    if ($profile_pic) {
        echo "<img src='uploads/" . htmlspecialchars($profile_pic) . "' alt='Profile Picture' width='120' class='profile-pic'>";
    }
    // Notification badge for unread messages and new shared files
    $unread_count = 0;
    $conn_notif = new mysqli($servername, $username_db, $password_db, $dbname);
    $res_unread = $conn_notif->query("SELECT COUNT(*) as cnt FROM messages WHERE recipient='".$conn_notif->real_escape_string($username)."' AND is_read=0");
    if ($row_unread = $res_unread->fetch_assoc()) {
        $unread_count += (int)$row_unread['cnt'];
    }
    $res_files = $conn_notif->query("SELECT COUNT(*) as cnt FROM shared_files WHERE recipient='".$conn_notif->real_escape_string($username)."' AND timestamp >= NOW() - INTERVAL 1 DAY");
    if ($row_files = $res_files->fetch_assoc()) {
        $unread_count += (int)$row_files['cnt'];
    }
    $conn_notif->close();
    $badge = $unread_count > 0 ? "<span style='background:#d81b60;color:#fff;border-radius:50%;padding:2px 8px;font-size:0.9em;margin-left:7px;'>$unread_count</span>" : "";
    echo "<div style='text-align:center;margin-bottom:12px;'><a href='messages.php' style='background:#1976d2;color:#fff;padding:7px 18px;border-radius:6px;text-decoration:none;font-weight:500;box-shadow:0 2px 8px #1976d211;transition:background 0.2s;'>💬 Messages$badge</a></div>";
    if ($bio) {
        echo "<div class='bio-block'><strong>Bio:</strong><br>" . nl2br(htmlspecialchars($bio)) . "</div>";
    }
    echo "<table class='profile-table'>";
    echo "<tr><th>Name</th><td>$name</td></tr>";
    echo "<tr><th>Username</th><td>$username</td></tr>";
    echo "<tr><th>Registration Number</th><td>$reg_number</td></tr>";
    echo "<tr><th>Phone</th><td>$phone</td></tr>";
    echo "<tr><th>Email</th><td>$email</td></tr>";
    echo "<tr><th>Course</th><td>$course</td></tr>";
    echo "</table>";
    // Videos
    $videos = [];
    $resv = $conn->query("SELECT video_filename FROM user_videos WHERE username='" . $conn->real_escape_string($username) . "'");
    while ($rowv = $resv->fetch_assoc()) {
        $videos[] = $rowv['video_filename'];
    }
    echo "<div style='margin-top:30px;'>";
    if (count($videos) > 0) {
        echo "<b>Your Videos:</b><br>";
        echo "<div class='video-row'>";
        foreach ($videos as $v) {
            echo "<div class='video-card'>";
            echo "<video width='200' height='120' controls><source src='uploads/" . htmlspecialchars($v) . "' type='video/mp4'></video>";
            echo "</div>";
        }
        echo "</div>";
    }
    echo "</div>";
    // My Groups Section
    $conn_groups = new mysqli($servername, $username_db, $password_db, $dbname);
    // Fetch all groups (not just joined)
    $res_groups = $conn_groups->query("SELECT id, name, cover_photo FROM groups");
    $groups = [];
    while ($rowg = $res_groups->fetch_assoc()) $groups[] = $rowg;
    $conn_groups->close();
    // Fetch user's joined group ids
    $conn_gm = new mysqli($servername, $username_db, $password_db, $dbname);
    $res_gm = $conn_gm->query("SELECT group_id FROM group_members WHERE username='".$conn_gm->real_escape_string($username)."'");
    $joined_group_ids = [];
    while ($rowgm = $res_gm->fetch_assoc()) $joined_group_ids[] = $rowgm['group_id'];
    $conn_gm->close();
    if (count($groups) > 0) {
        echo "<div class='my-groups-section' style='margin-top:28px;'><b>Groups:</b><div class='my-groups-list' style='margin-top:10px;display:flex;flex-wrap:wrap;gap:18px;'>";
        foreach ($groups as $g) {
            $gid = intval($g['id']);
            $is_joined = in_array($gid, $joined_group_ids);
            echo "<div style='display:flex;align-items:center;gap:10px;background:#f5faff;padding:10px 16px;border-radius:8px;box-shadow:0 1px 4px #1976d211;font-weight:500;" . ($is_joined ? '' : 'border:2px solid #ffa000;') . "'>";
            if (!empty($g['cover_photo'])) {
                echo "<img src='uploads/".htmlspecialchars($g['cover_photo'])."' style='width:32px;height:32px;object-fit:cover;border-radius:50%;box-shadow:0 1px 4px #1976d211;'>";
            } else {
                echo "<span style='font-size:1.3em;'>👥</span>";
            }
            echo htmlspecialchars($g['name']);
            if ($is_joined) {
                echo " <a href='group_chat.php?group=$gid' style='margin-left:10px;color:#1976d2;text-decoration:underline;'>Enter</a>";
            } else {
                // Check if already requested
                $conn_req = new mysqli($servername, $username_db, $password_db, $dbname);
                $stmt_req = $conn_req->prepare("SELECT status FROM group_join_requests WHERE group_id=? AND username=? ORDER BY requested_at DESC LIMIT 1");
                $stmt_req->bind_param("is", $gid, $username);
                $stmt_req->execute();
                $stmt_req->bind_result($req_status);
                if ($stmt_req->fetch()) {
                    if ($req_status === 'pending') {
                        echo " <span style='color:#ffa000;margin-left:10px;'>Request Pending</span>";
                    } elseif ($req_status === 'denied') {
                        echo " <span style='color:#d81b60;margin-left:10px;'>Request Denied</span>";
                    } elseif ($req_status === 'approved') {
                        echo " <span style='color:#43a047;margin-left:10px;'>Approved - Check Inbox</span>";
                    }
                } else {
                    echo " <form method='POST' style='display:inline;margin-left:10px;'><input type='hidden' name='request_join_group' value='$gid'><button type='submit' style='background:#ffa000;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;'>Request to Join</button></form>";
                }
                $stmt_req->close();
                $conn_req->close();
            }
            echo "</div>";
        }
        echo "</div></div>";
// Handle join group request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_join_group'])) {
    $req_gid = intval($_POST['request_join_group']);
    $conn_req = new mysqli($servername, $username_db, $password_db, $dbname);
    // Only insert if not already pending/approved
    $stmt_check = $conn_req->prepare("SELECT status FROM group_join_requests WHERE group_id=? AND username=? ORDER BY requested_at DESC LIMIT 1");
    $stmt_check->bind_param("is", $req_gid, $username);
    $stmt_check->execute();
    $stmt_check->bind_result($req_status);
    if ($stmt_check->fetch()) {
        // Already requested, do nothing
        $stmt_check->close();
        $conn_req->close();
    } else {
        $stmt_check->close();
        $stmt_ins = $conn_req->prepare("INSERT INTO group_join_requests (group_id, username) VALUES (?, ?)");
        $stmt_ins->bind_param("is", $req_gid, $username);
        $stmt_ins->execute();
        $stmt_ins->close();
        $conn_req->close();
        echo "<script>alert('Join request sent to group admin.');window.location='view.php';</script>";
        exit();
    }
}
    }
    // Password modal
    ?>
    <div id="groupPasswordModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:28px 22px;border-radius:12px;max-width:350px;margin:auto;box-shadow:0 2px 16px #1976d299;position:relative;">
            <span id="closeGroupPwdModalBtn" style="position:absolute;top:10px;right:16px;font-size:1.5em;cursor:pointer;">&times;</span>
            <h3 style="margin-top:0;">Enter Group Password</h3>
            <form id="groupPwdForm" method="POST" action="">
                <input type="hidden" name="join_group_id" id="join_group_id" value="">
                <input type="password" name="group_password" placeholder="Group Password" required style="width:100%;padding:8px;margin-bottom:12px;border-radius:6px;border:1px solid #b0bec5;">
                <button type="submit" style="background:#1976d2;color:#fff;border:none;border-radius:6px;padding:8px 18px;cursor:pointer;width:100%;">Join Group</button>
            </form>
            <div id="groupPwdError" style="color:#d81b60;margin-top:10px;display:none;"></div>
        </div>
    </div>
    <script>
    document.querySelectorAll('.group-link').forEach(function(link){
        link.onclick = function(e){
            var gid = this.getAttribute('data-groupid');
            var joined = this.innerHTML.indexOf('[Join]') === -1;
            if (joined) {
                window.location = 'group_chat.php?group=' + gid;
            } else {
                document.getElementById('groupPasswordModal').style.display = 'flex';
                document.getElementById('join_group_id').value = gid;
                document.getElementById('groupPwdError').style.display = 'none';
                e.preventDefault();
            }
        };
    });
    document.getElementById('closeGroupPwdModalBtn').onclick = function(){
        document.getElementById('groupPasswordModal').style.display = 'none';
    };
    </script>
    <?php
// Handle group join with password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group_id']) && isset($_POST['group_password'])) {
    $join_group_id = intval($_POST['join_group_id']);
    $input_pwd = $_POST['group_password'];
    $conn_jg = new mysqli($servername, $username_db, $password_db, $dbname);
    $stmt_jg = $conn_jg->prepare("SELECT group_password FROM groups WHERE id=?");
    $stmt_jg->bind_param("i", $join_group_id);
    $stmt_jg->execute();
    $stmt_jg->bind_result($group_pwd_hash);
    if ($stmt_jg->fetch()) {
        if (password_verify($input_pwd, $group_pwd_hash)) {
            // Add user to group_members if not already
            $stmt_jg->close();
            $stmt_check = $conn_jg->prepare("SELECT 1 FROM group_members WHERE group_id=? AND username=?");
            $stmt_check->bind_param("is", $join_group_id, $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows === 0) {
                $stmt_check->close();
                $stmt_add = $conn_jg->prepare("INSERT INTO group_members (group_id, username) VALUES (?, ?)");
                $stmt_add->bind_param("is", $join_group_id, $username);
                $stmt_add->execute();
                $stmt_add->close();
            } else { $stmt_check->close(); }
            $conn_jg->close();
            header("Location: group_chat.php?group=".$join_group_id);
            exit();
        } else {
            $stmt_jg->close();
            $conn_jg->close();
            echo "<script>document.addEventListener('DOMContentLoaded',function(){document.getElementById('groupPasswordModal').style.display='flex';document.getElementById('groupPwdError').textContent='Incorrect password.';document.getElementById('groupPwdError').style.display='block';});</script>";
        }
    } else { $stmt_jg->close(); $conn_jg->close(); }
}
    // Received Files Section (only show if viewing own profile)
    if (!isset($_GET['user']) || $_GET['user'] === $_SESSION['username']) {
        $conn_rf = new mysqli($servername, $username_db, $password_db, $dbname);
        $stmt_rf = $conn_rf->prepare("SELECT id, sender, file_path, file_type, original_name, from_profile, timestamp FROM shared_files WHERE recipient=? ORDER BY timestamp DESC");
        $stmt_rf->bind_param("s", $username);
        $stmt_rf->execute();
        $stmt_rf->store_result();
        if ($stmt_rf->num_rows > 0) {
            $stmt_rf->bind_result($rf_id, $rf_sender, $rf_path, $rf_type, $rf_orig, $rf_from_profile, $rf_time);
            echo "<div class='received-files-section' style='margin-top:28px;'><b>Received Files:</b><div class='received-files-list' style='margin-top:10px;'>";
            while ($stmt_rf->fetch()) {
                echo "<div class='received-file-row' style='margin-bottom:12px;padding:8px 12px;background:#f5faff;border-radius:7px;box-shadow:0 1px 4px #1976d211;display:flex;align-items:center;justify-content:space-between;gap:10px;'>";
                echo "<div>";
                echo "<span style='color:#1976d2;font-weight:500;'>From: <a href='profile.php?user=".urlencode($rf_sender)."' style='color:#1976d2;'>".htmlspecialchars($rf_sender)."</a></span> ";
                echo "<span style='color:#888;font-size:0.95em;'>@ ".htmlspecialchars($rf_time)."</span><br>";
                if (in_array(strtolower(pathinfo($rf_path, PATHINFO_EXTENSION)), ['mp4','mov','avi'])) {
                    echo "<video width='180' controls style='margin-top:6px;'><source src='uploads/".htmlspecialchars($rf_path)."'></video>";
                } elseif (in_array(strtolower(pathinfo($rf_path, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif'])) {
                    echo "<img src='uploads/".htmlspecialchars($rf_path)."' style='max-width:180px;margin-top:6px;border-radius:6px;'>";
                } else {
                    echo "<a href='uploads/".htmlspecialchars($rf_path)."' download style='color:#1976d2;font-weight:500;'>".htmlspecialchars($rf_orig)."</a>";
                }
                if ($rf_from_profile) {
                    echo " <span style='color:#388e3c;font-size:0.92em;'>(from sender's profile videos)</span>";
                }
                echo "</div>";
                echo "<form method='POST' onsubmit=\"return confirm('Delete this received file?');\" style='margin:0;'>";
                echo "<input type='hidden' name='delete_shared_file' value='".intval($rf_id)."'>";
                echo "<button type='submit' style='padding:4px 10px;border-radius:5px;border:1px solid #d81b60;background:#fff;color:#d81b60;font-weight:500;cursor:pointer;'>Delete</button>";
                echo "</form>";
                echo "</div>";
            }
            echo "</div></div>";
        }
        $stmt_rf->close();
        $conn_rf->close();
    }

    // Action links at the very bottom
    echo "<div class='action-bar'>";
    echo "<a href='edit.php'>Edit Details</a> ";
    echo "| <form action='delete_account.php' method='POST' style='display:inline;' onsubmit=\"return confirm('Are you sure you want to delete your account? This cannot be undone.');\"'>";
    echo "<input type='hidden' name='username' value='$username'>";
    echo "<button type='submit'>Delete Account</button>";
    echo "</form> ";
    echo "| <a href='logout.php'>Logout</a>";
    echo "</div>";
    echo "</div>";
    echo "</body></html>";
} else {
    echo "No details found.";
    echo "</body></html>";
}
$stmt->close();
$conn->close();
?>
