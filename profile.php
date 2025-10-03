<?php
// profile.php: View another user's public profile
session_start();
if (!isset($_SESSION['username'])) {
    echo "Access denied. Please <a href='login.html'>login</a> first.";
    exit();
}
if (!isset($_GET['user'])) {
    echo "No user specified.";
    exit();
}
$view_user = $_GET['user'];
$conn = null;
require_once 'db_connect.php';
if (!$conn) die("Connection failed");
// Fetch user details and show_videos privacy
$stmt = $conn->prepare("SELECT name, username, reg_number, course, profile_pic, bio, show_videos FROM registration WHERE username=?");
$stmt->bind_param("s", $view_user);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 1) {
    $stmt->bind_result($name, $username, $reg_number, $course, $profile_pic, $bio, $show_videos);
    $stmt->fetch();
    echo "<!DOCTYPE html><html><head><title>Profile of $username</title><style>body{background:#e6f0ff;font-family:Segoe UI,Arial,sans-serif;}.profile-card{background:#fff;max-width:500px;margin:40px auto;border-radius:16px;box-shadow:0 2px 12px #1976d233;padding:28px;}h2{color:#1976d2;}.profile-pic{display:block;margin:0 auto 18px auto;border-radius:50%;box-shadow:0 2px 8px #0001;}table{width:100%;margin-bottom:18px;}th,td{padding:8px 12px;text-align:left;}th{color:#1976d2;}tr:last-child td{border-bottom:none;}.bio-block{background:#f5faff;border-radius:8px;padding:10px 14px;margin-bottom:14px;}</style></head><body>";
    echo "<div class='profile-card'>";
    echo "<h2>Profile of $name</h2>";
    if ($profile_pic) {
        echo "<img src='uploads/" . htmlspecialchars($profile_pic) . "' alt='Profile Picture' width='110' class='profile-pic'>";
    }
    if ($bio) {
        echo "<div class='bio-block'><strong>Bio:</strong><br>" . nl2br(htmlspecialchars($bio)) . "</div>";
    }
    echo "<table>";
    echo "<tr><th>Name</th><td>".htmlspecialchars($name)."</td></tr>";
    echo "<tr><th>Username</th><td>".htmlspecialchars($username)."</td></tr>";
    echo "<tr><th>Registration Number</th><td>".htmlspecialchars($reg_number)."</td></tr>";
    echo "<tr><th>Course</th><td>".htmlspecialchars($course)."</td></tr>";
    echo "</table>";
    // Show videos only if allowed, otherwise show notice
    if ($show_videos) {
        $videos = [];
        $resv = $conn->query("SELECT video_filename FROM user_videos WHERE username='" . $conn->real_escape_string($username) . "'");
        while ($rowv = $resv->fetch_assoc()) {
            $videos[] = $rowv['video_filename'];
        }
        if (count($videos) > 0) {
            echo "<b>User's Videos:</b><br><div style='display:flex;gap:18px;flex-wrap:wrap;margin-top:8px;'>";
            foreach ($videos as $v) {
                echo "<div><video width='160' height='100' controls><source src='uploads/" . htmlspecialchars($v) . "' type='video/mp4'></video></div>";
            }
            echo "</div>";
        }
    } else {
        echo "<div style='margin:18px 0 0 0; color:#d81b60; font-weight:500; text-align:center;'>This user has chosen not to share their videos publicly.</div>";
    }

    // Received Files Section (only show if viewing own profile)
    if (isset($_SESSION['username']) && $_SESSION['username'] === $username) {
        $conn_rf = new mysqli($servername, $username_db, $password_db, $dbname);
        $stmt_rf = $conn_rf->prepare("SELECT sender, file_path, file_type, original_name, from_profile, timestamp FROM shared_files WHERE recipient=? ORDER BY timestamp DESC");
        $stmt_rf->bind_param("s", $username);
        $stmt_rf->execute();
        $stmt_rf->store_result();
        if ($stmt_rf->num_rows > 0) {
            $stmt_rf->bind_result($rf_sender, $rf_path, $rf_type, $rf_orig, $rf_from_profile, $rf_time);
            echo "<div style='margin-top:28px;'><b>Received Files:</b><div style='margin-top:10px;'>";
            while ($stmt_rf->fetch()) {
                echo "<div style='margin-bottom:12px;padding:8px 12px;background:#f5faff;border-radius:7px;box-shadow:0 1px 4px #1976d211;'>";
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
            }
            echo "</div></div>";
        }
        $stmt_rf->close();
        $conn_rf->close();
    }
    echo "<div style='margin-top:22px;text-align:center;'><a href='messages.php' style='color:#1976d2;'>Back to Messages</a></div>";
    echo "</div></body></html>";
} else {
    echo "User not found.";
}
$stmt->close();
$conn->close();
?>
