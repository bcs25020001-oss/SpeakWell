<?php
include 'db.php';
session_start();

// Security: User must be signed in
if (!isset($_SESSION['user'])) { 
    header("Location: index.php"); 
    exit(); 
}

$theme = $_GET['theme'] ?? 'Free Talk';
$userEmail = $_SESSION['user']['email'];
$userId = $_SESSION['user']['id'];

// 1. Fetch the avatar path from the database
$stmt = $conn->prepare("SELECT ai_avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res->fetch_assoc();

$dbPath = $userData['ai_avatar'] ?? '';

// 2. Logic: Check if the path is not empty AND if the file actually exists on the disk
if (!empty($dbPath) && file_exists($dbPath)) {
    $aiAvatar = $dbPath;
} else {
    // Fallback if the record is empty OR if someone deleted the file from the folder
    $aiAvatar = 'AI_Avatar.png';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speakwell AI - <?php echo $theme; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Sidebar Toggle Button -->
<button class="menu-toggle" onclick="toggleSidebar()">☰</button>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<!-- Side Menu -->
<div class="sidebar" id="sidebar">
    <h2 class="brand-header">SPEAKWELL</h2>
    <div class="user-info-box">
        <span class="sidebar-label">Logged in as:</span>
        <strong><?php echo htmlspecialchars($userEmail); ?></strong>
    </div>

    <div class="side-nav">
        <a href="dashboard.php" class="nav-history">🏠 Dashboard</a>
        <a href="game.php" class="nav-game">🎮 Word Sprint</a>
        <a href="guess_who.php" class="nav-game">🧠 Guess Who</a>
        <a href="chat.php?theme=Free Talk" class="nav-history">✨ New Chat</a>
        <a href="history.php" class="nav-history">🕒 Practice History</a>
        <a href="logout.php" class="nav-logout">🚪 Logout Account</a>
    </div>
</div>

<div class="chat-card">

    <!-- MODIFIED AVATAR SECTION -->
    <div class="avatar-container">
        <div id="ai-img" class="ai-avatar">
            <!-- Source now comes from Database variable -->
            <img src="<?php echo $aiAvatar; ?>" id="aiAvatarImg">
        </div>
        
        <!-- The small Camera Button -->
        <button class="edit-avatar-btn" onclick="document.getElementById('avatarInput').click()" title="Change AI Avatar">
            📷
        </button>

        <!-- Hidden form for upload -->
        <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" class="hidden">
            <input type="file" name="new_avatar" id="avatarInput" accept="image/*" onchange="uploadAvatarAjax()">
        </form>
    </div>

    <p id="status">Ready</p>
    <div class="chat-box" id="chatBox"></div>

    
    <!-- Row with Clear Chat, Microphone, and Stop -->
    <div class="controls">
        <button id="clearBtn" class="btn btn-clear">🧹 Clear Chat</button>
        <button id="micBtn" class="btn btn-ready">🎤 Click to Speak</button>
        <button id="stopBtn" class="btn btn-stop">⏹ Stop</button>
    </div>

    <!-- Theme Dropdown -->
    <div class="theme-selector-container">
        <p class="theme-selector-label">Switch Topic:</p>
        <select class="theme-dropdown" onchange="changeTheme(this.value)">
            <option value="Free Talk" <?php if($theme == 'Free Talk') echo 'selected'; ?>>🗣️ Free Talk</option>
            <option value="Daily Life" <?php if($theme == 'Daily Life') echo 'selected'; ?>>🏠 Daily Life</option>
            <option value="Hobbies" <?php if($theme == 'Hobbies') echo 'selected'; ?>>🎨 Hobbies</option>
            <option value="Travel" <?php if($theme == 'Travel') echo 'selected'; ?>>✈️ Travel</option>
            <option value="Technology" <?php if($theme == 'Technology') echo 'selected'; ?>>🖥️ Technology</option>
            <option value="Job Interview" <?php if($theme == 'Job Interview') echo 'selected'; ?>>💼 Job Interview</option>
        </select>
    </div>
</div>

<script>
    // --- SIDEBAR LOGIC ---
    function toggleSidebar() {
        const sb = document.getElementById('sidebar');
        const ov = document.getElementById('overlay');
        sb.classList.toggle('active');
        ov.style.display = (ov.style.display === 'block') ? 'none' : 'block';
    }

    function changeTheme(val) {
        window.location.href = "chat.php?theme=" + encodeURIComponent(val);
    }

    // --- ORIGINAL AI LOGIC (REMAINED SAME) ---
    const API_KEY = "AIzaSyD4ftEL4cDG2WX0dWVSJaBV_KoAzzikY94"; 
    const MODEL_ID = "gemini-2.5-flash"; 

    const micBtn = document.getElementById('micBtn');
    const stopBtn = document.getElementById('stopBtn');
    const clearBtn = document.getElementById('clearBtn');
    const chatBox = document.getElementById('chatBox');
    const status = document.getElementById('status');
    const aiImg = document.getElementById('ai-img');

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    
    const synth = window.speechSynthesis;
    let lastUserText = "";
    const theme = "<?php echo $theme; ?>";
    
    // UPDATED SYSTEM PROMPT WITH STRICT SAFETY AND FRIENDLY RULES
    const systemPrompt = `You are SPEAKWELL AI, a friendly best friend and English teacher.
    Theme: ${theme}.
    STRICT SAFETY RULE: If the user asks about intense topics or bad words, politely say sorry and refuse, then encourage them to speak about English.
    PERSONALITY: Very friendly, warm, and helpful. Answer accurately and nicely.
    REPLY RULES: 1 to 2 SHORT sentences. Simple words.
    Grammar Tip: If user makes mistake, add: [Friendly Tip: Mistake -> Correction]. 
    Ending: Always end with a short friendly question.`;

    window.onload = () => callGemini(`Hi, briefly greet me about ${theme}. Then ask me short question to start our conversation.`, true);

    micBtn.onclick = () => {
        try { recognition.start(); } catch(e) { recognition.stop(); }
    };

    recognition.onstart = () => updateUI(true, "Listening...");
    recognition.onresult = (e) => {
        const text = e.results[0][0].transcript;
        lastUserText = text;
        appendMsg('user', text);
        updateUI(true, "Thinking...");
        callGemini(`${systemPrompt}. User said: "${text}"`);
    };

    clearBtn.onclick = () => {
        chatBox.innerHTML = '';
        synth.cancel();
        callGemini(`Greet me again. Just brief shortly about ${theme}.`, true);
    };

    stopBtn.onclick = () => {
        synth.cancel();
        try { recognition.stop(); } catch(e) {}
        aiImg.classList.remove('speaking');
        updateUI(false, "Ready");
    };

    async function callGemini(prompt, isInit = false) {
        try {
            const url = `https://generativelanguage.googleapis.com/v1/models/${MODEL_ID}:generateContent?key=${API_KEY}`;
            const response = await fetch(url, {
                method: "POST", headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
            });
            const data = await response.json();
            const parts = data.candidates[0].content.parts;
            const aiText = parts.find(p => p.text)?.text || "No response received";
            appendMsg('ai', aiText);
            speak(aiText);
            if (!isInit) saveToDatabase(lastUserText, aiText);
        } catch (err) { updateUI(false, "Ready"); }
    }

    async function saveToDatabase(userTxt, aiTxt) {
        const formData = new FormData();
        formData.append('user_text', userTxt);
        formData.append('ai_text', aiTxt);
        formData.append('theme', theme);
        fetch('process_chat.php', { method: 'POST', body: formData });
    }

    function speak(text) {
        const clean = text.replace(/\[.*?\]/g, ""); 
        const utter = new SpeechSynthesisUtterance(clean);
        const voices = synth.getVoices();
        let femaleVoice = voices.find(voice => 
            voice.name.includes("Female") || voice.name.includes("Samantha") || 
            voice.name.includes("Google US English") || voice.name.includes("Aria")
        );
        if (femaleVoice) utter.voice = femaleVoice;
        utter.pitch = 1.1; 
        utter.rate = 1.0;  
        utter.onstart = () => { aiImg.classList.add('speaking'); updateUI(true, "Speaking..."); };
        utter.onend = () => { aiImg.classList.remove('speaking'); updateUI(false, "Ready"); };
        synth.speak(utter);
    }

    function updateUI(busy, txt) {
        status.innerText = txt;
        micBtn.className = busy ? "btn btn-busy" : "btn btn-ready";
        micBtn.innerText = busy ? "Please Wait..." : "🎤 Click to Speak";
    }

    function appendMsg(sender, text) {
        const d = document.createElement('div');
        d.className = `msg msg-${sender}`;
        d.innerHTML = text.replace(/\[(.*?)\]/g, '<span class="grammar-tip">✨ $1</span>');
        chatBox.appendChild(d);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    window.speechSynthesis.onvoiceschanged = () => {
        window.speechSynthesis.getVoices();
    };

    async function uploadAvatarAjax() {
        const fileInput = document.getElementById('avatarInput');
        const avatarImg = document.getElementById('aiAvatarImg');
        const statusText = document.getElementById('status');

        if (fileInput.files.length === 0) return;

        const formData = new FormData();
        formData.append('new_avatar', fileInput.files[0]);

        statusText.innerText = "Updating Avatar...";

        try {
            const response = await fetch('upload_avatar_ajax.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update the image source without reloading the page
                // We add a timestamp (?t=...) to force the browser to bypass cache
                avatarImg.src = result.new_path + "?t=" + new Date().getTime();
                statusText.innerText = "Avatar Updated!";
                setTimeout(() => { statusText.innerText = "Ready"; }, 2000);
            } else {
                alert("Upload failed: " + result.error);
                statusText.innerText = "Ready";
            }
        } catch (error) {
            console.error("Error:", error);
            statusText.innerText = "Error uploading";
        }
    }
</script>
</body>
</html>