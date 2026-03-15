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
    <title>Speakwell - Guess Who</title>
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
            <a href="guess_who.php" class="nav-game">🎮 Guess Who</a>
            <a href="game.php" class="nav-game">🎮 Word Sprint</a>
            <a href="history.php" class="nav-history">🕒 Practice History</a>
            <a href="logout.php" class="nav-logout">🚪 Logout</a>
        </div>
    </div>

    <div class="page-content">
        <h1 class="brand-header">Guess Who</h1>
        <p class="game-subtitle">The AI gives you clues. Guess the person (or character) as fast as you can!</p>

        <div class="card">
            <h2 class="game-header">How to Play</h2>
            <p class="game-subtitle">Start a round, then ask the AI for a clue. You get up to <strong id="clueLimit">3</strong> clues and <strong id="guessLimit">5</strong> guesses.</p>

            <div class="clue-box">
                <div class="clue-label">Clue <span id="clueNumber">—</span> / <span id="clueTotal">—</span></div>
                <div class="clue-text" id="clueText">Press "Start Game" to begin.</div>
            </div>

            <div class="stack">
                <input id="guessInput" class="guess-input" type="text" placeholder="Type your guess here..." disabled>
                <div class="stack-row">
                    <button id="startBtn" class="btn btn-primary">Start Game</button>
                    <button id="clueBtn" class="btn btn-secondary" disabled>Get Clue</button>
                    <button id="guessBtn" class="btn btn-secondary" disabled>Submit Guess</button>
                </div>
                <div id="statusBox" class="score-box status-box"></div>
            </div>

            <div class="game-meta">
                <div><strong>Clues used:</strong> <span id="cluesUsed">0</span> / <span id="clueTotal2">0</span></div>
                <div><strong>Guesses used:</strong> <span id="guessesUsed">0</span> / <span id="guessTotal2">0</span></div>
            </div>
        </div>
    </div>

    <script>
        const gameItems = [
            {
                name: 'Marie Curie',
                clues: [
                    'I was the first woman to win a Nobel Prize.',
                    'I discovered two elements: polonium and radium.',
                    'My work helped create the field of radioactivity.',
                    'I shared a Nobel Prize in Physics with my husband.'
                ]
            },
            {
                name: 'Sherlock Holmes',
                clues: [
                    'I live on Baker Street.',
                    'I am a detective known for logical reasoning.',
                    'My friend is Dr. Watson.',
                    'I was created by Arthur Conan Doyle.'
                ]
            },
            {
                name: 'Leonardo da Vinci',
                clues: [
                    'I painted the Mona Lisa.',
                    'I was an artist and an inventor during the Renaissance.',
                    'I wrote notes in mirror script.',
                    'I drew the Vitruvian Man.'
                ]
            },
            {
                name: 'Amelia Earhart',
                clues: [
                    'I was a pioneering pilot.',
                    'I was the first female aviator to fly solo across the Atlantic.',
                    'My disappearance remains a famous mystery.',
                    'I wrote a book called "The Fun of It".'
                ]
            },
            {
                name: 'Albert Einstein',
                clues: [
                    'I am known for the equation E=mc².',
                    'I developed the theory of relativity.',
                    'I won the Nobel Prize in Physics in 1921.',
                    'My hair is often pictured as wild and white.'
                ]
            }
        ];

        const clueLimit = 3;
        const guessLimit = 5;

        const clueText = document.getElementById('clueText');
        const clueNumber = document.getElementById('clueNumber');
        const clueTotal = document.getElementById('clueTotal');
        const clueTotal2 = document.getElementById('clueTotal2');
        const cluesUsed = document.getElementById('cluesUsed');
        const guessInput = document.getElementById('guessInput');
        const startBtn = document.getElementById('startBtn');
        const clueBtn = document.getElementById('clueBtn');
        const guessBtn = document.getElementById('guessBtn');
        const statusBox = document.getElementById('statusBox');
        const guessesUsed = document.getElementById('guessesUsed');
        const tokenBar = document.getElementById('tokenBar');
        const tokenCount = document.getElementById('tokenCount');

        let gameState = {
            answer: '',
            clues: [],
            clueIndex: 0,
            attemptCount: 0,
            solved: false
        };

        function resetUI() {
            clueNumber.textContent = '—';
            clueTotal.textContent = clueLimit;
            clueTotal2.textContent = clueLimit;
            cluesUsed.textContent = '0';
            guessInput.value = '';
            guessesUsed.textContent = '0';
            document.getElementById('clueLimit').textContent = clueLimit;
            document.getElementById('guessLimit').textContent = guessLimit;

            statusBox.classList.remove('visible');
            statusBox.textContent = '';
        }

        function startGame() {
            const item = gameItems[Math.floor(Math.random() * gameItems.length)];
            gameState.answer = item.name.toLowerCase();
            gameState.clues = item.clues;
            gameState.clueIndex = 0;
            gameState.attemptCount = 0;
            gameState.solved = false;

            resetUI();

            clueText.textContent = 'Press "Get Clue" to see your first hint.';
            clueNumber.textContent = '0';
            cluesUsed.textContent = '0';
            guessesUsed.textContent = '0';

            guessInput.disabled = false;
            clueBtn.disabled = false;
            guessBtn.disabled = false;
            startBtn.textContent = 'Restart Game';
            guessInput.focus();
        }

        function getClue() {
            if (gameState.clueIndex >= clueLimit) {
                statusBox.classList.add('visible');
                statusBox.textContent = 'You have used all your clues. Try guessing!';
                clueBtn.disabled = true;
                return;
            }

            const clue = gameState.clues[gameState.clueIndex] || 'No more clues available.';
            gameState.clueIndex += 1;
            clueText.textContent = clue;
            clueNumber.textContent = gameState.clueIndex;
            cluesUsed.textContent = gameState.clueIndex;

            if (gameState.clueIndex >= clueLimit) {
                clueBtn.disabled = true;
            }

            statusBox.classList.remove('visible');
        }

        function makeGuess() {
            if (gameState.solved) return;

            const guess = guessInput.value.trim().toLowerCase();
            if (!guess) {
                alert('Please type your guess first.');
                return;
            }

            gameState.attemptCount += 1;
            guessesUsed.textContent = gameState.attemptCount;

            if (guess === gameState.answer) {
                handleWin();
                return;
            }

            if (gameState.attemptCount >= guessLimit) {
                handleLose();
                return;
            }

            statusBox.classList.add('visible');
            statusBox.textContent = `Not quite! You have ${guessLimit - gameState.attemptCount} guesses left.`;
        }

        function handleWin() {
            gameState.solved = true;
            const tokensAwarded = 20 + (clueLimit - gameState.clueIndex) * 10;
            statusBox.classList.add('visible');
            statusBox.textContent = `🎉 Correct! It was ${capitalize(gameState.answer)}. You earned ${tokensAwarded} tokens.`;

            awardTokens(tokensAwarded);
            endRound();
        }

        function handleLose() {
            gameState.solved = true;
            statusBox.classList.add('visible');
            statusBox.textContent = `You ran out of guesses. The answer was ${capitalize(gameState.answer)}. Try again!`;
            endRound();
        }

        function endRound() {
            guessInput.disabled = true;
            clueBtn.disabled = true;
            guessBtn.disabled = true;
            startBtn.textContent = 'Play Again';
        }

        function awardTokens(amount) {
            fetch('guess_who.php', {
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

        function capitalize(text) {
            return text.replace(/\b\w/g, (c) => c.toUpperCase());
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
        clueBtn.addEventListener('click', getClue);
        guessBtn.addEventListener('click', makeGuess);

        resetUI();
    </script>
</body>
</html>
