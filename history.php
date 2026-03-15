<?php
include 'db.php';
session_start();

// Security: User must be signed in
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user']['id'];

// --- HANDLE CLEAR HISTORY ACTION ---
if (isset($_POST['clear_action'])) {
    $stmt = $conn->prepare("DELETE FROM history WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        header("Location: history.php?status=cleared");
        exit();
    }
}

// Fetch history
$result = $conn->query("SELECT * FROM history WHERE user_id = $userId ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Speakwell - History</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>

        <div class="history-container">
            <div class="header">
                <a href="dashboard.php" class="btn-back">⬅ Back</a>
                <h2 class="title">Practice History</h2>
                
                <!-- Clear History Form -->
                <form method="POST" id="clearForm">
                    <input type="hidden" name="clear_action" value="1">
                    <button type="button" class="btn-clear" onclick="confirmDelete()">🗑️ Clear All</button>
                </form>
            </div>

            <div class="history-list">
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="history-item">
                            <div class="meta">
                                <span><?php echo date('M d, Y • h:i A', strtotime($row['created_at'])); ?></span>
                                <span class="theme-badge"><?php echo htmlspecialchars($row['theme']); ?></span>
                            </div>
                            <p class="msg-text">
                                <span class="sender-tag <?php echo ($row['sender'] == 'user') ? 'user-tag' : 'ai-tag'; ?>">
                                    <?php echo strtoupper($row['sender']); ?>:
                                </span>
                                <?php echo htmlspecialchars($row['message']); ?>
                            </p>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No practice history found yet.<br>Start chatting to see your progress!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function confirmDelete() {
                if (confirm("Are you sure you want to clear your entire history? This action cannot be undone.")) {
                    document.getElementById('clearForm').submit();
                }
            }
        </script>

    </body>
</html>