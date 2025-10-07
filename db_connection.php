<?php
$servername = "localhost";
$username = "root";   // default in XAMPP
$password = "12345";  // your MySQL password
$dbname = "bec_intranet";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
