<?php
session_start();
require "db_connection.php";

// Ensure the user is logged in and is a student
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// Sanitize and validate inputs
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($fullname !== '' && strlen($fullname) < 2) {
    $_SESSION['error'] = "Name must have at least 2 characters.";
    header("Location: student_dashboard.php");
    exit();
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: student_dashboard.php");
    exit();
}

// Handle file uploads
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$profileFilename = null;
if (!empty($_FILES['profile_picture']['name'])) {
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['jpg','jpeg','png','gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        $_SESSION['error'] = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
        header("Location: student_dashboard.php");
        exit();
    }

    if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
        $_SESSION['error'] = "File too large. Max 2MB allowed.";
        header("Location: student_dashboard.php");
        exit();
    }

    $profileFilename = 'profile_' . $userId . '_' . time() . '.' . $ext;
    $dest = $uploadDir . '/' . $profileFilename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $_SESSION['error'] = "Failed to upload profile picture.";
        header("Location: student_dashboard.php");
        exit();
    }
}

// Fetch columns dynamically
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM `students`");
while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];

// Determine column mapping
$nameCol  = in_array('fullname', $cols) ? 'fullname'
           : (in_array('full_name', $cols) ? 'full_name'
           : (in_array('name', $cols) ? 'name' : null));

$emailCol = in_array('email', $cols) ? 'email'
           : (in_array('user_email', $cols) ? 'user_email' : null);

$picCol   = in_array('profile_picture', $cols) ? 'profile_picture'
           : (in_array('avatar', $cols) ? 'avatar'
           : (in_array('profile_pic', $cols) ? 'profile_pic' : null));

if (!$nameCol && !$emailCol && !$picCol) {
    $_SESSION['error'] = "No updatable columns found.";
    header("Location: student_dashboard.php");
    exit();
}

// Build dynamic update query
$fields = [];
$params = [];
$types = '';

if ($nameCol && $fullname !== '') {
    $fields[] = "`$nameCol` = ?";
    $params[] = $fullname;
    $types .= 's';
}

if ($emailCol && $email !== '') {
    $fields[] = "`$emailCol` = ?";
    $params[] = $email;
    $types .= 's';
}

if ($profileFilename && $picCol) {
    $fields[] = "`$picCol` = ?";
    $params[] = $profileFilename;
    $types .= 's';
}

if (empty($fields)) {
    $_SESSION['error'] = "Nothing to update.";
    header("Location: student_dashboard.php");
    exit();
}

$fieldsSql = implode(', ', $fields);
$sql = "UPDATE `students` SET $fieldsSql WHERE `student_id` = ?";
$params[] = $userId;
$types .= 's';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Server error.";
    header("Location: student_dashboard.php");
    exit();
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    $_SESSION['error'] = "Failed to update profile.";
    header("Location: student_dashboard.php");
    exit();
}

// Update session
if ($nameCol && $fullname !== '') $_SESSION['fullname'] = $fullname;
if ($emailCol && $email !== '') $_SESSION['email'] = $email;
if ($profileFilename && $picCol) $_SESSION['profile_picture'] = $profileFilename;

// Success message (can trigger animated toast/snackbar on frontend)
$_SESSION['success'] = "Profile updated successfully.";

header("Location: student_dashboard.php");
exit();
?>
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

// Validations
if (empty($current) || empty($new) || empty($confirm)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: student_dashboard.php#password-section");
    exit();
}

if ($new !== $confirm) {
    $_SESSION['error'] = "Passwords do not match.";
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
} else {
    $_SESSION['error'] = "Error updating password. Try again later.";
}

header("Location: student_dashboard.php#password-section");
exit();
?>
