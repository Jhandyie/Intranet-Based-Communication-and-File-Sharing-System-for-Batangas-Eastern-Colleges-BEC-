<?php
session_start();
require "db_connection.php";

// Security check: Redirect to login if not authenticated as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== "student") {
    header("Location: login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch student profile (updated to include fullname)
$profileQuery = $conn->prepare("SELECT fullname, profile_picture, email FROM students WHERE student_id = ?");
$profileQuery->bind_param("s", $userId);
$profileQuery->execute();
$student = $profileQuery->get_result()->fetch_assoc();

// Set session fullname if not already set (fallback from DB)
if (!isset($_SESSION['fullname']) && $student) {
    $_SESSION['fullname'] = $student['fullname'] ?? 'Student';
}

// Determine profile picture path safely
$profilePic = 'user.png';
if (!empty($student['profile_picture']) && $student) {
    $candidate = __DIR__ . '/uploads/' . $student['profile_picture'];
    if (file_exists($candidate)) {
        $profilePic = 'uploads/' . htmlspecialchars($student['profile_picture']);
    }
}

// Fetch announcements
$annQuery = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");

// Fetch recent files (assuming 'users' here is a legacy table; update to users_view if needed)
$filesQuery = $conn->query("
    SELECT f.file_name, f.uploaded_at, u.full_name 
    FROM files f
    JOIN users_view u ON f.uploaded_by = u.id  -- Updated to users_view if applicable
    ORDER BY f.uploaded_at DESC
    LIMIT 5
");

// Fetch conversations and latest messages for "New Messages" card (updated query for users_view)
$conversations = [];
if (isset($userId)) {
    $convQuery = $conn->prepare("
        SELECT c.id, c.name, c.is_group
        FROM conversation_members cm
        JOIN conversation c ON cm.conversation_id = c.id
        WHERE cm.user_id = ? AND cm.role = 'student'
        ORDER BY c.id DESC
        LIMIT 5
    ");
    $convQuery->bind_param("s", $userId);
    $convQuery->execute();
    $convResult = $convQuery->get_result();
    
    while ($conv = $convResult->fetch_assoc()) {
        // Fetch latest message with users_view JOIN (fixed for varchar ID and role)
        $msgQuery = $conn->prepare("
            SELECT m.message, m.created_at, u.full_name AS sender_name
            FROM messages m
            JOIN users_view u ON m.sender_id = u.id AND m.sender_role = u.role_type
            WHERE m.conversation_id = ?
            ORDER BY m.created_at DESC
            LIMIT 1
        ");
        $msgQuery->bind_param("i", $conv['id']);
        $msgQuery->execute();
        $latestMsg = $msgQuery->get_result()->fetch_assoc();
        
        $conversations[] = [
            'id' => $conv['id'],
            'name' => $conv['name'] ?? 'Unnamed Chat',
            'latest_message' => $latestMsg['message'] ?? 'No messages yet',
            'sender_name' => $latestMsg['sender_name'] ?? 'Unknown'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BEC Intranet - Student Dashboard</title>
  <link rel="stylesheet" href="dashboard.css">
  <link rel="stylesheet" href="profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <img src="assets image/logo2.png" alt="BEC Logo" class="logo">
      <h2>BEC</h2>
    </div>
    <ul class="menu">
      <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="messages.php"><i class="fas fa-comment-dots"></i> Messages</a></li> <!-- Updated link -->
      <li><i class="fas fa-chalkboard-teacher"></i> Classes</li>
      <li><i class="fas fa-folder"></i> BEC Drive</li>
      <li><i class="fas fa-bell"></i> Notifications</li>
      <li id="sidebar-profile"><i class="fas fa-user"></i> Profile</li>
      <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Topbar -->
    <header class="topbar">
      <button id="theme-toggle" class="theme-btn"><i class="fas fa-moon"></i></button>
      <div class="user-profile" id="profile-trigger">
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="User " class="avatar">
        <span class="username"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></span>
      </div>
    </header>

    <!-- Welcome Section -->
    <div class="welcome-section">
      <h1>Welcome, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?>!</h1>
      <p id="userFullname"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></p>
    </div>

    <!-- Dashboard Cards -->
    <section class="dashboard-cards">
      <!-- Announcements -->
      <div class="card announcements">
        <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
        <?php if ($annQuery && $annQuery->num_rows > 0): ?>
          <ul>
            <?php while ($a = $annQuery->fetch_assoc()): ?>
              <li><strong><?= htmlspecialchars($a['title']) ?>:</strong> <?= htmlspecialchars($a['content']) ?></li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <p>No new announcements.</p>
        <?php endif; ?>
      </div>

      <!-- New Messages (Consolidated - No Duplication) -->
      <div class="card messages">
        <h3><i class="fas fa-envelope"></i> New Messages</h3>
        <?php if (!empty($conversations)): ?>
          <ul>
            <?php foreach ($conversations as $conv): ?>
              <li>
                <strong><?= htmlspecialchars($conv['sender_name']) ?>:</strong>
                <?= htmlspecialchars($conv['latest_message']) ?>
                <?php if ($conv['name']): ?>
                  <small>(<?= htmlspecialchars($conv['name']) ?>)</small>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p>No messages yet. <a href="messages.php">Start chatting!</a></p>
        <?php endif; ?>
      </div>

      <!-- Recent Files -->
      <div class="card files">
        <h3><i class="fas fa-file-alt"></i> Recent Files Shared</h3>
        <?php if ($filesQuery && $filesQuery->num_rows > 0): ?>
          <ul>
            <?php while ($f = $filesQuery->fetch_assoc()): ?>
              <li><strong><?= htmlspecialchars($f['file_name']) ?></strong> uploaded by <?= htmlspecialchars($f['full_name']) ?> on <?= date('M j, Y', strtotime($f['uploaded_at'])) ?></li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <p>No files uploaded yet.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <!-- Profile Modal -->
  <div id="profileModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeProfile">&times;</span>
      <h2>Profile</h2>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab-btn active" data-tab="infoTab">Profile Info</button>
        <button class="tab-btn" data-tab="passwordTab">Change Password</button>
      </div>

      <!-- Profile Info Form -->
      <div class="tab-content active" id="infoTab">
        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
          <label>Full Name</label>
          <input type="text" name="fullname" value="<?= htmlspecialchars($student['fullname'] ?? $_SESSION['fullname']) ?>" required>

          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>

          <label>Profile Picture</label>
          <input type="file" name="profile_picture" accept="image/*">

          <div class="profile-preview">
            <img src="<?= htmlspecialchars($profilePic) ?>" 
                 alt="Profile Picture" class="avatar" style="width:80px; height:80px; border-radius:50%; margin-top:10px;">
          </div>

          <button type="submit" class="btn-save">Update Profile</button>
        </form>
      </div>

      <!-- Change Password Form -->
      <div class="tab-content" id="passwordTab">
        <form action="update_password.php" method="POST">
          <label>Current Password</label>
          <input type="password" name="current_password" required>

          <label>New Password</label>
          <input type="password" name="new_password" required>

          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" required>

          <button type="submit" class="btn-save">Update Password</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    const body = document.body;
    const toggleBtn = document.getElementById("theme-toggle");
    const profileModal = document.getElementById("profileModal");
    const profileTrigger = document.getElementById("profile-trigger");
    const closeProfile = document.getElementById("closeProfile");

    // Initialize theme from localStorage
    if (localStorage.getItem("theme") === "dark") {
      body.classList.add("dark");
      toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
      toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
    }

    toggleBtn.addEventListener("click", () => {
      body.classList.toggle("dark");
      if (body.classList.contains("dark")) {
        localStorage.setItem("theme", "dark");
        toggleBtn.innerHTML = '<i class="fas fa-sun"></i>';
      } else {
        localStorage.setItem("theme", "light");
        toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
      }
    });

    // Profile modal handlers
    if (profileTrigger) {
      profileTrigger.addEventListener("click", () => {
        profileModal.style.display = "flex";
      });
    }
    if (closeProfile) {
      closeProfile.addEventListener("click", () => {
        profileModal.style.display = "none";
      });
    }
    window.addEventListener("click", e => {
      if (e.target === profileModal) profileModal.style.display = "none";
    });
    document.getElementById("sidebar-profile")?.addEventListener("click", () => {
      profileModal.style.display = "flex";
    });

    // Tab functionality for profile modal (if needed)
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.add('active');
      });
    });
  </script>
</body>
</html>