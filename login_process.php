<?php
session_start();
require_once 'db_connect.php';
if (!$conn) die("Connection failed");

// Get form data
$username = trim($_POST["username"] ?? '');
$password = $_POST["password"] ?? '';
$bioRaw   = $_POST['webauthn_login_credential'] ?? '';

// Helper: Dice loader output
function show_dice_loader($username, $conn) {
    $profile_pic = null;
    $stmt_pic = $conn->prepare("SELECT profile_pic FROM registration WHERE username=? LIMIT 1");
    $stmt_pic->bind_param("s", $username);
    $stmt_pic->execute();
    $stmt_pic->bind_result($profile_pic);
    $stmt_pic->fetch();
    $stmt_pic->close();

    $facts = [
        'Honey never spoils.',
        'Bananas are berries, but strawberries are not.',
        'A group of flamingos is called a flamboyance.',
        'Octopuses have three hearts.',
        'There are more trees on Earth than stars in the Milky Way.',
        'Humans share 60% of their DNA with bananas.',
        'A single strand of spaghetti is called a “spaghetto.”',
        'Nairobi is the only capital city in the world located on the equator.',
        'The Eiffel Tower can be 15 cm taller during hot days.',
        'Some turtles can breathe through their butts.',
        'There are more stars in the universe than grains of sand on Earth.',
        'Wombat poop is cube-shaped.',
        'A day on Venus is longer than a year on Venus.',
        'Mosquitoes are attracted to the color blue twice as much as to any other color.'
    ];
    $fact = $facts[array_rand($facts)];

    echo '<!DOCTYPE html><html><head><title>Logging in...</title><style>
body { background: linear-gradient(135deg, #1976d2 0%, #e6f0ff 100%); margin:0; height:100vh; overflow:hidden; display:flex; align-items:center; justify-content:center; }
.dice-loader-container { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; width:100vw; }
.dice-label { font-family:Segoe UI,Arial,sans-serif; font-size:1.4em; color:#1976d2; margin-top:38px; letter-spacing:1px; text-shadow:0 2px 8px #fff,0 0 2px #1976d2; }
.dice-fact { font-family:Segoe UI,Arial,sans-serif; font-size:1.1em; color:#333; margin-top:18px; background:rgba(255,255,255,0.85); border-radius:8px; padding:10px 18px; box-shadow:0 2px 8px rgba(25,118,210,0.07); max-width:340px; text-align:center; }
.dice-3d { width:90px; height:90px; perspective:400px; }
.cube { width:90px; height:90px; position:relative; transform-style:preserve-3d; animation:rollDice 2s cubic-bezier(.45,.05,.55,.95) infinite; }
.face { position:absolute; width:90px; height:90px; border-radius:18px; box-shadow:0 2px 12px rgba(25,118,210,0.10); display:flex; align-items:center; justify-content:center; font-size:2.5em; font-weight:bold; }
.face1 { background:#fffbe7; border:3px solid #ffd600; transform:rotateY(0deg) translateZ(45px); overflow:hidden; }
.face2 { background:#e3f2fd; border:3px solid #1976d2; transform:rotateY(180deg) translateZ(45px); }
.face3 { background:#fce4ec; border:3px solid #d81b60; transform:rotateY(90deg) translateZ(45px); }
.face4 { background:#e8f5e9; border:3px solid #43a047; transform:rotateY(-90deg) translateZ(45px); }
.face5 { background:#f3e5f5; border:3px solid #8e24aa; transform:rotateX(90deg) translateZ(45px); }
.face6 { background:#fff3e0; border:3px solid #fb8c00; transform:rotateX(-90deg) translateZ(45px); }
@keyframes rollDice {
    0% { transform:rotateX(0deg) rotateY(0deg); }
    20% { transform:rotateX(180deg) rotateY(0deg); }
    40% { transform:rotateX(180deg) rotateY(180deg); }
    60% { transform:rotateX(360deg) rotateY(180deg); }
    80% { transform:rotateX(360deg) rotateY(360deg); }
    100% { transform:rotateX(0deg) rotateY(0deg); }
}
.dot { width:16px; height:16px; background:#1976d2; border-radius:50%; display:inline-block; margin:2px; }
.profile-face-img { width:70px; height:70px; border-radius:12px; object-fit:cover; border:2px solid #ffd600; box-shadow:0 2px 8px #ffd60055; }
.emoji-face { font-size:2.2em; }
</style></head><body>';
    echo '<div class="dice-loader-container">';
    echo '<div class="dice-3d"><div class="cube">';
    // Face 1: profile pic or emoji
    echo '<div class="face face1">';
    if ($profile_pic) {
        echo '<img src="uploads/' . htmlspecialchars($profile_pic) . '" class="profile-face-img" alt="Profile">';
    } else {
        echo '<span class="emoji-face">🎲</span>';
    }
    echo '</div>';
    // Face 2
    echo '<div class="face face2"><span class="dot"></span><span class="dot" style="margin-left:40px;"></span></div>';
    // Face 3
    echo '<div class="face face3"><span class="dot"></span><span class="dot" style="margin-left:20px;"></span><span class="dot" style="margin-left:20px;"></span></div>';
    // Face 4
    echo '<div class="face face4"><span class="dot"></span><span class="dot" style="margin-left:40px;"></span><br><span class="dot" style="margin-top:40px;"></span><span class="dot" style="margin-left:40px;margin-top:40px;"></span></div>';
    // Face 5
    echo '<div class="face face5"><span class="dot"></span><span class="dot" style="margin-left:40px;"></span><span class="dot" style="margin-left:20px;margin-top:20px;"></span><span class="dot" style="margin-left:40px;margin-top:40px;"></span><span class="dot" style="margin-left:20px;margin-top:40px;"></span></div>';
    // Face 6
    echo '<div class="face face6"><span class="dot"></span><span class="dot" style="margin-left:20px;"></span><span class="dot" style="margin-left:40px;"></span><br><span class="dot" style="margin-top:20px;"></span><span class="dot" style="margin-left:20px;margin-top:20px;"></span><span class="dot" style="margin-left:40px;margin-top:20px;"></span></div>';
    echo '</div></div>';
    echo '<div class="dice-label">Youre being logged in...</div>';
    echo '<div class="dice-fact">Did you know? ' . htmlspecialchars($fact) . '</div>';
    echo '</div>';
    echo '<script>setTimeout(function(){ window.location.href="view.php"; }, 5000);</script>';
    echo '</body></html>';
    exit();
}

// --- Biometric login path ---
if (!empty($bioRaw)) {
    // Strict matching of credential id (base64url) with stored credential.
    // NOTE: Ensure your login page JS fetches the stored credential_id from backend
    // (e.g., get_webauthn_id.php) and uses it in allowCredentials; do NOT use the username bytes.
    $STRICT_WEBAUTHN = true; // set to false temporarily ONLY for transitional debugging

    $cred = json_decode($bioRaw, true);
    if (!$cred || empty($cred['id'])) {
        echo "Invalid biometric credential payload.";
        exit();
    }
    $assertedId = $cred['id']; // should already be base64url string from authenticator

    // Fetch any stored credential ids for this username
    $stmt_wa = $conn->prepare("SELECT credential_id FROM webauthn_credentials WHERE username=?");
    $stmt_wa->bind_param("s", $username);
    $stmt_wa->execute();
    $res = $stmt_wa->get_result();
    $match = false;
    $storedSingle = null;
    while ($row = $res->fetch_assoc()) {
        if ($row['credential_id'] === $assertedId) {
            $match = true;
            break;
        }
        $storedSingle = $row['credential_id'];
    }
    $stmt_wa->close();

    if (!$match && !$STRICT_WEBAUTHN && $storedSingle && !$res) {
        // Transitional (very weak) fallback: if only one credential exists, accept even if id mismatch.
        // SECURITY: Disable after fixing front-end allowCredentials usage.
        $match = true;
    }

    if ($match) {
        $_SESSION['username'] = $username;
        show_dice_loader($username, $conn);
    } else {
        echo "Biometric credential not recognized. (Tip: Ensure login JS uses stored credential_id in allowCredentials)";
    }
    exit();
}

// --- Password login path ---
if (empty($username) || empty($password)) {
    echo "Both fields are required.";
    exit();
}

$stmt = $conn->prepare("SELECT password FROM registration WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    if (password_verify($password, $hashed_password)) {
        $_SESSION['username'] = $username;
        show_dice_loader($username, $conn);
    } else {
        echo "Invalid password.";
    }
} else {
    echo "Username not found.";
}
$stmt->close();
$conn->close();
?>
