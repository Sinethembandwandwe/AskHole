<?php
require_once 'config.php';
requireLogin();

$user_id = getUserId();

// Fetch current profile data
$stmt = $conn->prepare("
    SELECT name, bio, age, location, pronouns, profile_pic 
    FROM users WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submission
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $location = trim($_POST['location'] ?? '');
    $pronouns = trim($_POST['pronouns'] ?? '');
    
    // Profile picture handling
    $profile_pic = $user_data['profile_pic'] ?? '';
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_filename = $user_id . '_' . time() . '.' . $ext;
        $upload_path = 'uploads/' . $new_filename;
        
        if (in_array($ext, $allowed) && $file['size'] < 5000000) {
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if ($profile_pic && file_exists($profile_pic)) {
                    unlink($profile_pic);
                }
                $profile_pic = $upload_path;
            } else {
                $error = "Failed to move uploaded file.";
            }
        } else {
            $error = "Invalid file type or too large (5MB max).";
        }
    }
    
    // SINGLE FIXED UPDATE (REMOVED DUPLICATES)
    $sql = "UPDATE users SET bio = ?, location = ?, pronouns = ?, profile_pic = ?";
    $types = "ssss";
    $params = [$bio, $location, $pronouns, $profile_pic];
    
    if ($age !== null) {
        $sql .= ", age = ?";
        $types .= "i";
        $params[] = $age;
    }
    
    $sql .= " WHERE id = ?";
    $types .= "i";
    $params[] = $user_id;
    
    $update_stmt = $conn->prepare($sql);
    if (!$update_stmt) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $update_stmt->bind_param($types, ...$params);
        if ($update_stmt->execute()) {
            $success = "✅ Profile updated successfully! 💜";
            // REFRESH user_data after update
            $stmt = $conn->prepare("SELECT name, bio, age, location, pronouns, profile_pic FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Execute failed: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - AskHole 💜</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0d2e 30%, #2d1b69 70%, #8b5cf6 100%);
        }
        .navbar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 20px 40px; max-width: 1200px; margin: 20px auto;
            background: rgba(91, 44, 145, 0.95); border-radius: 20px;
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.3);
        }
        .nav-left .logo { font-size: 1.8em; font-weight: bold; color: #c084fc; text-decoration: none; }
        .nav-buttons { display: flex; gap: 15px; align-items: center; }
        .nav-btn {
            padding: 12px 24px; background: rgba(139, 92, 246, 0.3); color: #f3e8ff;
            text-decoration: none; border-radius: 20px; font-weight: 600;
            border: 2px solid rgba(139, 92, 246, 0.4); transition: all 0.3s ease;
        }
        .nav-btn:hover { background: rgba(139, 92, 246, 0.5); transform: translateY(-2px); }
        .nav-btn.active, .nav-btn.primary { background: linear-gradient(45deg, #8b5cf6, #c084fc); border-color: #8b5cf6; }
        .nav-btn.home { background: linear-gradient(45deg, #3b82f6, #60a5fa); border-color: #3b82f6; }
        .nav-btn.logout { background: linear-gradient(45deg, #ef4444, #f87171); border-color: #ef4444; }
        .edit-container {
            max-width: 700px; margin: 40px auto; padding: 40px;
            background: rgba(91, 44, 145, 0.95); border-radius: 25px;
            border: 1px solid rgba(139, 92, 246, 0.4); box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(20px);
        }
        .edit-header { text-align: center; margin-bottom: 40px; color: #f3e8ff; }
        .edit-header h1 {
            font-size: 2.5em; background: linear-gradient(45deg, #c084fc, #8b5cf6);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 10px;
        }
        .form-group { margin-bottom: 30px; }
        label { display: block; color: #f3e8ff; font-size: 1.1em; font-weight: 600; margin-bottom: 10px; }
        input[type="text"], input[type="number"], input[type="file"], textarea, select {
            width: 100%; padding: 20px; border-radius: 15px; border: 2px solid rgba(139, 92, 246, 0.4);
            background: rgba(243, 232, 255, 0.1); color: #f3e8ff; font-size: 16px; font-family: inherit;
            transition: all 0.3s ease; resize: vertical;
        }
        textarea { min-height: 150px; max-height: 300px; }
        input:focus, textarea:focus, select:focus {
            outline: none; border-color: #8b5cf6; box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
            background: rgba(243, 232, 255, 0.2);
        }
        input::placeholder, textarea::placeholder { color: rgba(243, 232, 255, 0.6); }
        .profile-preview { text-align: center; margin-bottom: 20px; }
        .profile-preview img {
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
            border: 4px solid #8b5cf6; box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
        }
        .button-group { display: flex; gap: 20px; justify-content: center; margin-top: 40px; flex-wrap: wrap; }
        .save-btn {
            flex: 1; min-width: 180px; padding: 20px 40px; background: linear-gradient(45deg, #10b981, #34d399);
            color: white; border: none; font-size: 1.2em; font-weight: bold; border-radius: 20px;
            cursor: pointer; box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4); transition: all 0.4s ease;
        }
        .save-btn:hover { transform: translateY(-5px); box-shadow: 0 20px 45px rgba(16, 185, 129, 0.6); }
        .cancel-btn {
            flex: 1; min-width: 180px; padding: 20px 40px; background: rgba(139, 92, 246, 0.3);
            color: #f3e8ff; border: 2px solid rgba(139, 92, 246, 0.5); font-size: 1.2em; font-weight: bold;
            border-radius: 20px; text-decoration: none; text-align: center; line-height: 24px;
            transition: all 0.3s ease;
        }
        .cancel-btn:hover { background: rgba(139, 92, 246, 0.5); transform: translateY(-3px); }
        .msg { padding: 25px; border-radius: 18px; margin-bottom: 30px; font-weight: 600; text-align: center; font-size: 1.2em; }
        .success { background: rgba(16, 185, 129, 0.25); color: #d1fae5; border-left: 6px solid #10b981; }
        .error { background: rgba(239, 68, 68, 0.25); color: #fee2e2; border-left: 6px solid #ef4444; }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="index.php" class="logo">🏠 AskHole</a>
        </div>
        <div class="nav-buttons">
            <a href="index.php" class="nav-btn home">🌐 Home</a>
            <a href="dashboard.php" class="nav-btn">🏠 Dashboard</a>
            <a href="profile.php?id=<?= $user_id ?>" class="nav-btn">👤 Profile</a>
            <a href="edit_profile.php" class="nav-btn active primary">✏️ Edit Profile</a>
            <a href="logout.php" class="nav-btn logout">Logout</a>
        </div>
    </div>

    <div class="edit-container">
        <div class="edit-header">
            <h1>✏️ Edit Your Profile</h1>
            <p style="color: #c084fc;">Make your profile shine! 💜</p>
        </div>

        <?php if ($success): ?>
            <div class="msg success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg error"><?= $error ?></div>
        <?php endif; ?>

        <!-- FORM ALWAYS SHOWS + FIELDS ALWAYS VISIBLE -->
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>🖼️ Profile Picture:</label>
                <div class="profile-preview">
                    <?php if ($user_data['profile_pic'] && file_exists($user_data['profile_pic'])): ?>
                        <img src="<?= htmlspecialchars($user_data['profile_pic']) ?>" alt="Current Profile Pic">
                        <br><small style="color: #c084fc;">Current image</small>
                    <?php endif; ?>
                </div>
                <input type="file" name="profile_pic" accept="image/*">
                <small style="color: #c084fc;">JPG, PNG, GIF (Max 5MB) - Leave empty to keep current</small>
            </div>

            <div class="form-group">
                <label>📍 Location (City/Country):</label>
                <input type="text" name="location" 
                       value="<?= htmlspecialchars($user_data['location'] ?? '') ?>"
                       placeholder="e.g. Cape Town, South Africa">
            </div>

            <div class="form-group">
                <label>🎂 Age:</label>
                <input type="number" name="age" min="13" max="100"
                       value="<?= $user_data['age'] ?? '' ?>"
                       placeholder="Optional">
            </div>

            <div class="form-group">
                <label>🏳️ Pronouns:</label>
                <select name="pronouns">
                    <option value="">Select pronouns (optional)</option>
                    <option value="he/him" <?= ($user_data['pronouns'] ?? '') === 'he/him' ? 'selected' : '' ?>>he/him</option>
                    <option value="she/her" <?= ($user_data['pronouns'] ?? '') === 'she/her' ? 'selected' : '' ?>>she/her</option>
                    <option value="they/them" <?= ($user_data['pronouns'] ?? '') === 'they/them' ? 'selected' : '' ?>>they/them</option>
                    <option value="other" <?= ($user_data['pronouns'] ?? '') === 'other' ? 'selected' : '' ?>>other</option>
                </select>
            </div>

            <div class="form-group">
                <label>✨ Bio (Tell people about you!):</label>
                <textarea name="bio" placeholder="Love deep conversations 💜 Hiking enthusiast. Coffee dates welcome! Looking for genuine connections..."><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
            </div>

            <div class="button-group">
                <button type="submit" class="save-btn">💾 Save Changes</button>
                <a href="profile.php?id=<?= $user_id ?>" class="cancel-btn">👤 Preview Profile</a>
            </div>
        </form>
    </div>
</body>
</html>
