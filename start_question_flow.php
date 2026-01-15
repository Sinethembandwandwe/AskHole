<?php
session_start();

// PREVENT any accidental output
ob_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ask_questions.php");
    exit;
} else {
    header("Location: signup.php");
    exit;
}

ob_end_flush();
?>
