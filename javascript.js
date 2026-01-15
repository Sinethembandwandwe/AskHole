// Simple falling emoji generator
const emojis = ["😭", "❤️", "🥰", "😘", "😂", "❓"];
const emojiContainer = document.getElementById("emojiContainer");

function createEmoji() {
    const emoji = document.createElement("span");
    emoji.innerText = emojis[Math.floor(Math.random() * emojis.length)];

    emoji.style.left = Math.random() * window.innerWidth + "px";
    emoji.style.animationDuration = (3 + Math.random() * 3) + "s";

    emojiContainer.appendChild(emoji);

    setTimeout(() => {
        emoji.remove();
    }, 5000);
}

// Make emojis fall every 500ms
setInterval(createEmoji, 500);

// CTA button behavior
document.getElementById("startButton").addEventListener("click", function() {
    window.location.href = "signup.php";
});


//scroll up footer
document.getElementById("topButton").addEventListener("click", () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});

//login
document.getElementById('signupForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const name = this.name.value.trim();
  const email = this.email.value.trim();
  const password = this.password.value;

  if (!name || !email || !password) {
    alert('Please fill in all required fields.');
    return;
  }

  // For now, just alert success and clear
  alert(`Welcome, ${name}! Your account has been created.`);
  this.reset();
});

//actual login
document.getElementById('loginForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const email = this.email.value.trim();
  const password = this.password.value;

  if (!email || !password) {
    alert('Please fill in both fields.');
    return;
  }

  // For now, just simulate a login success
  alert(`Welcome back! Logging in as ${email}`);

  this.reset();

  // TODO: connect to backend API for real authentication
});

//theme changing
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.classList.contains('dark-purple-theme')) {
        document.body.style.transition = 'all 0.6s ease-in-out';
        // Purple heart emojis for dating vibe
        createFallingEmojis(['💜', '🖤', '💕', '🌙']);
    }
});
// for question directing you to signup if not logged in
