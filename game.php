<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$userSession = $_SESSION['user'];
$userId = $userSession['id'];
$userEmail = $userSession['email'];
$tier = $userSession['tier'];
$tokens_remaining = $userSession['tokens_remaining'];

// Award tokens endpoint (called via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_tokens'])) {
    $award = intval($_POST['award_tokens']);
    if ($award > 0) {
        $conn->query("UPDATE users SET tokens_remaining = tokens_remaining + $award WHERE id = $userId");
        $res = $conn->query("SELECT tokens_remaining FROM users WHERE id = $userId");
        $row = $res->fetch_assoc();
        $_SESSION['user']['tokens_remaining'] = $row['tokens_remaining'];
        echo json_encode(['status' => 'success', 'tokens' => $row['tokens_remaining']]);
        exit();
    }
    echo json_encode(['status' => 'error', 'message' => 'Invalid award amount']);
    exit();
}

// Refresh token info for rendering
$result = $conn->query("SELECT tokens_remaining, tier FROM users WHERE id = $userId");
$userData = $result->fetch_assoc();
$_SESSION['user'] = array_merge($_SESSION['user'], $userData);
$tokens_remaining = $userData['tokens_remaining'];
$tier = $userData['tier'];
$maxTokens = ($tier == 'premium') ? 50000 : 8000;
$percent = ($maxTokens > 0) ? ($tokens_remaining / $maxTokens) * 100 : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speakwell - Word Sprint</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

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
                    <span class="sidebar-token-count"><?php echo number_format($tokens_remaining); ?></span>
                    <span class="sidebar-token-max">/ <?php echo number_format($maxTokens); ?></span>
                </div>
            </div>

            <div class="progress-container"><div class="progress-bar" id="tokenBar" style="width: <?php echo $percent; ?>%;"></div></div>

            <?php if($tier == 'free'): ?>
                <button onclick="confirmUpgrade()" class="btn btn-primary sidebar-upgrade">🚀 Upgrade to Premium</button>
            <?php endif; ?>
        </div>

        <div class="side-nav">
            <a href="dashboard.php" class="nav-history">🏠 Dashboard</a>
            <a href="game.php" class="nav-game">🎮 Word Sprint</a>
            <a href="guess_who.php" class="nav-game">🧠 Guess Who</a>
            <a href="history.php" class="nav-history">🕒 Practice History</a>
            <a href="logout.php" class="nav-logout">🚪 Logout</a>
        </div>
    </div>

    <div class="page-content">
        <h1 class="brand-header">Word Sprint</h1>
        <p class="game-subtitle">Get faster at thinking in English — build quick sentences using target words.</p>

        <div class="card">
            <h2 class="game-header">Ready to Race?</h2>
            <p class="game-subtitle">You have <strong id="timerDisplay">30</strong>s to write a sentence containing the word shown below. Every word you type gives you points!</p>
            <div class="word-box" id="wordBox">--</div>
            <div class="timer">Time left: <strong id="timeLeft">30</strong>s</div>

            <textarea id="sentence" placeholder="Write a short sentence using the word above..." disabled></textarea>

            <div class="stack">
                <div class="stack-row">
                    <button id="startBtn" class="btn btn-primary">Start Game</button>
                    <button id="submitBtn" class="btn btn-secondary" disabled>Submit Sentence</button>
                    <button id="nextWordBtn" class="btn btn-secondary" disabled>Next Word</button>
                </div>
                <div id="scoreBox" class="score-box status-box"></div>
            </div>
        </div>
    </div>

    <script>
        const words = [
            'Journey','Excited','Relax','Challenge','Dream','Create','Friend','Funny','Listen','Teach',
            'Brave','Hope','Smile','Learn','Travel','Music','Story','Sunny','Forest','Future'
        ];

        let timer = null;
        let timeLeft = 30;
        let currentWord = '';

        const wordBox = document.getElementById('wordBox');
        const timeLeftEl = document.getElementById('timeLeft');
        const timerDisplay = document.getElementById('timerDisplay');
        const sentenceInput = document.getElementById('sentence');
        const startBtn = document.getElementById('startBtn');
        const submitBtn = document.getElementById('submitBtn');
        const nextWordBtn = document.getElementById('nextWordBtn');
        const scoreBox = document.getElementById('scoreBox');
        const tokenBar = document.getElementById('tokenBar');
        const tokenCount = document.getElementById('tokenCount');

        function pickWord() {
            currentWord = words[Math.floor(Math.random() * words.length)];
            wordBox.textContent = currentWord;
        }

        function updateTimer() {
            timeLeft -= 1;
            timeLeftEl.textContent = timeLeft;
            timerDisplay.textContent = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(timer);
                endRound();
            }
        }

        function startGame() {
            pickWord();
            sentenceInput.value = '';
            sentenceInput.disabled = false;
            sentenceInput.focus();
            submitBtn.disabled = false;
            nextWordBtn.disabled = true;
            scoreBox.classList.remove('visible');

            timeLeft = 30;
            timeLeftEl.textContent = timeLeft;
            timerDisplay.textContent = timeLeft;

            if (timer) clearInterval(timer);
            timer = setInterval(updateTimer, 1000);
        }

        function endRound() {
            sentenceInput.disabled = true;
            submitBtn.disabled = true;
            nextWordBtn.disabled = false;
            scoreBox.classList.add('visible');
            scoreBox.textContent = 'Time is up! Tap "Next Word" to try again.';
        }

        function submitSentence() {
            const sentence = sentenceInput.value.trim();
            if (!sentence) {
                alert('Please type a sentence using the word above.');
                return;
            }

            const words = sentence.split(/\s+/).filter(Boolean);
            let score = words.length;

            // Optional bonus if the prompt word is included
            if (sentence.toLowerCase().includes(currentWord.toLowerCase())) {
                score += 2;
            }

            scoreBox.classList.add('visible');
            scoreBox.textContent = `Great! You scored ${score} points. (Includes bonus for using the word)`;

            // Award tokens (bonus for effort)
            awardTokens(score * 2);

            // Stop timer but allow next word
            clearInterval(timer);
            submitBtn.disabled = true;
            nextWordBtn.disabled = false;
        }

        function awardTokens(amount) {
            fetch('game.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'award_tokens=' + encodeURIComponent(amount)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    tokenCount.textContent = Number(data.tokens).toLocaleString();
                    const percent = Math.min(100, (data.tokens / <?php echo $maxTokens; ?>) * 100);
                    tokenBar.style.width = `${percent}%`;
                }
            });
        }

        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            const ov = document.getElementById('overlay');
            const menuBtn = document.querySelector('.menu-toggle');
            sb.classList.toggle('active');
            if (sb.classList.contains('active')) {
                ov.style.display = 'block';
                menuBtn.innerHTML = '✕';
            } else {
                ov.style.display = 'none';
                menuBtn.innerHTML = '☰';
            }
        }

        function confirmUpgrade() {
            const response = confirm("Ready to unlock 50,000 tokens? Click OK to upgrade to SPEAKWELL Premium! ✨");
            if (response) {
                window.location.href = 'upgrade.php';
            }
        }

        startBtn.addEventListener('click', startGame);
        submitBtn.addEventListener('click', submitSentence);
        nextWordBtn.addEventListener('click', () => {
            pickWord();
            sentenceInput.value = '';
            sentenceInput.disabled = false;
            sentenceInput.focus();
            submitBtn.disabled = false;
            nextWordBtn.disabled = true;
            timeLeft = 30;
            timeLeftEl.textContent = timeLeft;
            timerDisplay.textContent = timeLeft;
            scoreBox.style.display = 'none';
            if (timer) clearInterval(timer);
            timer = setInterval(updateTimer, 1000);
        });
    </script>
</body>
</html>
