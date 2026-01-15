<?php
require_once 'config.php';
$user_id = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}
$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en" <?php echo $isLoggedIn ? 'class="dark-purple-theme"' : ''; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>console.log('Theme class on HTML:', document.documentElement.className);</script>
    <title>AskHole – Love is a joke. Let's take it seriously.</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="mobile.css?v=<?php echo time(); ?>">

</head>

<body>

    <!-- === NAVBAR === -->
    <nav class="navbar">
    <div class="logo">AskHole</div>

    <!-- MENU -->
    <ul class="menu">
        <li><a href="#home">Home</a></li>
        <li><a href="#what">What is AskHole?</a></li>
        <li><a href="#how">How it Works</a></li>
        <li><a href="#about">About Me</a></li>
    </ul>

    <div class="nav-buttons">
    <?php if ($isLoggedIn): ?>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_email']); ?>!</span>
        <a href="dashboard.php" class="btn">Dashboard</a>
        <a href="profile.php?id=<?= $user_id ?>" class="btn">My Profile 💜</a>

        <a href="logout.php" class="btn logout-btn">Logout</a>
    <?php else: ?>
        <a href="login.php" class="btn login-btn">Login</a>
        <a href="signup.php" class="btn signup-btn">Sign Up</a>
    <?php endif; ?>
    </div>

</nav>


    <!-- === HERO SECTION === -->
    <section id="home" class="hero">
        <h1>Love is a joke.<br><span class="highlight">Let’s take it seriously.</span></h1>

        <p class="subtitle">
            Send 5 questions. Get answers.  
            Match or move on.  
            No small talk. No pretending.
        </p>

       <button class="cta-btn" id="startButton" 
                data-redirect="<?php echo $isLoggedIn ? 'ask_questions.php' : 'signup.php'; ?>">
            <?php echo $isLoggedIn ? 'Start Asking Questions' : 'Start Asking Questions'; ?>
        </button>
    </section>

    <!-- Fixed JavaScript (put before </body>) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const startButton = document.getElementById("startButton");
        if (startButton) {
            startButton.addEventListener("click", function() {
                window.location.href = this.getAttribute('data-redirect');
            });
        }
    });
    </script>






        <div class="falling-emojis" id="emojiContainer"></div>
    </section>




    <!-- ======= WHAT IS ASKHole ======= -->
<section id="what" class="section what-askhole">
  <div class="container">
    <h2 class="section-title">What is AskHole?</h2>
    <p class="section-description">
      AskHole is the brutally honest dating app where you cut through the small talk and get straight to the point.  
      Send 5 questions, get real answers, and decide if someone’s worth your time or just another joke.  
      No pretending. No endless swiping. Just raw, unapologetic connection (they might still cheat tho).
    </p>
  </div>
</section>

<!-- ======= HOW IT WORKS ======= -->
<section id="how" class="section how-it-works">
  <div class="container">
    <h2 class="section-title">How it Works</h2>

    <div class="steps">
      <div class="step-card">
        <div class="step-number">1</div>
        <h3>Send 5 Questions</h3>
        <p>Choose from pre-set templates or ask your own. Send them to someone you want to know better.</p>
      </div>

      <div class="step-card">
        <div class="step-number">2</div>
        <h3>Get Real Answers</h3>
        <p>The other person answers honestly or ignores you. No pressure, no fake vibes.</p>
      </div>

      <div class="step-card">
        <div class="step-number">3</div>
        <h3>Match or Disconnect</h3>
        <p>Decide if you want to start chatting or end it. You hold the power.</p>
      </div>
    </div>

  </div>
</section>

<!-- ======= ABOUT US ======= -->
<section id="about" class="section about-us">
  <div class="container about-container">
    <h2 class="section-title">About Me</h2>
    <div class="about-content">
      <img src="snethe.jpg" alt="Sinethemba Ndwandwe" class="about-photo" />
      <div class="about-text">
        <p>
          Hey, I’m the brains and heart behind AskHole, built to cut through the dating chaos with brutal honesty and a touch of dark humor.  
          I’m here to help you find love (or at least a good story) without all the fake smiles and small talk.
          Relax dawg, you won't be single as long as I'm still around (wink wink).
          Ask boldly. Answer honestly. Love weirdly.
        </p>
      </div>
    </div>
  </div>
</section>


<footer class="footer">
  <p>
    &copy; 2025 AskHole.Sinethemba Ndwandwe. All rights reserved.  
    <br>
    <button id="topButton" class="scroll-up-btn">Scroll Up Diva</button>
  </p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($isLoggedIn): ?>
        document.body.classList.add('dark-purple-theme');
        document.documentElement.classList.add('dark-purple-theme');
    <?php endif; ?>
});
</script>

    <!-- External JS -->
    <script src="javascript.js"></script>
</body>
</html>
