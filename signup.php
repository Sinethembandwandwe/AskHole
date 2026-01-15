<?php
session_start();
require_once 'config.php';  // ← ADDED: Uses your ByetHost DB connection
$error = '';

global $conn;  // ← ADDED: Use config.php connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($conn->connect_error) {
        $error = 'Database connection failed.';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $pronouns = trim($_POST['pronouns']);
        $gender = trim($_POST['gender']);
        $location = trim($_POST['location']);
        $password = $_POST['password'];
        
        if (empty($name) || empty($email) || empty($gender) || empty($password)) {
            $error = 'Please fill required fields.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Email already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (name, email, pronouns, gender, location, password) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssss', $name, $email, $pronouns, $gender, $location, $passwordHash);
                
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['user_email'] = $email;
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Error creating account. Try again.';
                }
            }
            $stmt->close();
        }
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>AskHole - Sign Up</title>
  <link rel="stylesheet" href="style.css" />
  <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">
</head>
<body>
  <div class="auth-container">
    <h1>Sign Up</h1>
    
    <?php if ($error): ?>
      <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="signup.php">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" placeholder="Your Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required />

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="your@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required />

      <label for="pronouns">Pronouns</label>
      <input type="text" id="pronouns" name="pronouns" placeholder="e.g. she/her, they/them" value="<?php echo isset($_POST['pronouns']) ? htmlspecialchars($_POST['pronouns']) : ''; ?>" />

      <label for="gender">Gender</label>
      <select id="gender" name="gender" required>
        <option value="">Select your gender</option>
        <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
        <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
        <option value="nonbinary" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'nonbinary') ? 'selected' : ''; ?>>Non-binary</option>
        <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
        <option value="preferNot" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'preferNot') ? 'selected' : ''; ?>>Prefer not to say</option>
      </select>

      <label for="location">Location</label>
      <input type="text" id="location" name="location" placeholder="City, Country" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>" />

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Create a password" required />

      <button type="submit" class="btn signup-btn">Create Account</button>
    </form>

    <p class="switch-auth">
      Already have an account? <a href="login.php">Log In</a>
    </p>
  </div>
</body>
</html>
