<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['new_avatar'])) {
    $userId = $_SESSION['user']['id'];
    $file = $_FILES['new_avatar'];

    if (!is_dir('uploads')) mkdir('uploads', 0777, true);

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'gif');

    if (in_array($fileExt, $allowed)) {
        $newName = "avatar_" . $userId . "_" . time() . "." . $fileExt;
        $targetPath = "uploads/" . $newName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmt = $conn->prepare("UPDATE users SET ai_avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $targetPath, $userId);
            
            if ($stmt->execute()) {
                // Return success and the path to the new image
                echo json_encode(['success' => true, 'new_path' => $targetPath]);
                exit();
            }
        }
    }
}
echo json_encode(['success' => false, 'error' => 'Upload failed']);