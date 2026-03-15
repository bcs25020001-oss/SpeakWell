<?php
include 'db.php';
session_start();

// Handle Login
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $result = $conn->query("SELECT * FROM users WHERE email='$email'");
    if ($user = $result->fetch_assoc()) {
        if (password_verify($_POST['password'], $user['password'])) {
            $_SESSION['user'] = $user;
            header("Location: dashboard.php"); exit();
        } else { $error = "Wrong password."; }
    } else { $error = "User not found."; }
}

// Handle Sign Up
if (isset($_POST['signup'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    if($conn->query("SELECT id FROM users WHERE email='$email'")->num_rows > 0) {
        $error = "Email exists.";
    } else {
        $conn->query("INSERT INTO users (email, password, tier, tokens_remaining) VALUES ('$email', '$password', 'free', 8000)");
        $success = "Account Successfully Registered! Login Again.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Speakwell - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <!--Without this, mobile phones will always show the page "zoomed out."-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="auth-body">
    <div class="auth-split">
        <div class="auth-left">
            <div class="auth-motivation">
                <h1 class="brand-title">Speakwell</h1>
                <p class="brand-subtitle">Practice daily. Speak confidently. Learn faster.</p>
                <p class="motivation-note">“A little practice each day makes your English stay!”</p>
            </div>
        </div>

        <div class="auth-right">
            <div class="auth-container">
                <?php if(isset($error)) echo "<div class='alert error'>$error</div>"; ?>
                <?php if(isset($success)) echo "<div class='alert success'>$success</div>"; ?>

                <div class="tab-header">
                    <button id="loginTab" class="tab-btn active" onclick="switchTab('login')">Login</button>
                    <button id="signupTab" class="tab-btn" onclick="switchTab('signup')">Sign Up</button>
                </div>

                <div id="loginSection">
                    <form method="POST">
                        <input type="email" name="email" placeholder="Email Address" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="submit" name="login" class="primary-btn">Login</button>
                    </form>
                </div>

                <div id="signupSection" class="hidden">
                    <form method="POST">
                        <input type="email" name="email" placeholder="Email Address" required>
                        <input type="password" name="password" placeholder="Create Password" required>
                        <button type="submit" name="signup" class="primary-btn signup-btn">Create Account</button>
                    </form>
                </div>

                <div class="divider"><span>or</span></div>

                <!-- Google Sign-In Configuration -->
                <div id="g_id_onload" 
                    data-client_id="770281214264-j46bgmoetme5a9r9uktgj7jomse9vg7h.apps.googleusercontent.com" 
                    data-callback="onGoogle"
                    data-auto_prompt="false"
                    data-auto_select="false"
                    data-prompt="select_account">
                </div>

                <!-- The Login Button -->
                <div class="social-login">
                    <div class="g_id_signin" 
                        data-type="standard" 
                        data-shape="rectangular" 
                        data-theme="outline" 
                        data-text="signin_with" 
                        data-size="large" 
                        data-logo_alignment="left"
                        data-width="280"> <!-- Reduced from 320 to 280 for better fit on small phones -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const loginSec = document.getElementById('loginSection');
            const signupSec = document.getElementById('signupSection');
            const loginTab = document.getElementById('loginTab');
            const signupTab = document.getElementById('signupTab');

            if(type === 'login') {
                loginSec.style.display = 'block';
                signupSec.style.display = 'none';
                loginTab.classList.add('active');
                signupTab.classList.remove('active');
            } else {
                loginSec.style.display = 'none';
                signupSec.style.display = 'block';
                signupTab.classList.add('active');
                loginTab.classList.remove('active');
            }
        }

        function onGoogle(response) {
            console.log("Google response received!"); // For debugging
    
            const formData = new FormData();
            formData.append('credential', response.credential);

            // Send to your backend
            fetch('google_auth.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                console.log("Server response:", data); // Check what the server says
        
                if (data.status === 'success') {
                    // THIS LINE IS WHAT MAKES IT "AUTOMATIC"
                    window.location.href = 'dashboard.php';
            } else {
                    alert('Error from server: ' + data.message);
                }
            })
            .catch(err => {
                console.error("Fetch error:", err);
                alert("Could not connect to the server.");
            });
        }
    </script>
</body>
</html>