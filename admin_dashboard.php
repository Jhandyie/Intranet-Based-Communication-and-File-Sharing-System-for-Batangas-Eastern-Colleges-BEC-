<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin") {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="logo2.png" alt="BEC Logo" class="logo">
      <h2>Admin Panel</h2>
    </div>
    <ul class="menu">
      <li class="active"><i class="fas fa-home"></i> Dashboard</li>
      <li><i class="fas fa-user-shield"></i> User Management</li>
      <li><i class="fas fa-database"></i> System Logs</li>
      <li><i class="fas fa-cogs"></i> Settings</li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <header class="topbar">
      <div class="user-profile">
        <img src="user.png" alt="User" class="avatar">
        <span class="username"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </header>

    <section class="dashboard-cards">
      <div class="card">
        <h3><i class="fas fa-user-plus"></i> Manage Users</h3>
        <p>View, edit, or remove users.</p>
      </div>
      <div class="card">
        <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
        <p>Configure system security.</p>
      </div>
      <div class="card">
        <h3><i class="fas fa-chart-line"></i> System Reports</h3>
        <p>No reports available.</p>
      </div>
    </section>
  </main>
</body>
</html>

