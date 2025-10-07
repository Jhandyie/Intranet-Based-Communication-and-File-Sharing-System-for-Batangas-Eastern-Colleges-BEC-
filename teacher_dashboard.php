<?php
session_start();
require "db_connection.php";

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "student") {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="logo2.png" alt="BEC Logo" class="logo">
      <h2>BEC</h2>
    </div>
    <ul class="menu">
      <li class="active"><i class="fas fa-home"></i> Dashboard</li>
      <li><i class="fas fa-comment-dots"></i> Messages</li>
      <li><i class="fas fa-chalkboard-teacher"></i> Classes</li>
      <li><i class="fas fa-folder"></i> BEC Drive</li>
      <li><i class="fas fa-bell"></i> Notifications</li>
      <li><i class="fas fa-cog"></i> Settings</li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Topbar -->
    <header class="topbar">
      <div class="user-profile">
        <img src="user.png" alt="User" class="avatar">
        <span class="username">
          <?php echo htmlspecialchars($_SESSION['fullname']); ?> 
        </span>
        <a href="logout.php" class="logout-btn">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </header>

    <!-- Dashboard Cards -->
    <?php
    // Announcements
    $annQuery = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
    ?>
    <div class="card announcements">
      <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
      <?php if ($annQuery->num_rows > 0): ?>
        <ul>
          <?php while($a = $annQuery->fetch_assoc()): ?>
            <li><strong><?= htmlspecialchars($a['title']); ?></strong> - <?= htmlspecialchars($a['content']); ?></li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p>No new announcements.</p>
      <?php endif; ?>
    </div>

    <?php
    // Messages (prepared statement)
    $msgQuery = $conn->prepare("
        SELECT m.body, u.full_name
        FROM messages m
        JOIN users_view u ON m.sender_id = u.id
        WHERE m.receiver_id = ?
        ORDER BY m.sent_at DESC
        LIMIT 5
    ");
    $msgQuery->bind_param("s", $userId);
    $msgQuery->execute();
    $messages = $msgQuery->get_result();
    ?>
    <div class="card messages">
      <h3><i class="fas fa-envelope"></i> New Messages</h3>
      <?php if ($messages->num_rows > 0): ?>
        <ul>
          <?php while($m = $messages->fetch_assoc()): ?>
            <li><strong><?= htmlspecialchars($m['full_name']); ?>:</strong> <?= htmlspecialchars($m['body']); ?></li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p>No messages yet.</p>
      <?php endif; ?>
    </div>

    <?php
    // Files (using users_view)
    $filesQuery = $conn->query("
        SELECT f.file_name, f.uploaded_at, u.full_name 
        FROM files f
        JOIN users_view u ON f.uploaded_by = u.id
        ORDER BY f.uploaded_at DESC
        LIMIT 5
    ");
    ?>
    <div class="card files">
      <h3><i class="fas fa-file-alt"></i> Recent Files Shared</h3>
      <?php if ($filesQuery->num_rows > 0): ?>
        <ul>
          <?php while($f = $filesQuery->fetch_assoc()): ?>
            <li><strong><?= htmlspecialchars($f['file_name']); ?></strong> uploaded by <?= htmlspecialchars($f['full_name']); ?></li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p>No files uploaded yet.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
