<?php
include 'db.php';
session_start();

// Security check: Make sure user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user']['id'];

/**
 * PROTOTYPE LOGIC:
 * In a real app, this is where you would integrate PayPal or Stripe API.
 * For this prototype, we simply update the database status.
 */

$newTier = 'premium';
$newTokens = 50000;

$sql = "UPDATE users SET tier = '$newTier', tokens_remaining = $newTokens WHERE id = $userId";

if ($conn->query($sql) === TRUE) {
    // CRITICAL: Update the SESSION data so the dashboard reflects changes immediately
    $_SESSION['user']['tier'] = $newTier;
    $_SESSION['user']['tokens_remaining'] = $newTokens;

    // Redirect back with a success message
    header("Location: dashboard.php?upgrade=success");
} else {
    // If something goes wrong
    header("Location: dashboard.php?upgrade=error");
}
exit();
?>