<?php
session_start();
require "db_connection.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== "student") {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// Sanitize input
$current = trim($_POST['current_password'] ?? '');
$new = trim($_POST['new_password'] ?? '');
$confirm = trim($_POST['confirm_password'] ?? '');

// Basic validations
if (empty($current) || empty($new) || empty($confirm)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: student_dashboard.php#password-section");
    exit();
}

if ($new !== $confirm) {
    $_SESSION['error'] = "New password and confirmation do not match.";
    header("Location: student_dashboard.php#password-section");
    exit();
}

if (strlen($new) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters.";
    header("Location: student_dashboard.php#password-section");
    exit();
}

// Fetch current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($hashed);
$stmt->fetch();
$stmt->close();

if (!$hashed || !password_verify($current, $hashed)) {
    $_SESSION['error'] = "Current password is incorrect.";
    header("Location: student_dashboard.php#password-section");
    exit();
}

// Update new password
$newHash = password_hash($new, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $newHash, $userId);

if ($update->execute()) {
    $_SESSION['success'] = "Password updated successfully!";
    header("Location: student_dashboard.php#password-section");
} else {
    $_SESSION['error'] = "Error updating password. Try again later.";
    header("Location: student_dashboard.php#password-section");
}
exit();
?>
