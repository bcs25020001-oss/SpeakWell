<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_SESSION['user']['id'];
    $userText = $conn->real_escape_string($_POST['user_text']);
    $aiText = $conn->real_escape_string($_POST['ai_text']);
    $theme = $conn->real_escape_string($_POST['theme'] ?? 'General');

    // Calculate simulated tokens
    $tokensUsed = strlen($userText) + strlen($aiText);

    // Remove the 'if ($userId > 0)' check. Just run the queries directly.
    $conn->query("INSERT INTO history (user_id, message, sender, theme) VALUES ($userId, '$userText', 'user', '$theme')");
    $conn->query("INSERT INTO history (user_id, message, sender, theme) VALUES ($userId, '$aiText', 'ai', '$theme')");
    $conn->query("UPDATE users SET tokens_remaining = tokens_remaining - $tokensUsed WHERE id = $userId");

    $res = $conn->query("SELECT tokens_remaining FROM users WHERE id = $userId");
    $row = $res->fetch_assoc();
    $_SESSION['user']['tokens_remaining'] = $row['tokens_remaining'];

    echo json_encode([
        'status' => 'success', 
        'new_balance' => $_SESSION['user']['tokens_remaining']
    ]);
}
?>