<?php
require_once 'config.php';
requireLogin();

$user_id = getUserId();
$question_id = $_GET['id'] ?? 0;

if ($question_id == 0) {
    header("Location: dashboard.php");
    exit;
}

// RULE 2: Check 7-day expiration + fetch question
$stmt = $conn->prepare("
    SELECT q.*, u.name as sender_name, u.email as sender_email,
           TIMESTAMPDIFF(DAY, q.created_at, NOW()) as days_old,
           TIMESTAMPDIFF(DAY, q.created_at + INTERVAL 5 DAY, NOW()) as sender_expired
    FROM questions q 
    JOIN users u ON q.sender_id = u.id 
    WHERE q.id = ? AND q.recipient_username = (SELECT email FROM users WHERE id = ?)
");
$stmt->bind_param("ii", $question_id, $user_id);
$stmt->execute();
$question = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$question) {
    header("Location: dashboard.php?error=no_access");
    exit;
}

// RULE 4: 7-day expiration check
if ($question['days_old'] > 7) {
    header("Location: dashboard.php?expired=1");
    exit;
}

// RULE 1: Check sender sent 3+ people in 5 days
$sent_check = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM questions 
    WHERE sender_id = ? AND created_at > NOW() - INTERVAL 5 DAY
");
$sent_check->bind_param("i", $question['sender_id']);
$sent_check->execute();
$sent_count = $sent_check->get_result()->fetch_assoc()['count'];
$sent_check->close();

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a1 = trim($_POST['a1']);
    $a2 = trim($_POST['a2']);
    $a3 = trim($_POST['a3']);
    $a4 = trim($_POST['a4']);
    $a5 = trim($_POST['a5']);
    
    // RULE 3: All answers required
    if (empty($a1) || empty($a2) || empty($a3) || empty($a4) || empty($a5)) {
        $error = "All 5 answers required 💜";
    } elseif ($sent_count < 3) {
        $error = "Sender must send to 3+ people first (rule violation)";
    } else {
        // RULE 3: Save answers as PENDING
        $answers_stmt = $conn->prepare("
            INSERT INTO answers (question_id, recipient_id, a1, a2, a3, a4, a5, answered_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        $answers_stmt->bind_param("iisssss", $question_id, $user_id, $a1, $a2, $a3, $a4, $a5);
        
        if ($answers_stmt->execute()) {
            // notify sender that someone answered (FIXED: added related_id)
            $notif = $conn->prepare("
                INSERT INTO notifications (user_id, from_user_id, type, related_id)
                VALUES (?, ?, 'message_answered', ?)
            ");
            $notif->bind_param("iii", $question['sender_id'], $user_id, $question_id);
            $notif->execute();
            $notif->close();

            $success = "✅ Answers sent to " . htmlspecialchars($question['sender_name']) . "<br>⏳ Waiting for review (7 days)";
        } else {
            $error = "Error saving answers.";
        }
        $answers_stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Answer Questions - AskHole</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
   

    <style>
        * {margin:0;padding:0;box-sizing:border-box;}
        body {font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh;background:linear-gradient(135deg,#0a0a0a 0%,#1a0d2e 30%,#2d1b69 70%,#8b5cf6 100%);}
        .container{max-width:850px;margin:40px auto;padding:40px;background:rgba(91,44,145,.95);border-radius:25px;border:1px solid rgba(139,92,246,.4);box-shadow:0 20px 60px rgba(139,92,246,.3);backdrop-filter:blur(20px);}
        .sender-card{background:rgba(139,92,246,.25);padding:30px;border-radius:20px;margin-bottom:30px;border-left:6px solid #8b5cf6;text-align:center;box-shadow:0 10px 30px rgba(139,92,246,.2);}
        .sender-name{color:#c084fc;font-size:2.2em;font-weight:700;margin-bottom:10px;text-shadow:0 2px 10px rgba(192,132,252,.5);}
        .rule-status{display:flex;justify-content:space-around;margin:20px 0;font-size:1.1em;color:#e0d7ff;}
        .status-item{padding:15px 25px;background:rgba(26,13,46,.8);border-radius:12px;border:1px solid rgba(139,92,246,.3);}
        .status-good{color:#32d45a;border-color:#32d45a;}
        .status-warning{color:#ffaa00;border-color:#ffaa00;}
        .question-card{background:rgba(243,232,255,.12);padding:25px;margin-bottom:25px;border-radius:20px;border-left:5px solid #8b5cf6;border:1px solid rgba(139,92,246,.2);transition:all .4s ease;}
        .question-card:hover{background:rgba(243,232,255,.22);transform:translateX(8px);box-shadow:0 15px 35px rgba(139,92,246,.25);}
        .question-text{color:#f3e8ff;font-size:1.2em;margin-bottom:20px;font-weight:600;line-height:1.5;}
        .answer-box{width:100%;padding:20px;border-radius:18px;border:2px solid rgba(139,92,246,.4);background:rgba(26,13,46,.85);color:#f3e8ff;font-size:16px;resize:vertical;min-height:140px;font-family:inherit;box-sizing:border-box;transition:all .3s ease;}
        .answer-box:focus{outline:none;border-color:#8b5cf6;box-shadow:0 0 25px rgba(139,92,246,.4);background:rgba(26,13,46,.95);}
        .answer-box::placeholder{color:rgba(243,232,255,.6);}
        .submit-btn{width:100%;padding:25px;background:linear-gradient(45deg,#8b5cf6,#c084fc);border:none;color:#fff;font-size:1.4em;font-weight:700;border-radius:18px;cursor:pointer;box-shadow:0 12px 35px rgba(139,92,246,.45);transition:all .4s ease;text-transform:uppercase;letter-spacing:1px;margin:30px 0;}
        .submit-btn:hover{transform:translateY(-4px);box-shadow:0 18px 45px rgba(139,92,246,.65);}
        .msg{padding:25px;border-radius:18px;margin-bottom:30px;font-weight:600;text-align:center;font-size:1.1em;}
        .success{background:rgba(50,212,90,.25);color:#d4f8e0;border-left:6px solid #32d45a;}
        .error{background:rgba(255,102,196,.25);color:#ffb3d9;border-left:6px solid #ff66c4;}
        .back-btn{display:inline-block;color:#c084fc;text-decoration:none;font-weight:700;margin-top:25px;padding:15px 35px;background:rgba(139,92,246,.25);border-radius:15px;border:2px solid rgba(139,92,246,.5);transition:all .3s ease;}
        .back-btn:hover{background:rgba(139,92,246,.4);transform:translateY(-2px);}
        .rules-note{background:rgba(139,92,246,.15);padding:25px;border-radius:15px;margin-top:30px;border-left:4px solid #8b5cf6;color:#e0d7ff;font-size:.95em;}
        h2{color:#f3e8ff;text-align:center;margin-bottom:35px;font-size:2.2em;}
    </style>
</head>
<body>
    <div class="container">
        <div class="sender-card">
            <div class="sender-name">💜 <?= htmlspecialchars($question['sender_name']) ?></div>
            <div style="color:#e0d7ff;font-size:1.2em;margin-bottom:15px;">sent you questions <?= $question['days_old'] ?> days ago</div>
            <div class="rule-status">
                <div class="status-item status-<?= $sent_count >= 3 ? 'good' : 'warning' ?>">
                    📤 Sender sent: <?= $sent_count ?>/3 people
                </div>
                <div class="status-item status-good">
                    ⏰ <?= 7 - $question['days_old'] ?> days left
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="msg success"><?= $success ?></div>
            <div style="text-align:center;">
                <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
        <?php elseif ($error): ?>
            <div class="msg error"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div>
            <h2>Answer Honestly 💜</h2>
            <form method="POST">
                <div class="question-card">
                    <div class="question-text">1. <?= htmlspecialchars($question['q1']) ?></div>
                    <textarea class="answer-box" name="a1" placeholder="Your brutally honest answer..." required></textarea>
                </div>
                <div class="question-card">
                    <div class="question-text">2. <?= htmlspecialchars($question['q2']) ?></div>
                    <textarea class="answer-box" name="a2" placeholder="Be real..." required></textarea>
                </div>
                <div class="question-card">
                    <div class="question-text">3. <?= htmlspecialchars($question['q3']) ?></div>
                    <textarea class="answer-box" name="a3" placeholder="No pretending..." required></textarea>
                </div>
                <div class="question-card">
                    <div class="question-text">4. <?= htmlspecialchars($question['q4']) ?></div>
                    <textarea class="answer-box" name="a4" placeholder="Truth only..." required></textarea>
                </div>
                <div class="question-card">
                    <div class="question-text">5. <?= htmlspecialchars($question['q5']) ?></div>
                    <textarea class="answer-box" name="a5" placeholder="Make it count..." required></textarea>
                </div>
                <button type="submit" class="submit-btn">Send Answers → Pending Review</button>
            </form>
            <div class="rules-note">
                <strong>⚖️ Rules:</strong> Sender reviews in 7 days → LIKE=Chat | DISLIKE=Disconnect (15-day unblock)
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
