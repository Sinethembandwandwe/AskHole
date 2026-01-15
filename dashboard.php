<?php 
require_once 'config.php';
requireLogin();

$user_id = getUserId();

$stats = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM questions q WHERE q.sender_id = ? AND q.created_at > NOW() - INTERVAL 7 DAY) as questions_sent,
        (SELECT COUNT(*) FROM chats c WHERE (c.user1_id = ? OR c.user2_id = ?) AND c.status = 'active') as active_chats,
        (SELECT COUNT(*) FROM answers a JOIN questions q ON a.question_id = q.id WHERE q.sender_id = ?) as responses_received,
        (SELECT COUNT(*) FROM profile_views pv WHERE pv.viewed_id = ? AND pv.viewed_at > NOW() - INTERVAL 7 DAY) as profile_views_this_week
");
$stats->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stats->execute();
$stats_data = $stats->get_result()->fetch_assoc();
$stats->close();



// Search (top of HTML)
echo "<input type='text' id='userSearch' placeholder='Find people to ask...' style='width:300px;padding:8px;'>";
echo "<div id='searchResults'></div>";

// recently active gang
$active_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.profile_pic,
           COUNT(q.id) as questions_asked,
           COUNT(DISTINCT a.id) as responses_given
    FROM users u 
    LEFT JOIN questions q ON u.email = q.recipient_username
    LEFT JOIN answers a ON u.id = a.recipient_id
    WHERE u.id != ? 
    AND u.created_at > NOW() - INTERVAL 30 DAY
    GROUP BY u.id 
    ORDER BY MAX(COALESCE(q.created_at, u.created_at)) DESC 
    LIMIT 12
");
$active_stmt->bind_param("i", $user_id);
$active_stmt->execute();
$active_users = $active_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$user_email = $_SESSION['user_email'] ?? 'User';


// Fetch latest unread notifications
$notif_stmt = $conn->prepare("
    SELECT n.id, n.type, u.name AS from_name, n.created_at
    FROM notifications n
    LEFT JOIN users u ON n.from_user_id = u.id
    WHERE n.user_id = ? AND n.is_read = 0
    ORDER BY n.created_at DESC
    LIMIT 5
");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notifications = $notif_result->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

if (!empty($notifications)) {
    $ids = array_column($notifications, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders)";
    $mark = $conn->prepare($sql);
    $mark->bind_param($types, ...$ids);
    $mark->execute();
    $mark->close();
}
// 1. RECEIVED questions (where I'M recipient)
$received_questions = [];
$received_stmt = $conn->prepare("
    SELECT q.id, q.q1, q.q2, q.q3, q.q4, q.q5, q.created_at, 
           u.name as sender_name, u.id as sender_id,
           TIMESTAMPDIFF(DAY, q.created_at, NOW()) as days_old
    FROM questions q 
    JOIN users u ON q.sender_id = u.id
    LEFT JOIN answers a ON a.question_id = q.id
    WHERE q.recipient_username = ?
      AND a.id IS NULL
    ORDER BY q.created_at DESC
    LIMIT 10
");

$received_stmt->bind_param("s", $user_email);
$received_stmt->execute();
$result = $received_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $received_questions[] = $row;
}
$received_stmt->close();

// 2. SENT questions count
$sent_count = 0;
$sent_stmt = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE sender_id = ?");
$sent_stmt->bind_param("i", $user_id);
$sent_stmt->execute();
$sent_result = $sent_stmt->get_result();
$sent_count = $sent_result->fetch_assoc()['count'];
$sent_stmt->close();

// 3. PENDING ANSWERS (NEW - where receivers answered MY questions!)
$pending_answers = [];
$pending_stmt = $conn->prepare("
    SELECT a.id, a.a1, a.status, a.answered_at, a.recipient_id,
           u.name as recipient_name, u.email as recipient_email,
           q.id as question_id
    FROM answers a 
    JOIN questions q ON a.question_id = q.id
    JOIN users u ON a.recipient_id = u.id
    WHERE q.sender_id = ? AND a.status = 'pending'
    ORDER BY a.answered_at DESC 
    LIMIT 5
");
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
while ($row = $pending_result->fetch_assoc()) {
    $pending_answers[] = $row;
}
$pending_stmt->close();

$pending_count = count($pending_answers);

// ADD THIS after $pending_answers query (around line 40)
$my_chats = [];
$chats_stmt = $conn->prepare("
    SELECT c.id, c.created_at, 
           CASE 
               WHEN c.user1_id = ? THEN u2.name 
               WHEN c.user2_id = ? THEN u1.name 
           END as chat_partner,
           u1.id as user1_id, u2.id as user2_id
    FROM chats c 
    JOIN users u1 ON c.user1_id = u1.id
    JOIN users u2 ON c.user2_id = u2.id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$chats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$chats_stmt->execute();
$chats_result = $chats_stmt->get_result();
while ($row = $chats_result->fetch_assoc()) {
    $my_chats[] = $row;
}
$chats_stmt->close();
?>

<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Dashboard - AskHole 💜</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    <style>
        /* YOUR FULL CSS FROM BEFORE - SAME AS LAST VERSION */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0d2e 30%, #2d1b69 70%, #8b5cf6 100%);
        }
        .dashboard-container { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }
        .welcome-header { text-align: center; color: #f3e8ff; margin-bottom: 40px; text-shadow: 0 2px 15px rgba(139, 92, 246, 0.6); }
        .welcome-header h1 { font-size: 3em; background: linear-gradient(45deg, #c084fc, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: rgba(91, 44, 145, 0.9); padding: 30px; border-radius: 25px; border: 1px solid rgba(139, 92, 246, 0.4); text-align: center; backdrop-filter: blur(20px); box-shadow: 0 15px 50px rgba(139, 92, 246, 0.25); transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-10px); }
        .stat-number { font-size: 3em; font-weight: bold; background: linear-gradient(45deg, #8b5cf6, #c084fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 10px; }
        .questions-section, .answers-section { background: rgba(26, 13, 46, 0.92); border-radius: 25px; padding: 35px; margin-bottom: 30px; border: 1px solid rgba(139, 92, 246, 0.35); backdrop-filter: blur(15px); }
        .section-title { color: #f3e8ff; font-size: 2em; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        .question-card, .answer-card { background: rgba(139, 92, 246, 0.15); padding: 25px; margin-bottom: 25px; border-radius: 20px; border-left: 5px solid #8b5cf6; transition: all 0.4s ease; }
        .question-card:hover, .answer-card:hover { background: rgba(139, 92, 246, 0.25); transform: translateX(10px); box-shadow: 0 20px 40px rgba(139, 92, 246, 0.2); }
        .question-header, .answer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; color: #c084fc; font-weight: 600; }
        .questions-list, .answers-preview { display: grid; gap: 15px; margin-top: 20px; }
        .question-item, .answer-preview { background: rgba(243, 232, 255, 0.1); padding: 15px; border-radius: 12px; border-left: 3px solid rgba(139, 92, 246, 0.5); color: #e0d7ff; }
        .answer-btn, .review-btn { background: linear-gradient(45deg, #32d45a, #4ade80) !important; color: white !important; padding: 15px 30px !important; border: none !important; border-radius: 15px !important; font-weight: bold !important; text-decoration: none !important; display: inline-block !important; margin-top: 20px !important; box-shadow: 0 10px 30px rgba(50, 212, 90, 0.4) !important; transition: all 0.3s ease !important; }
        .review-btn { background: linear-gradient(45deg, #f59e0b, #fbbf24) !important; box-shadow: 0 10px 30px rgba(245, 158, 11, 0.4) !important; }
        .review-btn:hover { transform: translateY(-3px) !important; box-shadow: 0 15px 40px rgba(245, 158, 11, 0.6) !important; }
        .expired { color: #ff66c4 !important; font-style: italic !important; padding: 15px !important; background: rgba(255,102,196,0.1) !important; border-radius: 10px !important; text-align: center !important; }
        .cta-buttons { text-align: center; margin-top: 40px; }
        .start-btn { background: linear-gradient(45deg, #8b5cf6, #c084fc); color: white; padding: 20px 50px; border: none; border-radius: 50px; font-size: 1.2em; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4); transition: all 0.3s ease; margin: 0 15px; }
        .start-btn:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(139, 92, 246, 0.6); }
        .no-questions, .no-answers { text-align: center; color: rgba(243, 232, 255, 0.7); font-style: italic; padding: 60px 20px; }
    </style>

    <script>
        document.getElementById('userSearch').addEventListener('input', function(e) {
            let q = e.target.value;
            if (q.length < 2) return document.getElementById('searchResults').innerHTML = '';
            
            fetch(`search_users.php?q=${encodeURIComponent(q)}`)
                .then(r=>r.text())
                .then(html => document.getElementById('searchResults').innerHTML = html);
        });
    </script>

</head>
<body>
    <div class="dashboard-container">
        <div class="navbar">
            <div class="nav-left">
                <a href="index.php" class="logo">🏠 AskHole Dashboard</a>
            </div>
            <div class="nav-buttons">
                <a href="index.php" class="nav-btn home">🌐 Home</a>
                <a href="dashboard.php" class="nav-btn active">Dashboard</a>
                <a href="profile.php?id=<?= $user_id ?>" class="nav-btn">👤 My Profile</a>
                <a href="edit_profile.php" class="nav-btn primary">✏️ Edit Profile</a>
                <a href="logout.php" class="nav-btn logout">Logout</a>
            </div>
        </div>

        <!-- Keep your welcome message BELOW navbar -->
        <div class="welcome-header">
            <h1>Welcome Back, <?= htmlspecialchars($user_email) ?> 👋</h1>
            <p>Your AskHole Dashboard 💜</p>
        </div>
           

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?=$stats_data['questions_sent']?>/3</div>
                <div>Questions Sent<br>This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?=$stats_data['active_chats']?></div>
                <div>Active Chats</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?=$stats_data['responses_received']?></div>
                <div>Responses Received</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?=$stats_data['profile_views_this_week']?></div>
                <div>Profile Views<br>This Week</div>
            </div>
        </div>

       <!-- 🔥 RECENTLY ACTIVE USERS (Dashboard Discovery) -->
        <h3 style="color:#f3e8ff;margin:40px 0 20px 0;">👥 Recently Active</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
        <?php
        $active_stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.name, u.email, u.profile_pic,
                COUNT(q.id) as questions_asked
            FROM users u 
            LEFT JOIN questions q ON u.email = q.recipient_username
            WHERE u.id != ? AND u.created_at > NOW() - INTERVAL 30 DAY
            GROUP BY u.id 
            ORDER BY MAX(COALESCE(q.created_at, u.created_at)) DESC 
            LIMIT 12
        ");
        $active_stmt->bind_param("i", $user_id);
        $active_stmt->execute();
        $active_users = $active_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $active_stmt->close();

        foreach($active_users as $user): ?>
            <a href="profile.php?username=<?=urlencode($user['email'])?>" style="text-decoration:none;">
                <div style="background:rgba(139,92,246,0.2);padding:20px;border-radius:15px;border:1px solid rgba(139,92,246,0.4);text-align:center;">
                    <div style="width:60px;height:60px;border-radius:50%;background:#8b5cf6;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:1.5em;color:white;font-weight:bold;">
                        <?=substr($user['name'],0,1)?>
                    </div>
                    <div style="color:#f3e8ff;font-weight:600;"><?=htmlspecialchars($user['name'])?></div>
                    <div style="color:#c084fc;font-size:0.9em;"><?=$user['questions_asked']?> questions received</div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>

        <!-- 🔥 WHO VIEWED YOU (Engagement Booster) -->
        <h3 style="color:#f3e8ff;margin:40px 0 20px 0;">👀 Who Viewed Your Profile</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
        <?php
        $viewers_stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.name, u.email, pv.viewed_at
            FROM profile_views pv 
            JOIN users u ON pv.viewer_id = u.id
            WHERE pv.viewed_id = ? AND pv.viewed_at > NOW() - INTERVAL 7 DAY
            ORDER BY pv.viewed_at DESC LIMIT 8
        ");
        $viewers_stmt->bind_param("i", $user_id);
        $viewers_stmt->execute();
        $viewers = $viewers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $viewers_stmt->close();

        if (empty($viewers)): ?>
            <div style="grid-column:1 / -1; text-align:center; padding:40px; color:#a78bfa;">
                No views yet. Share your profile! 💜
            </div>
        <?php else: 
        foreach($viewers as $viewer): ?>
            <a href="profile.php?username=<?=urlencode($viewer['email'])?>" style="text-decoration:none;">
                <div style="background:rgba(16,185,129,0.2);padding:20px;border-radius:15px;border:1px solid #10b981;text-align:center;">
                    <div style="width:60px;height:60px;border-radius:50%;background:#10b981;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:1.5em;color:white;font-weight:bold;">
                        <?=substr($viewer['name'],0,1)?>
                    </div>
                    <div style="color:#f3e8ff;font-weight:600;"><?=htmlspecialchars($viewer['name'])?></div>
                    <div style="color:#34d399;font-size:0.9em;"><?=date('M j H:i', strtotime($viewer['viewed_at']))?></div>
                </div>
            </a>
        <?php endforeach; 
        endif; ?>
        </div>



        <?php if (!empty($notifications)): ?>
        <div class="notification-panel" style="margin-bottom:20px; padding:15px; border-radius:15px; background:rgba(26,13,46,0.9); border:1px solid rgba(139,92,246,0.4); color:#f3e8ff;">
            <h3 style="margin-bottom:10px;">🔔 Notifications</h3>
            <ul style="list-style:none; padding-left:0;">
                <?php foreach ($notifications as $n): ?>
                    <li style="margin-bottom:8px; font-size:0.95em;">
                        <?= htmlspecialchars(renderNotification($n)) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>


        <!-- NEW: PENDING ANSWERS SECTION (SENDER SEES THIS!) -->
        <?php if (!empty($pending_answers)): ?>
        <div class="answers-section">
            <h2 class="section-title">⏳ Answers Waiting Your Review (<?= $pending_count ?>)</h2>
            <?php foreach ($pending_answers as $answer): ?>
                <div class="answer-card">
                    <div class="answer-header">
                        <span><?= htmlspecialchars($answer['recipient_name']) ?> answered!</span>
                        <span><?= date('M j, Y', strtotime($answer['answered_at'])) ?></span>
                    </div>
                    <div class="answers-preview">
                        <div class="answer-preview">1. <?= htmlspecialchars(substr($answer['a1'], 0, 100)) ?>...</div>
                    </div>
                    <a href="review_answers.php?id=<?= $answer['id'] ?>" class="review-btn">
                        Review Full Answers → Like/Reject
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 3. 👇 INSERT "My Chats" HERE 👇 -->
    <?php if (!empty($my_chats)): ?>
    <div class="questions-section">
        <h2 class="section-title">💬 My Active Chats (<?= count($my_chats) ?>)</h2>
        <?php foreach ($my_chats as $chat): ?>
            <div class="question-card">
                <div class="question-header">
                    <span>Chat with: <?= htmlspecialchars($chat['chat_partner']) ?></span>
                    <span><?= date('M j, Y', strtotime($chat['created_at'])) ?></span>
                </div>
                <div style="margin-top: 20px;">
                    <a href="communicationsmsg.php?id=<?= $chat['id'] ?>" class="answer-btn">
                        Open Chat 💜
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

        <!-- Received Questions -->
        <div class="questions-section">
            <h2 class="section-title">📥 Questions You Received (<?= count($received_questions) ?>)</h2>
            <?php if (empty($received_questions)): ?>
                <div class="no-questions">No questions yet... Share your profile! 💜</div>
            <?php else: ?>
                <?php foreach ($received_questions as $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <span>From: <?= htmlspecialchars($question['sender_name']) ?></span>
                            <span><?= date('M j, Y', strtotime($question['created_at'])) ?></span>
                        </div>
                        <div class="questions-list">
                            <div class="question-item">1. <?= htmlspecialchars(substr($question['q1'], 0, 100)) ?>...</div>
                            <div class="question-item">2. <?= htmlspecialchars(substr($question['q2'], 0, 100)) ?>...</div>
                        </div>
                        <?php if ($question['days_old'] <= 7): ?>
                            <a href="answers.php?id=<?= $question['id'] ?>" class="answer-btn">
                                Answer Now 💜 (<?= 7 - $question['days_old'] ?> days left)
                            </a>
                        <?php else: ?>
                            <div class="expired">❌ Expired (7 days passed)</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="cta-buttons">
            <a href="ask_questions.php" class="start-btn">Send Questions 🚀</a>
        </div>
    </div>
</body>
</html>
