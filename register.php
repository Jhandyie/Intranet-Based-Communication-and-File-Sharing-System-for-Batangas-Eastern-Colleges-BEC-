<?php
session_start();
require "db_connection.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role      = $_POST['role'];
    $id_number = trim($_POST['id_number']);
    $email     = trim($_POST['email']);
    $password  = trim($_POST['password']);

    // Table & ID field based on role
    if ($role == "student") {
        $table = "students";
        $id_field = "student_id";
    } elseif ($role == "teacher") {
        $table = "teachers";
        $id_field = "teacher_id";
    } elseif ($role == "admin") {
        $table = "admins";
        $id_field = "admin_id";
    } else {
        $_SESSION['signup_error'] = "Invalid role selected.";
        header("Location: login.php");
        exit();
    }

    // Check if account already exists
    $check = $conn->prepare("SELECT $id_field FROM $table WHERE $id_field=? OR email=?");
    $check->bind_param("ss", $id_number, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['signup_error'] = "Account with this ID or email already exists!";
        header("Location: login.php");
        exit();
    }

    $check->close();

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new account
    $stmt = $conn->prepare("INSERT INTO $table ($id_field, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $id_number, $email, $hashedPassword);

    if ($stmt->execute()) {
        $_SESSION['signup_success'] = "Account created successfully! You can now log in.";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['signup_error'] = "Error creating account: " . $stmt->error;
        header("Location: login.php");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
