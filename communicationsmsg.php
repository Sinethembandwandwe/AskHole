<?php
date_default_timezone_set('Africa/Johannesburg'); 

require_once 'config.php';
requireLogin();

$user_id = getUserId();
$chat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($chat_id === 0) {
    header("Location: dashboard.php");
    exit;
}

// Fetch chat + verify access + get other user info
$stmt = $conn->prepare("
    SELECT c.*, 
           u1.name AS user1_name, 
           u2.name AS user2_name,
           u1.id   AS user1_id,
           u2.id   AS user2_id
    FROM chats c 
    JOIN users u1 ON c.user1_id = u1.id
    JOIN users u2 ON c.user2_id = u2.id
    WHERE c.id = ? AND (c.user1_id = ? OR c.user2_id = ?)
");
$stmt->bind_param("iii", $chat_id, $user_id, $user_id);
$stmt->execute();
$chat = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$chat) {
    header("Location: dashboard.php?error=no_chat");
    exit;
}

// Who is the other user?
$other_user_id   = ($chat['user1_id'] == $user_id) ? $chat['user2_id'] : $chat['user1_id'];
$other_user_name = ($chat['user1_id'] == $user_id) ? $chat['user2_name'] : $chat['user1_name'];

// Handle chat actions: disconnect / block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_action'])) {
    $action = $_POST['chat_action'];

    if ($action === 'disconnect') {
        // Mark chat as disconnected
        $u = $conn->prepare("UPDATE chats SET status = 'disconnected' WHERE id = ?");
        $u->bind_param("i", $chat_id);
        $u->execute();
        $u->close();

        // Notification: chat disconnected (FIXED: added related_id)
        $notif = $conn->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, related_id) 
            VALUES (?, ?, 'chat_disconnected', ?)
        ");
        $notif->bind_param("iii", $other_user_id, $user_id, $chat_id);
        $notif->execute();
        $notif->close();

        header("Location: dashboard.php?msg=chat_disconnected");
        exit;
    }
       if ($chat && $chat['status'] === 'active') {
            $mark_read = $conn->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW(), delivery_status = 2 
                WHERE chat_id = ? AND sender_id != ? AND is_read = 0
            ");
            $mark_read->bind_param("ii", $chat_id, $user_id);
            $mark_read->execute();
            $mark_read->close();
        }




    if ($action === 'block') {
        // Mark chat as disconnected + blocked_by
        $u = $conn->prepare("UPDATE chats SET status = 'disconnected', blocked_by = ? WHERE id = ?");
        $u->bind_param("ii", $user_id, $chat_id);
        $u->execute();
        $u->close();

        // Notification: ignored / blocked (FIXED: added related_id)
        $notif = $conn->prepare("
            INSERT INTO notifications (user_id, from_user_id, type, related_id) 
            VALUES (?, ?, 'message_ignored', ?)
        ");
        $notif->bind_param("iii", $other_user_id, $user_id, $chat_id);
        $notif->execute();
        $notif->close();

        header("Location: dashboard.php?msg=blocked");
        exit;
    }
}

// Re-fetch chat to get updated status after any action
$stmt = $conn->prepare("
    SELECT c.*, 
           u1.name AS user1_name, 
           u2.name AS user2_name,
           u1.id   AS user1_id,
           u2.id   AS user2_id
    FROM chats c 
    JOIN users u1 ON c.user1_id = u1.id
    JOIN users u2 ON c.user2_id = u2.id
    WHERE c.id = ? AND (c.user1_id = ? OR c.user2_id = ?)
");
$stmt->bind_param("iii", $chat_id, $user_id, $user_id);
$stmt->execute();
$chat = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch messages
$messages_stmt = $conn->prepare("
    SELECT m.*, u.name AS sender_name, m.is_read
    FROM messages m 
    JOIN users u ON m.sender_id = u.id
    WHERE m.chat_id = ? 
    ORDER BY m.created_at ASC
");
$messages_stmt->bind_param("i", $chat_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = [];
while ($row = $messages_result->fetch_assoc()) {
    $messages[] = $row;
}
$messages_stmt->close();


// Handle new message (only if chat is active)
if (
    $chat['status'] === 'active' &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    !isset($_POST['chat_action']) &&
    !empty(trim($_POST['message'] ?? ''))
) {
    $message_text = trim($_POST['message']);
    $msg_stmt = $conn->prepare("INSERT INTO messages (chat_id, sender_id, message, delivery_status) VALUES (?, ?, ?, 1)");
    $msg_stmt->bind_param("iis", $chat_id, $user_id, $message_text);
    $msg_stmt->execute();
    $msg_stmt->close();
    header("Location: communicationsmsg.php?id=$chat_id"); // Refresh to show new message
    exit;
}

?>

<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - AskHole 💜</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0d2e 30%, #2d1b69 70%, #8b5cf6 100%);
        }
        .chat-container {
            max-width: 95vw !important;
            width: 95vw !important;
            height: 90vh;
            margin: 10px auto !important;
            background: rgba(91, 44, 145, 0.95);
            border-radius: 25px;
            border: 1px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            background: linear-gradient(45deg, #8b5cf6, #c084fc);
            padding: 25px;
            border-radius: 25px 25px 0 0;
            text-align: center;
            color: white;
            box-shadow: 0 5px 20px rgba(139, 92, 246, 0.4);
        }
        .chat-partner {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .chat-messages {
            flex: 1;
            padding: 20px !important;
            overflow-y: auto;
            overflow-x: hidden !important;
            background: rgba(26, 13, 46, 0.92);
            border-radius: 0 0 25px 25px;
            margin: 0 !important;
            border: 1px solid rgba(139, 92, 246, 0.3);
            word-wrap: break-word !important;
            word-break: break-word !important;
            hyphens: auto;
        }
        .message {
            margin-bottom: 25px;
            display: flex;
            animation: slideIn 0.3s ease-out;
            width: 100%;
        }
        .message.sent {
            justify-content: flex-end;
        }
        .message.received {
            justify-content: flex-start;
        }
        .message-bubble {
            max-width: 85% !important;
            min-width: 250px;
            padding: 18px 22px !important;
            border-radius: 25px;
            position: relative;
            backdrop-filter: blur(15px);
            word-wrap: break-word !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
            hyphens: auto;
            line-height: 1.4 !important;
            font-size: 1rem !important;
        }
        .message.sent .message-bubble {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            border-bottom-right-radius: 5px;
            margin-left: 10px !important;
        }
        .message.received .message-bubble {
            background: rgba(139, 92, 246, 0.3);
            color: #f3e8ff;
            border-bottom-left-radius: 5px;
            margin-right: 10px !important;
            border: 1px solid rgba(139, 92, 246, 0.5);
        }
        .message-sender {
            font-weight: 600;
            font-size: 0.9em;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        .message-text {
            font-size: 1.05em !important;
            line-height: 1.5 !important;
            word-wrap: break-word !important;
            word-break: break-word !important;
            overflow-wrap: anywhere;
            hyphens: auto;
        }
        .message-time {
            font-size: 0.75em;
            opacity: 0.7;
            margin-top: 8px;
        }
        .chat-input-container {
            padding: 25px 30px 30px;
            background: rgba(26, 13, 46, 0.92);
            border-radius: 0 0 25px 25px;
            border-top: 1px solid rgba(139, 92, 246, 0.3);
        }
        .chat-input-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        #message-input {
            flex: 1;
            padding: 18px !important;
            border-radius: 25px;
            border: 2px solid rgba(139, 92, 246, 0.4);
            background: rgba(243, 232, 255, 0.1);
            color: #f3e8ff;
            font-size: 16px !important;
            font-family: inherit;
            resize: vertical !important;
            min-height: 60px;
            max-height: 200px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            line-height: 1.4;
        }
        #message-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.4);
            background: rgba(243, 232, 255, 0.2);
        }
        .send-btn {
            padding: 20px 30px;
            background: linear-gradient(45deg, #8b5cf6, #c084fc);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .send-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.6);
        }
        .back-btn {
            display: inline-block;
            color: #c084fc;
            text-decoration: none;
            padding: 12px 25px;
            background: rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            border: 2px solid rgba(139, 92, 246, 0.4);
            margin-bottom: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .no-messages {
            text-align: center;
            color: rgba(243, 232, 255, 0.6);
            font-style: italic;
            padding: 60px 20px;
            font-size: 1.2em;
        }

        /* 🔥 MOBILE + SAMSUNG FIXES */
        @media (max-width: 768px) {
            .chat-container {
                max-width: 100vw !important;
                width: 100vw !important;
                height: 100vh !important;
                margin: 0 !important;
                border-radius: 0 !important;
                padding: 5px !important;
                box-sizing: border-box;
            }
            
            .chat-messages {
                padding: 15px !important;
                margin: 0 !important;
            }
            
            .message-bubble {
                max-width: 92vw !important;
                min-width: 85vw !important;
                font-size: 16px !important;
                padding: 16px 20px !important;
            }
            
            .chat-input-form {
                flex-direction: column !important;
                gap: 12px !important;
            }
            
            .send-btn {
                width: 100% !important;
                margin-top: 0 !important;
            }
            
            #message-input {
                width: 100% !important;
                font-size: 16px !important; /* Prevents zoom */
            }
            
            .chat-header {
                padding: 20px 15px !important;
            }
            
            .chat-partner {
                font-size: 1.4em !important;
            }
        }
</style>

</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <a href="dashboard.php" class="back-btn">← Dashboard</a>
            <div class="chat-partner">
                💜 Chat with <?= htmlspecialchars($other_user_name) ?>
            </div>
            <div style="margin-top: 10px; display:flex; gap:10px; justify-content:center;">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="chat_action" value="disconnect">
                    <button type="submit" class="send-btn" style="background:linear-gradient(45deg,#f97316,#fb923c);">
                        🔌 Disconnect
                    </button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="chat_action" value="block">
                    <button type="submit" class="send-btn" style="background:linear-gradient(45deg,#ef4444,#f97373);">
                        🚫 Block
                    </button>
                </form>
            </div>
        </div>

       <div class="chat-messages" id="messages">
            <?php if (empty($messages)): ?>
                <div class="no-messages">No messages yet... Say hello! 💜</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                        <div class="message-bubble">
                            <div class="message-sender"><?= htmlspecialchars($msg['sender_name']) ?></div>
                            <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                            <div class="message-time">
                                <?= date('H:i', strtotime($msg['created_at'])) ?> <!-- SA TIME NOW! -->
                                <!-- ✅ "viewed" ONLY on YOUR messages when OTHER person opens chat -->
                                <?php if ($msg['sender_id'] == $user_id && $msg['is_read'] == 1): ?>
                                    <span style="color:#10b981;font-size:0.8em;margin-left:8px;">viewed</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>




        <div class="chat-input-container">
            <?php if ($chat['status'] === 'disconnected'): ?>
                <div class="no-messages">
                    Chat disconnected. You can no longer send messages. 💜
                </div>
            <?php else: ?>
                <form method="POST" class="chat-input-form">
                    <textarea id="message-input" name="message" placeholder="Type your message... 💭" required></textarea>
                    <button type="submit" class="send-btn">Send 💜</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom
        window.onload = function() {
            const messages = document.getElementById('messages');
            messages.scrollTop = messages.scrollHeight;
        };

        // Auto-resize textarea
        const input = document.getElementById('message-input');
        if (input) {
            input.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        }
    </script>
</body>
</html>
