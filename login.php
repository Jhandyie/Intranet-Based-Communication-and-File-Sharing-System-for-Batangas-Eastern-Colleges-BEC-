<?php
session_start();
require "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.html");
    exit();
}

$role = trim($_POST['role'] ?? '');
$password = trim($_POST['password'] ?? '');

$map = [
    'student' => ['table' => 'students', 'id_field' => 'student_id', 'id_input' => 'id_number', 'redirect' => 'student_dashboard.php'],
    'teacher' => ['table' => 'teachers', 'id_field' => 'teacher_id', 'id_input' => 'teacher_id', 'redirect' => 'teacher_dashboard.php'],
    'admin'   => ['table' => 'admins',   'id_field' => 'admin_id',   'id_input' => 'id_number', 'redirect' => 'admin_dashboard.php'],
];

if (!isset($map[$role])) {
    $_SESSION['error'] = "Invalid role.";
    header("Location: login.html");
    exit();
}

$id_input_name = $map[$role]['id_input'];
$id_number = trim($_POST[$id_input_name] ?? '');

if ($id_number === '' || $password === '') {
    $_SESSION['error'] = "Please provide credentials.";
    header("Location: login.html");
    exit();
}

$table = $map[$role]['table'];
$id_field = $map[$role]['id_field'];

// Safe dynamic names come from whitelist above
$sql = "SELECT * FROM `$table` WHERE `$id_field` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error'] = "Server error.";
    header("Location: login.html");
    exit();
}
$stmt->bind_param("s", $id_number);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$auth_ok = false;
if ($user && isset($user['password']) && $user['password'] !== '') {
    // Prefer hashed passwords, fallback to plain (not recommended)
    if (password_verify($password, $user['password'])) {
        $auth_ok = true;
    } elseif ($password === $user['password']) {
        // fallback for legacy plaintext passwords (consider migrating to password_hash)
        $auth_ok = true;
    }
}

if ($auth_ok) {
    session_regenerate_id(true);
    // set session values used in dashboard
    $_SESSION['user_id'] = $user[$id_field];
    $_SESSION['role'] = $role;
    $_SESSION['fullname'] = $user['full_name'] ?? ($user['fullname'] ?? '');
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['profile_picture'] = $user['profile_picture'] ?? '';

    // redirect to role-specific dashboard
    $redirect = $map[$role]['redirect'];
    header("Location: " . $redirect);
    exit();
} else {
    $_SESSION['error'] = "Invalid credentials.";
    header("Location: login.html");
    exit();
}
?>
