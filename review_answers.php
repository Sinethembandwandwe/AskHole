<?php
require_once 'config.php';
requireLogin();

$user_id = getUserId();
$answer_id = $_GET['id'] ?? 0;

if ($answer_id == 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch answer + ACTUAL QUESTIONS
$stmt = $conn->prepare("
    SELECT a.*, q.q1, q.q2, q.q3, q.q4, q.q5, q.recipient_username, 
           u.name as recipient_name, u.id as recipient_id,
           TIMESTAMPDIFF(DAY, a.answered_at, NOW()) as days_old
    FROM answers a 
    JOIN questions q ON a.question_id = q.id
    JOIN users u ON a.recipient_id = u.id
    WHERE a.id = ? AND q.sender_id = ?
");
$stmt->bind_param("ii", $answer_id, $user_id);
$stmt->execute();
$answer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$answer) {
    header("Location: dashboard.php?error=no_access");
    exit;
}

// Handle Like/Reject
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'like') {
        // First, check if a chat already exists between these two users
        $check = $conn->prepare("
            SELECT id 
            FROM chats 
            WHERE user1_id = GREATEST(?, ?) 
              AND user2_id = LEAST(?, ?)
            LIMIT 1
        ");
        $check->bind_param("iiii", $user_id, $answer['recipient_id'], $user_id, $answer['recipient_id']);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            // Reuse existing chat to avoid UNIQUE constraint error
            $chat_id = $existing['id'];
        } else {
            // Create new chat for this pair
            $chat_stmt = $conn->prepare("
                INSERT INTO chats (user1_id, user2_id, answer_id) 
                VALUES (GREATEST(?,?), LEAST(?,?), ?)
            ");
            $chat_stmt->bind_param("iiiii", $user_id, $answer['recipient_id'], $user_id, $answer['recipient_id'], $answer_id);
            $chat_stmt->execute();
            $chat_id = $conn->insert_id;
            $chat_stmt->close();
        }

        // Mark answer as accepted
        $update_stmt = $conn->prepare("UPDATE answers SET status = 'accepted', reviewed_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $answer_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $success = "✅ Chat opened! <a href='communicationsmsg.php?id=$chat_id' style='color:#10b981;font-weight:bold;'>Start Messaging →</a>";
    
    } elseif ($action === 'reject') {
        $update_stmt = $conn->prepare("UPDATE answers SET status = 'rejected', reviewed_at = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $answer_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $block_stmt = $conn->prepare("
            INSERT INTO blocks (blocker_id, blocked_id, blocked_at, unblock_date) 
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY))
            ON DUPLICATE KEY UPDATE unblock_date = DATE_ADD(NOW(), INTERVAL 15 DAY)
        ");
        $block_stmt->bind_param("ii", $user_id, $answer['recipient_id']);
        $block_stmt->execute();
        $block_stmt->close();
        
        $success = "❌ " . htmlspecialchars($answer['recipient_name']) . " disconnected (15-day block)";
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Answers - AskHole 💜</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    <style>
        /* SAME STYLES AS BEFORE - just fixed question display */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0d2e 30%, #2d1b69 70%, #8b5cf6 100%);
        }
        .review-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 40px;
            background: rgba(91, 44, 145, 0.95);
            border-radius: 25px;
            border: 1px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(20px);
        }
        .recipient-header {
            background: rgba(139, 92, 246, 0.25);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            border-left: 6px solid #8b5cf6;
            text-align: center;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.2);
        }
        .recipient-name {
            color: #c084fc;
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(192, 132, 252, 0.5);
        }
        .answered-time {
            color: #e0d7ff;
            font-size: 1.2em;
            margin-bottom: 15px;
        }
        .answers-section {
            background: rgba(26, 13, 46, 0.92);
            padding: 35px;
            border-radius: 25px;
            margin-bottom: 40px;
            border: 1px solid rgba(139, 92, 246, 0.35);
            backdrop-filter: blur(15px);
        }
        .answer-item {
            background: rgba(243, 232, 255, 0.15);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 20px;
            border-left: 5px solid #8b5cf6;
            transition: all 0.4s ease;
        }
        .answer-item:hover {
            background: rgba(243, 232, 255, 0.25);
            transform: translateX(8px);
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.25);
        }
        .question-text {
            color: #f3e8ff;
            font-size: 1.3em;
            margin-bottom: 20px;
            font-weight: 600;
            line-height: 1.5;
            padding: 20px;
            background: rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            border-left: 4px solid #8b5cf6;
        }
        .answer-text {
            color: #e0d7ff;
            font-size: 1.1em;
            line-height: 1.7;
            background: rgba(26, 13, 46, 0.7);
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid #10b981;
            min-height: 120px;
            margin-top: 15px;
        }
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }
        .like-btn {
            flex: 1;
            padding: 25px 40px;
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            border: none;
            font-size: 1.4em;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.4);
            transition: all 0.4s ease;
        }
        .reject-btn {
            flex: 1;
            padding: 25px 40px;
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: white;
            border: none;
            font-size: 1.4em;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            box-shadow: 0 12px 35px rgba(239, 68, 68, 0.4);
            transition: all 0.4s ease;
        }
        .like-btn:hover { transform: translateY(-5px); box-shadow: 0 20px 45px rgba(16, 185, 129, 0.6); }
        .reject-btn:hover { transform: translateY(-5px); box-shadow: 0 20px 45px rgba(239, 68, 68, 0.6); }
        .msg {
            padding: 25px;
            border-radius: 18px;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
            font-size: 1.2em;
        }
        .success { background: rgba(16, 185, 129, 0.25); color: #d1fae5; border-left: 6px solid #10b981; }
        .back-btn {
            display: inline-block;
            color: #c084fc;
            text-decoration: none;
            font-weight: 700;
            margin-top: 25px;
            padding: 15px 35px;
            background: rgba(139, 92, 246, 0.25);
            border-radius: 15px;
            border: 2px solid rgba(139, 92, 246, 0.5);
            transition: all 0.3s ease;
        }
        .back-btn:hover { background: rgba(139, 92, 246, 0.4); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="review-container">
        <div class="recipient-header">
            <div class="recipient-name">💜 <?= htmlspecialchars($answer['recipient_name']) ?></div>
            <div class="answered-time">answered <?= date('M j, Y g:i A', strtotime($answer['answered_at'])) ?></div>
            <div style="color: #10b981; font-size: 1.3em; font-weight: bold;">
                (<?= 7 - $answer['days_old'] ?> days left to review)
            </div>
        </div>

        <?php if ($success): ?>
            <div class="msg success"><?= $success ?></div>
            <div style="text-align: center;">
                <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
            </div>
        <?php else: ?>
        <div class="answers-section">
            <h2 style="color: #f3e8ff; text-align: center; margin-bottom: 40px; font-size: 2.2em;">
                Their Answers to YOUR Questions 💜
            </h2>
            
            <div class="answer-item">
                <div class="question-text">
                    <strong> You asked:</strong> <?= htmlspecialchars($answer['q1']) ?>
                </div>
                <div class="answer-text">
                    <?= nl2br(htmlspecialchars($answer['a1'])) ?>
                </div>
            </div>
            
            <div class="answer-item">
                <div class="question-text">
                    <strong> You asked:</strong> <?= htmlspecialchars($answer['q2']) ?>
                </div>
                <div class="answer-text">
                    <?= nl2br(htmlspecialchars($answer['a2'])) ?>
                </div>
            </div>
            
            <div class="answer-item">
                <div class="question-text">
                    <strong> You asked:</strong> <?= htmlspecialchars($answer['q3']) ?>
                </div>
                <div class="answer-text">
                    <?= nl2br(htmlspecialchars($answer['a3'])) ?>
                </div>
            </div>
            
            <div class="answer-item">
                <div class="question-text">
                    <strong> You asked:</strong> <?= htmlspecialchars($answer['q4']) ?>
                </div>
                <div class="answer-text">
                    <?= nl2br(htmlspecialchars($answer['a4'])) ?>
                </div>
            </div>
            
            <div class="answer-item">
                <div class="question-text">
                    <strong> You asked:</strong> <?= htmlspecialchars($answer['q5']) ?>
                </div>
                <div class="answer-text">
                    <?= nl2br(htmlspecialchars($answer['a5'])) ?>
                </div>
            </div>

            <form method="POST">
                <div class="action-buttons">
                    <button type="submit" name="action" value="like" class="like-btn">
                        👍 LIKE - Open Chat Now
                    </button>
                    <button type="submit" name="action" value="reject" class="reject-btn">
                        👎 REJECT - Disconnect (15 days)
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
