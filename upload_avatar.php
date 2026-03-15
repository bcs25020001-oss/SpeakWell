<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    die("Session expired. Please log in again.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['new_avatar'])) {
    $userId = $_SESSION['user']['id'];
    $file = $_FILES['new_avatar'];

    // 1. Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed with error code: " . $file['error']);
    }

    // 2. Create 'uploads' folder if it doesn't exist
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    // 3. Prepare file info
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (in_array($fileExt, $allowed)) {
        $newName = "avatar_" . $userId . "_" . time() . "." . $fileExt;
        $targetPath = "uploads/" . $newName;

        // 4. Move file to folder
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // 5. Update Database
            $stmt = $conn->prepare("UPDATE users SET ai_avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $targetPath, $userId);
            
            if ($stmt->execute()) {
                header("Location: chat.php?upload=success");
                exit();
            } else {
                echo "Database error: " . $conn->error;
            }
        } else {
            echo "Failed to move file. Check folder permissions.";
        }
    } else {
        echo "Invalid file type. Only JPG, PNG, and GIF allowed.";
    }
} else {
    header("Location: chat.php");
}
?>