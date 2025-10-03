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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Load current values as defaults so failed/blocked uploads won't wipe fields
    $defaults_stmt = $conn->prepare("SELECT name, reg_number, phone, email, course, bio, profile_pic, show_videos FROM registration WHERE username=?");
    $defaults_stmt->bind_param("s", $username);
    $defaults_stmt->execute();
    $defaults_stmt->store_result();
    if ($defaults_stmt->num_rows === 1) {
        $defaults_stmt->bind_result($d_name, $d_reg_number, $d_phone, $d_email, $d_course, $d_bio, $d_profile_pic, $d_show_videos);
        $defaults_stmt->fetch();
    } else {
        $d_name = $d_reg_number = $d_phone = $d_email = $d_course = $d_bio = $d_profile_pic = null;
        $d_show_videos = 1;
    }
    $defaults_stmt->close();

    // Use POST values when present, otherwise keep defaults
    $name = isset($_POST['name']) ? $_POST['name'] : $d_name;
    $reg_number = isset($_POST['reg_number']) ? $_POST['reg_number'] : $d_reg_number;
    $phone = isset($_POST['phone']) ? $_POST['phone'] : $d_phone;
    $email = isset($_POST['email']) ? $_POST['email'] : $d_email;
    $course = isset($_POST['course']) ? $_POST['course'] : $d_course;
    $bio = isset($_POST['bio']) ? $_POST['bio'] : $d_bio;
    $show_videos = isset($_POST['show_videos']) ? (int)$_POST['show_videos'] : $d_show_videos;
    $profile_pic = null;
    $remove_pic = isset($_POST['remove_pic']) ? true : false;
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profile_', true) . '.' . $ext;
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)) {
            $profile_pic = $filename;
        }
    }
    // Handle video uploads (up to 3)
    if (isset($_FILES['videos'])) {
        $video_files = $_FILES['videos'];
        $uploaded_count = 0;
        // Count current videos
        $result = $conn->query("SELECT COUNT(*) as cnt FROM user_videos WHERE username='" . $conn->real_escape_string($username) . "'");
        $row = $result->fetch_assoc();
        $current_count = (int)$row['cnt'];
        // Normalize to array for single/multiple upload
        $names = is_array($video_files['name']) ? $video_files['name'] : [$video_files['name']];
        $types = is_array($video_files['type']) ? $video_files['type'] : [$video_files['type']];
        $tmp_names = is_array($video_files['tmp_name']) ? $video_files['tmp_name'] : [$video_files['tmp_name']];
        $errors = is_array($video_files['error']) ? $video_files['error'] : [$video_files['error']];
        $sizes = is_array($video_files['size']) ? $video_files['size'] : [$video_files['size']];
        for ($i = 0; $i < count($names); $i++) {
            if ($errors[$i] === UPLOAD_ERR_OK && $current_count + $uploaded_count < 3) {
                $ext = pathinfo($names[$i], PATHINFO_EXTENSION);
                $filename = uniqid('video_', true) . '.' . $ext;
                $target = 'uploads/' . $filename;
                if (move_uploaded_file($tmp_names[$i], $target)) {
                    $stmtv = $conn->prepare("INSERT INTO user_videos (username, video_filename) VALUES (?, ?)");
                    $stmtv->bind_param("ss", $username, $filename);
                    $stmtv->execute();
                    $stmtv->close();
                    $uploaded_count++;
                }
            }
        }
    }
    // Remove selected videos
    if (isset($_POST['remove_videos']) && is_array($_POST['remove_videos'])) {
        foreach ($_POST['remove_videos'] as $vid) {
            // Delete file from uploads
            $res = $conn->query("SELECT video_filename FROM user_videos WHERE id=" . intval($vid) . " AND username='" . $conn->real_escape_string($username) . "'");
            if ($row = $res->fetch_assoc()) {
                $file = 'uploads/' . $row['video_filename'];
                if (file_exists($file)) unlink($file);
            }
            $conn->query("DELETE FROM user_videos WHERE id=" . intval($vid) . " AND username='" . $conn->real_escape_string($username) . "'");
        }
    }
    // Update user details
    $fields = "name=?, reg_number=?, phone=?, email=?, course=?, bio=?, show_videos=?";
    $types = "ssssssi";
    $params = [$name, $reg_number, $phone, $email, $course, $bio, $show_videos];
    if ($profile_pic) {
        $fields .= ", profile_pic=?";
        $types .= "s";
        $params[] = $profile_pic;
    } else if ($remove_pic) {
        $fields .= ", profile_pic=NULL";
    }
    $fields .= " WHERE username=?";
    $types .= "s";
    $params[] = $username;
    $stmt = $conn->prepare("UPDATE registration SET $fields");
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        echo "<p>Details updated successfully. <a href='view.php'>Back to profile</a></p>";
    } else {
        echo "<p>Error updating details.</p>";
    }
    $stmt->close();
    $conn->close();
    exit();
}
// Fetch user details
$stmt = $conn->prepare("SELECT name, reg_number, phone, email, course, profile_pic, bio, show_videos FROM registration WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 1) {
    $stmt->bind_result($name, $reg_number, $phone, $email, $course, $profile_pic, $bio, $show_videos);
    $stmt->fetch();
    echo "<h2>Edit Details</h2>";
    // Profile picture at the top
    echo "<form method='POST' enctype='multipart/form-data' style='max-width:700px;'>";
    if ($profile_pic) {
        echo "<img src='uploads/" . htmlspecialchars($profile_pic) . "' alt='Profile Picture' width='120' style='border-radius:50%;margin-bottom:15px;display:block;'>";
        echo "<label><input type='checkbox' name='remove_pic' value='1'> Remove Profile Picture</label><br><br>";
    }
    echo "Change Profile Picture: <input type='file' name='profile_pic' accept='image/*'><br><br>";
    // Details and bio
    echo "<div style='margin-top:10px;'>";
    echo "Name: <input type='text' name='name' value='" . htmlspecialchars($name) . "' required><br><br>";
    echo "Registration Number: <input type='text' name='reg_number' value='" . htmlspecialchars($reg_number) . "' required><br><br>";
    echo "Phone: <input type='text' name='phone' value='" . htmlspecialchars($phone) . "' required><br><br>";
    echo "Email: <input type='email' name='email' value='" . htmlspecialchars($email) . "' required><br><br>";
    echo "Course: <input type='text' name='course' value='" . htmlspecialchars($course) . "' required><br><br>";
    echo "Bio:<br><textarea name='bio' rows='4' cols='40'>" . htmlspecialchars($bio ?? '') . "</textarea><br><br>";
    // Show videos privacy option
    echo "<label for='show_videos'>Allow other users to view my videos:</label> ";
    echo "<select id='show_videos' name='show_videos' required>";
    echo "<option value='1'" . ($show_videos ? ' selected' : '') . ">Yes</option>";
    echo "<option value='0'" . (!$show_videos ? ' selected' : '') . ">No</option>";
    echo "</select><br><br>";
    echo "<input type='submit' value='Update'>";
    echo "</div>";
    // Videos at the bottom
    $videos = [];
    $resv = $conn->query("SELECT id, video_filename FROM user_videos WHERE username='" . $conn->real_escape_string($username) . "'");
    while ($rowv = $resv->fetch_assoc()) {
        $videos[] = $rowv;
    }
    echo "<div style='margin-top:30px;'>";
    if (count($videos) > 0) {
        echo "<b>Your Videos:</b><br>";
        echo "<div style='display:flex;gap:20px;flex-wrap:wrap;margin-bottom:10px;'>";
        foreach ($videos as $v) {
            echo "<div style='display:flex;flex-direction:column;align-items:center;'>";
            echo "<video width='200' height='120' controls style='margin-bottom:5px;'><source src='uploads/" . htmlspecialchars($v['video_filename']) . "' type='video/mp4'></video>";
            echo "<label style='font-size:13px;'><input type='checkbox' name='remove_videos[]' value='" . $v['id'] . "'> Remove</label>";
            echo "</div>";
        }
        echo "</div>";
    }
    $remaining = 3 - count($videos);
    if ($remaining > 0) {
        echo "Upload Videos (You can add up to $remaining more): <input type='file' name='videos[]' accept='video/*' multiple><br><br>";
    }
    echo "</div>";
    echo "</form>";

    // STEP 1: Insert Biometric management placeholder UI (status only for now)
    echo "<div id='biometricSection' style='margin-top:35px;padding:15px;border:1px solid #ccc;border-radius:8px;background:#f9f9f9;max-width:700px;'>";
    echo "<h3 style='margin:0 0 10px;'>Biometric Login</h3>";
    echo "<p id='bioStatus' style='font-size:14px;color:#555;margin:0 0 10px;'>Loading status...</p>";
    echo "<div id='bioActions' style='display:block;margin-bottom:8px;'>";
    echo "<button type='button' id='enableBioBtn' style='padding:6px 14px;cursor:pointer;'>Enable Biometrics</button>";
    echo "<button type='button' id='disableBioBtn' style='padding:6px 14px;cursor:pointer;display:none;background:#c62828;color:#fff;border:1px solid #b71c1c;'>Disable Biometrics</button>";
    echo "</div>";
    echo "<div id='bioMsg' style='font-size:13px;min-height:18px;color:#333;'></div>";
    echo "<p style='font-size:12px;color:#777;margin:10px 0 0;'>Use fingerprint/face available on this device. This is experimental and may require a secure (HTTPS) context on some browsers.</p>";
    echo "</div>";
    echo "<script>\n(async function(){\n  const statusEl = document.getElementById('bioStatus');\n  const enableBtn = document.getElementById('enableBioBtn');\n  const disableBtn = document.getElementById('disableBioBtn');\n  const msgEl = document.getElementById('bioMsg');\n  const currentUser = '" . addslashes($username) . "';\n\n  function uiBusy(b){\n    enableBtn.disabled = disableBtn.disabled = b;\n    if(b){ enableBtn.style.opacity = disableBtn.style.opacity = 0.6; } else { enableBtn.style.opacity = disableBtn.style.opacity = 1; }\n  }\n\n  function bufferToB64url(buf){\n    const bytes = new Uint8Array(buf);\n    let str = '';\n    for (let i=0;i<bytes.length;i++){ str += String.fromCharCode(bytes[i]); }\n    return btoa(str).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');\n  }\n  function b64urlToBuffer(b64url){\n    let pad = b64url.replace(/-/g,'+').replace(/_/g,'/');\n    while(pad.length % 4) pad += '=';\n    const str = atob(pad);\n    const bytes = new Uint8Array(str.length);\n    for (let i=0;i<str.length;i++) bytes[i] = str.charCodeAt(i);\n    return bytes.buffer;\n  }\n  function randBuf(len){ const a = new Uint8Array(len); crypto.getRandomValues(a); return a; }\n\n  async function refreshStatus(){\n    try {\n      const r = await fetch('biometric_status.php', {credentials:'same-origin'});\n      const data = await r.json();\n      const enabled = !!data.biometric_enabled;\n      statusEl.textContent = enabled ? 'Biometric login is currently ENABLED.' : 'Biometric login is currently DISABLED.';\n      enableBtn.style.display = enabled ? 'none' : 'inline-block';\n      disableBtn.style.display = enabled ? 'inline-block' : 'none';\n    } catch(e){\n      statusEl.textContent = 'Could not load biometric status.';\n    }\n  }\n  window.refreshBiometricStatus = refreshStatus;\n\n  if(!window.PublicKeyCredential){\n     statusEl.textContent = 'This browser does not support WebAuthn.';\n     enableBtn.style.display = 'none';\n     disableBtn.style.display = 'none';\n     return;\n  }\n\n  enableBtn.addEventListener('click', async () => {\n    msgEl.textContent = '';\n    uiBusy(true);\n    try {\n      const challenge = randBuf(16);\n      const userId = randBuf(16); // random stable ID per credential (not persisted here)\n      const pubKey = {\n        challenge,\n        rp: { name: 'Student Portal' },\n        user: { id: userId, name: currentUser, displayName: currentUser },\n        pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],\n        authenticatorSelection: { userVerification: 'preferred' },\n        timeout: 60000,\n        attestation: 'none'\n      };\n      const cred = await navigator.credentials.create({ publicKey: pubKey });\n      if(!cred){ throw new Error('Creation returned null'); }\n      const credId = bufferToB64url(cred.rawId);\n      const payload = { id: credId, rawId: credId };\n      const form = new FormData();\n      form.append('credential', JSON.stringify(payload));\n      const resp = await fetch('enable_biometric.php', { method: 'POST', body: form, credentials: 'same-origin' });\n      const j = await resp.json();\n      if(j.success){\n        msgEl.style.color = '#2e7d32';\n        msgEl.textContent = (j.status==='exists'?'Already enabled.':'Biometric credential saved.');\n        await refreshStatus();\n      } else {\n        msgEl.style.color = '#c62828';\n        msgEl.textContent = 'Enable failed: ' + (j.error || 'unknown');\n      }\n    } catch(e){\n      msgEl.style.color = '#c62828';\n      msgEl.textContent = 'Enable error: ' + e.message;\n    } finally { uiBusy(false); }\n  });\n\n  disableBtn.addEventListener('click', async () => {\n    msgEl.textContent = '';\n    uiBusy(true);\n    try {\n      // fetch stored credential id\n      const r = await fetch('get_webauthn_id.php?username=' + encodeURIComponent(currentUser), {credentials:'same-origin'});\n      const data = await r.json();\n      const credId = data.credential_id;\n      if(!credId){ throw new Error('No credential to disable'); }\n      const allow = [{ type:'public-key', id: b64urlToBuffer(credId) }];\n      const assertion = await navigator.credentials.get({ publicKey: { challenge: randBuf(16), allowCredentials: allow, userVerification:'preferred', timeout:60000 } });\n      if(!assertion){ throw new Error('Assertion failed'); }\n      // Minimal trust: only use assertion.id (no signature verify)\n      const form = new FormData();\n      form.append('credential_id', bufferToB64url(assertion.rawId));\n      const resp = await fetch('disable_biometric.php', { method:'POST', body: form, credentials:'same-origin' });\n      const j = await resp.json();\n      if(j.success){\n        msgEl.style.color = '#2e7d32';\n        msgEl.textContent = 'Biometric login disabled.';\n        await refreshStatus();\n      } else {\n        msgEl.style.color = '#c62828';\n        msgEl.textContent = 'Disable failed: ' + (j.error || 'unknown');\n      }\n    } catch(e){\n      msgEl.style.color = '#c62828';\n      msgEl.textContent = 'Disable error: ' + e.message;\n    } finally { uiBusy(false); }\n  });\n\n  await refreshStatus();\n})();\n</script>";

    echo "<br><a href='view.php'>Cancel</a>";
} else {
    echo "No details found.";
}
$stmt->close();
$conn->close();
?>
