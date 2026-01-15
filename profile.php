<?php
require_once 'config.php';
$user_id = 0;  

// Handle both ?id=1 (own profile) and ?username=alice@example.com (public profile)
$view_user_id = $_GET['id'] ?? 0;
$username = $_GET['username'] ?? '';

if ($view_user_id) {
    // Own profile view
    $stmt = $conn->prepare("SELECT id, name, email, bio, age, location, pronouns, profile_pic, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $view_user_id);
} else if ($username) {
    // Public profile view
    $stmt = $conn->prepare("SELECT id, name, email, bio, age, location, pronouns, profile_pic, created_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $username);
} else {
    header("Location: dashboard.php");
    exit;
}

$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    echo "User not found";
    exit;
}

// Log profile view (if logged in AND viewing someone else)
if (isLoggedIn() && getUserId() != $profile['id']) {
    $viewer_id = getUserId();
    $stmt = $conn->prepare("INSERT IGNORE INTO profile_views (viewer_id, viewed_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $viewer_id, $profile['id']);
    $stmt->execute();
    $stmt->close();
}

// BULLETPROOF DEBUG VERSION - Shows EXACT values
$is_logged_in = isLoggedIn();
$current_user_id = getUserId();
$can_send = false;
$sent_count = 0;

if ($is_logged_in && $current_user_id != $profile['id']) {
    $sent_check = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE sender_id = ? AND recipient_username = ?");
    $sent_check->bind_param("is", $current_user_id, $profile['email']);
    $sent_check->execute();
    $sent_count = $sent_check->get_result()->fetch_assoc()['count'];
    $sent_check->close();
    $can_send = ($sent_count == 0);
}




?>

<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['name']) ?>'s Profile - AskHole 💜</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    
    <style>
       
        * { margin: 0; padding: 0; box-sizing: border-box; }
      
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-left">
            <a href="index.php" class="logo">🏠 AskHole</a>
        </div>
        <div class="nav-buttons">
            <a href="index.php" class="nav-btn home">🌐 Home</a>
            <a href="dashboard.php" class="nav-btn">🏠 Dashboard</a>
            <a href="profile.php?id=<?= getUserId() ?>" class="nav-btn active">👤 Profile</a>
            <a href="edit_profile.php" class="nav-btn primary">✏️ Edit Profile</a>
            <a href="logout.php" class="nav-btn logout">Logout</a>
        </div>
    </div>

    <div class="profile-container">
        <!-- Your existing profile header stays exactly the same -->
        <div class="profile-header">
            <div class="profile-pic">
                <?php if ($profile['profile_pic'] && file_exists($profile['profile_pic'])): ?>
                    <img src="<?= htmlspecialchars($profile['profile_pic']) ?>" 
                         alt="<?= htmlspecialchars($profile['name']) ?>" 
                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="profile-name"><?= htmlspecialchars($profile['name']) ?></div>
            <?php if ($profile['pronouns']): ?>
                <div class="profile-pronouns"><?= htmlspecialchars($profile['pronouns']) ?></div>
            <?php endif; ?>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-number"><?= $profile['age'] ?? '?' ?></div>
                    <div>Age</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= htmlspecialchars($profile['location'] ?: '?') ?></div>
                    <div>Location</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">Member since<br><?= date('M Y', strtotime($profile['created_at'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Your existing bio section stays exactly the same -->
        <div class="profile-bio-section">
            <h2 class="profile-bio-title">📝 About Me</h2>
            <?php if ($profile['bio']): ?>
                <div class="profile-bio-text"><?= nl2br(htmlspecialchars($profile['bio'])) ?></div>
            <?php else: ?>
                <div class="profile-bio-text" style="color: rgba(243,232,255,0.6); font-style: italic;">
                    No bio yet. Say hello with 5 questions! 💜
                </div>
            <?php endif; ?>
        </div>

        <?php if ($is_logged_in && $can_send): ?>
            <a href="ask_questions.php?recipient=<?= urlencode($profile['email']) ?>" class="send-questions-btn">
                🚀 Send 5 Questions to <?= htmlspecialchars($profile['name']) ?>
            </a>
        <?php elseif ($is_logged_in && $current_user_id != $profile['id']): ?>
            <div class="already-sent">
                ✅ You already sent questions to <?= htmlspecialchars($profile['name']) ?>!
            </div>
        <?php elseif ($current_user_id == $profile['id']): ?>
            <div style="background: rgba(59, 130, 246, 0.2); color: #bfdbfe; border: 2px solid #3b82f6; padding: 20px; border-radius: 15px; text-align: center;">
                👤 This is your profile 💜
            </div>
        <?php else: ?>
            <div class="login-prompt">
                <strong>💜 Login to send questions!</strong><br>
                <a href="login.php" style="color: #8b5cf6; font-weight: bold; text-decoration: underline;">Login →</a> 
                or <a href="signup.php" style="color: #8b5cf6; font-weight: bold; text-decoration: underline;">Sign Up</a>
            </div>
        <?php endif; ?>


        <!-- Updated share link works for both modes -->
        <div class="share-link">
            📎 Share this profile: <br>
            <strong>http://localhost/askHole/profile.php?username=<?= urlencode($profile['email']) ?></strong>
        </div>
    </div>
</body>
</html>
