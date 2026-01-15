<?php
session_start();
require_once 'config.php';  // ← ByetHost DB connection
$error = '';

global $conn;  // ← Use config.php connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($conn->connect_error) {
        $error = 'Database connection failed.';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (!empty($email) && !empty($password)) {
            $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $hashedPassword);
                $stmt->fetch();
                
                if (password_verify($password, $hashedPassword)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['user_email'] = $email;
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Incorrect email or password.';
                }
            } else {
                $error = 'Incorrect email or password.';
            }
            $stmt->close();
        } else {
            $error = 'Please fill all fields.';
        }
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AskHole - Log In</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
</head>
<body>
  <div class="auth-container">
    <h1>Log In</h1>
    
    <?php if ($error): ?>
      <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Your password" required />

      <button type="submit" class="btn login-btn">Log In</button>
    </form>

    <p class="switch-auth">
      Don't have an account? <a href="signup.php">Sign Up</a>
    </p>
  </div>
</body>
</html>
