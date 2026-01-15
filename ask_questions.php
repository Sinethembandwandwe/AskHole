<?php
require_once 'config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$success = '';
$error   = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id = getUserId();
    $recipient_email = trim($_POST['recipient_email'] ?? '');

    // Collect questions
    $questions = [
        trim($_POST['q1'] ?? ''),
        trim($_POST['q2'] ?? ''),
        trim($_POST['q3'] ?? ''),
        trim($_POST['q4'] ?? ''),
        trim($_POST['q5'] ?? '')
    ];

    // Validate recipient
    if ($recipient_email === '') {
        $error = "Recipient email is required.";
    }

    // Ensure all questions filled
    if (empty($error)) {
        foreach ($questions as $q) {
            if ($q === '') {
                $error = "All 5 questions are required.";
                break;
            }
        }
    }

    // Enforce: max 3 distinct recipients per 7 days
    if (empty($error)) {
        $limit_stmt = $conn->prepare("
            SELECT COUNT(DISTINCT recipient_username) AS cnt
            FROM questions
            WHERE sender_id = ? 
              AND created_at > NOW() - INTERVAL 7 DAY
        ");
        $limit_stmt->bind_param("i", $sender_id);
        $limit_stmt->execute();
        $cnt = $limit_stmt->get_result()->fetch_assoc()['cnt'];
        $limit_stmt->close();

        if ($cnt >= 3) {
            $error = "You already sent questions to 3 people this week 💜";
        }
    }

    if (empty($error)) {
        // Check recipient exists by email in users table
        $check_stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $recipient_email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $recipient_row = $check_result->fetch_assoc();
        $check_stmt->close();

        if (!$recipient_row) {
            $error = "User '$recipient_email' not found.";
        } else {
            // Insert into questions: sender_id, recipient_username (email), q1..q5
            $stmt = $conn->prepare("
                INSERT INTO questions (sender_id, recipient_username, q1, q2, q3, q4, q5)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issssss",
                $sender_id,
                $recipient_email,      // stored as recipient_username
                $questions[0],
                $questions[1],
                $questions[2],
                $questions[3],
                $questions[4]
            );

            if ($stmt->execute()) {
                $success = "Your 5 questions were sent to " . htmlspecialchars($recipient_email) . " 🎉";
            } else {
                $error = "Something went wrong. Try again.";
            }
            $stmt->close();
        }
    }
}

// Pre-fill from URL ?recipient=... (email)
$pre_filled_recipient = $_GET['recipient'] ?? '';
?>

<!DOCTYPE html>
<html lang="en" class="dark-purple-theme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AskHole - Send Your Questions</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
    <style>
        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
        }
        .container {
            background: rgba(91, 44, 145, 0.9);
            width: 90%;
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.3);
            backdrop-filter: blur(10px);
        }
        .container h2 {
            color: #f3e8ff;
            text-align: center;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(139, 92, 246, 0.5);
        }
        input, textarea {
            width: 100%;
            padding: 15px;
            margin: 10px 0 20px 0;
            border-radius: 12px;
            border: 2px solid rgba(139, 92, 246, 0.3);
            background: rgba(26, 13, 46, 0.8);
            color: #f3e8ff;
            font-size: 16px;
            box-sizing: border-box;
        }
        input::placeholder, textarea::placeholder {
            color: rgba(243, 232, 255, 0.7);
        }
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(45deg, #8b5cf6, #c084fc);
            border: none;
            color: white;
            font-weight: bold;
            border-radius: 12px;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            transition: all 0.3s ease;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.6);
        }
        .msg {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .success {
            background: rgba(50, 212, 90, 0.2);
            border-left: 5px solid #32d45a;
            color: #d4f8e0;
        }
        .error {
            background: rgba(255, 102, 196, 0.2);
            border-left: 5px solid #ff66c4;
            color: #ffb3d9;
        }
        label {
            color: #f3e8ff;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Send Your 5 Questions 💜</h2>

        <?php if (!empty($error)): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="msg success"><?= $success ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST">
            <label>Recipient Email:</label>
            <input
                type="email"
                name="recipient_email"
                value="<?= htmlspecialchars($pre_filled_recipient ?: ($_POST['recipient_email'] ?? '')) ?>"
                placeholder="Enter their email address"
                required
            >

            <label>Question 1:</label>
            <textarea name="q1" placeholder="Your first question..." required><?= htmlspecialchars($_POST['q1'] ?? '') ?></textarea>

            <label>Question 2:</label>
            <textarea name="q2" placeholder="Your second question..." required><?= htmlspecialchars($_POST['q2'] ?? '') ?></textarea>

            <label>Question 3:</label>
            <textarea name="q3" placeholder="Your third question..." required><?= htmlspecialchars($_POST['q3'] ?? '') ?></textarea>

            <label>Question 4:</label>
            <textarea name="q4" placeholder="Your fourth question..." required><?= htmlspecialchars($_POST['q4'] ?? '') ?></textarea>

            <label>Question 5:</label>
            <textarea name="q5" placeholder="Your fifth question..." required><?= htmlspecialchars($_POST['q5'] ?? '') ?></textarea>

            <button type="submit">Send Questions</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
