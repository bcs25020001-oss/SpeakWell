<?php
include 'db.php';
session_start();

// This script receives the Google Token, verifies it, and starts the user session
if (isset($_POST['credential'])) {
    $id_token = $_POST['credential'];
    
    // We use cURL to ask Google if this token is real
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important for localhost testing
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $payload = json_decode($response, true);

    // If Google says the email is valid
    if (isset($payload['email'])) {
        $email = $conn->real_escape_string($payload['email']);
        
        // 1. Check if this Google user is already in our database
        $result = $conn->query("SELECT * FROM users WHERE email='$email'");
        
        if ($result->num_rows > 0) {
            // User exists! Log them in
            $user = $result->fetch_assoc();
            $_SESSION['user'] = $user;
        } else {
            // 2. New User! Create an account for them automatically
            // We create a random password they won't need because they use Google
            $random_pass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
            
            $conn->query("INSERT INTO users (email, password, tier, tokens_remaining) 
                          VALUES ('$email', '$random_pass', 'free', 2000)");
            
            // Log in the newly created user
            $new_id = $conn->insert_id;
            $res = $conn->query("SELECT * FROM users WHERE id=$new_id");
            $_SESSION['user'] = $res->fetch_assoc();
        }
        
        // Tell the browser "Success!" so it can redirect to dashboard.php
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Google Token']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received from Google']);
}
?>