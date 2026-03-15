<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$userSession = $_SESSION['user'];
$userId = $userSession['id'];

// Refresh data
$result = $conn->query("SELECT * FROM users WHERE id = $userId");
$userData = $result->fetch_assoc();
$_SESSION['user'] = $userData;

$tier = $userData['tier'];
$tokens = $userData['tokens_remaining'];
$userName = $userData['name'] ?? 'Student';
$userEmail = $_SESSION['user']['email'];

$maxTokens = ($tier == 'premium') ? 50000 : 8000;
$percent = ($maxTokens > 0) ? ($tokens / $maxTokens) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speakwell - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <h2 class="brand-header">SPEAKWELL</h2>
        
        <div class="user-info-box">
            <span class="sidebar-label">Logged in as:</span>
            <strong><?php echo htmlspecialchars($userEmail); ?></strong>
        </div>
        
        <div class="sidebar-account">
            <p class="sidebar-label">Account Status</p>
            <p class="sidebar-value"><?php echo ($tier == 'premium') ? "⭐ Premium Member" : "Free Tier Account"; ?></p>

            <div class="sidebar-token-row">
                <span class="sidebar-label">Tokens Remaining</span>
                <div>
                    <span class="sidebar-token-count"><?php echo number_format($tokens); ?></span>
                    <span class="sidebar-token-max">/ <?php echo number_format($maxTokens); ?></span>
                </div>
            </div>

            <div class="progress-container"><div class="progress-bar" style="width: <?php echo $percent; ?>%;"></div></div>

            <?php if($tier == 'free'): ?>
                <button onclick="confirmUpgrade()" class="btn-premium sidebar-upgrade">🚀 Upgrade to Premium</button>
            <?php endif; ?>
        </div>

        <div class="side-nav">
            <a href="game.php" class="nav-game">🎮 Word Sprint</a>
            <a href="guess_who.php" class="nav-game">🧠 Guess Who</a>
            <a href="history.php" class="nav-history">🕒 Practice History</a>
            <a href="logout.php" class="nav-logout">🚪 Logout Account</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <section class="hero">
            <h1 class="brand-header">SPEAKWELL</h1>
            <p class="hero-subtitle">Practice speaking English with smart prompts, fun games, and real-time feedback. Build confidence with every conversation.</p>

            <div class="hero-items">
                <div class="hero-card">
                    <div class="hero-icon">🧠</div>
                    <div>
                        <h3>Play & Learn</h3>
                        <p>Words, clues, and practice challenges that boost fluency.</p>
                    </div>
                </div>
                <div class="hero-card">
                    <div class="hero-icon">⚡</div>
                    <div>
                        <h3>Fast Feedback</h3>
                        <p>Earn tokens as you improve and track your progress.</p>
                    </div>
                </div>
                <div class="hero-card">
                    <div class="hero-icon">🎯</div>
                    <div>
                        <h3>Topics You Choose</h3>
                        <p>Pick a theme that matches your goals and start speaking.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Choose your next challenge</h2>
                    <p class="panel-subtitle">Select a theme or play a mini-game to keep practicing.</p>
                </div>
                <div class="token-summary">
                    <div class="token-title">Tokens</div>
                    <div class="token-info">
                        <span class="token-count"><?php echo number_format($tokens); ?></span>
                        <span class="token-max">/ <?php echo number_format($maxTokens); ?></span>
                    </div>
                    <div class="progress-container"><div class="progress-bar" style="width: <?php echo $percent; ?>%;"></div></div>
                </div>
            </div>

            <div class="theme-grid">
                <button class="theme-card-btn" onclick="startChat('Free Talk')">
                    <span class="icon">🗣️</span>
                    <span class="label">Free Talk</span>
                </button>
                <button class="theme-card-btn" onclick="startChat('Daily Life')">
                    <span class="icon">🏠</span>
                    <span class="label">Daily Life</span>
                </button>
                <button class="theme-card-btn" onclick="startChat('Hobbies')">
                    <span class="icon">🎨</span>
                    <span class="label">Hobbies</span>
                </button>
                <button class="theme-card-btn" onclick="startChat('Travel')">
                    <span class="icon">✈️</span>
                    <span class="label">Travel</span>
                </button>
                <button class="theme-card-btn" onclick="startChat('Technology')">
                    <span class="icon">🖥️</span>
                    <span class="label">Technology</span>
                </button>
                <button class="theme-card-btn" onclick="startChat('Job Interview')">
                    <span class="icon">💼</span>
                    <span class="label">Job Interview</span>
                </button>
                <button class="theme-card-btn" onclick="window.location.href='game.php'">
                    <span class="icon">🎮</span>
                    <span class="label">Word Sprint</span>
                </button>
                <button class="theme-card-btn" onclick="window.location.href='guess_who.php'">
                    <span class="icon">🧠</span>
                    <span class="label">Guess Who</span>
                </button>
            </div>
        </section>
    </div>

    <script>
        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const ov = document.getElementById('overlay');
            const menuBtn = document.querySelector('.menu-toggle');
            
            sb.classList.toggle('active');
            
            if (sb.classList.contains('active')) {
                ov.style.display = 'block';
                // Optional: change icon to 'X' when open
                menuBtn.innerHTML = '✕'; 
            } else {
                ov.style.display = 'none';
                menuBtn.innerHTML = '☰';
            }
        }

        function startChat(theme) {
            window.location.href = "chat.php?theme=" + encodeURIComponent(theme);
        }

        // NEW: Confirmation function for Upgrade
        function confirmUpgrade() {
            const response = confirm("Ready to unlock 50,000 tokens? Click OK to upgrade to SPEAKWELL Premium! ✨");
            if (response) {
                window.location.href = 'upgrade.php';
            }
        }
    </script>
</body>
</html>