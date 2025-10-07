<?php
session_start();
require "db_connection.php"; // Path to db_connection.php relative to this file

// Security check
if(!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role']; // Get user role from session

// --- PHP API Logic ---
if (isset($_GET['action']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Security check for all API actions
    if (!$userId || !$userRole) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $action = $_GET['action'] ?? '';

    // --- Handle POST requests (Send new message, Start new chat) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Send new message
        if (isset($data['conversation_id']) && isset($data['message'])) {
            $msg = $data['message'];
            $conv = $data['conversation_id'];

            // Validate user is a member of the conversation before sending
            $checkMemberStmt = $conn->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND role = ?");
            $checkMemberStmt->bind_param("iss", $conv, $userId, $userRole);
            $checkMemberStmt->execute();
            $checkMemberResult = $checkMemberStmt->get_result();
            if ($checkMemberResult->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Forbidden: Not a member of this conversation']);
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $conv, $userId, $userRole, $msg);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'ok', 'message_id' => $conn->insert_id]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to send message: ' . $stmt->error]);
            }
            exit();
        }

        // Start new chat (1-on-1)
        if (isset($data['new_chat_user'])) {
            $otherId = $data['new_chat_user'];
            $otherRole = $data['new_chat_user_role'] ?? 'student'; // Assuming role is passed or defaults

            // Prevent chatting with self
            if ($userId === $otherId && $userRole === $otherRole) { // Added role check
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Cannot start chat with yourself.']);
                exit();
            }

            // Check if conversation already exists between these two users (for 1-on-1)
            // This query needs to be robust for different user roles and IDs
            $stmt = $conn->prepare("
                SELECT c.id FROM conversation c
                JOIN conversation_members cm1 ON c.id = cm1.conversation_id
                JOIN conversation_members cm2 ON c.id = cm2.conversation_id
                WHERE c.is_group=0
                  AND cm1.user_id = ? AND cm1.role = ?
                  AND cm2.user_id = ? AND cm2.role = ?
                  AND c.id IN (SELECT conversation_id FROM conversation_members WHERE user_id = ? AND role = ?)
                  AND c.id IN (SELECT conversation_id FROM conversation_members WHERE user_id = ? AND role = ?)
                GROUP BY c.id
                HAVING COUNT(DISTINCT cm1.user_id) = 1 AND COUNT(DISTINCT cm2.user_id) = 1 AND COUNT(cm.conversation_id) = 2
            ");
            $stmt->bind_param("ssssssss", $userId, $userRole, $otherId, $otherRole, $userId, $userRole, $otherId, $otherRole);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                echo json_encode(['status' => 'exists', 'conversation_id' => $res->fetch_assoc()['id']]);
                exit();
            }

            // Create new conversation
            $conn->query("INSERT INTO conversation (is_group) VALUES (0)");
            $convId = $conn->insert_id;
            if (!$convId) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to create conversation.']);
                exit();
            }

            // Add current user to conversation
            $stmt1 = $conn->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, ?)");
            $stmt1->bind_param("iss", $convId, $userId, $userRole);
            $stmt1->execute();

            // Add other user to conversation
            $stmt2 = $conn->prepare("INSERT INTO conversation_members (conversation_id, user_id, role) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $convId, $otherId, $otherRole);
            $stmt2->execute();

            if ($stmt1->affected_rows > 0 && $stmt2->affected_rows > 0) {
                echo json_encode(['status' => 'ok', 'conversation_id' => $convId]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to add members to conversation.']);
            }
            exit();
        }
    }

    // --- Handle GET requests (Fetch messages, Fetch conversations, Fetch users) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Fetch messages for a conversation (updated to include profile_picture)
        if ($action === 'get_messages' && isset($_GET['conversation_id'])) {
            $convId = $_GET['conversation_id'];

            // Validate user is a member of the conversation
            $checkMemberStmt = $conn->prepare("SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ? AND role = ?");
            $checkMemberStmt->bind_param("iss", $convId, $userId, $userRole);
            $checkMemberStmt->execute();
            $checkMemberResult = $checkMemberStmt->get_result();
            if ($checkMemberResult->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Forbidden: Not a member of this conversation']);
                exit();
            }

            // Updated query: Include uv.profile_picture for avatars
            $stmt = $conn->prepare("
                SELECT m.id, m.message, m.created_at, m.sender_id, m.sender_role, uv.full_name, uv.profile_picture
                FROM messages m
                JOIN users_view uv ON m.sender_id = uv.id AND m.sender_role = uv.role_type
                WHERE m.conversation_id=?
                ORDER BY m.created_at ASC
            ");
            $stmt->bind_param("i", $convId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($res);
            exit();
        }

        // Fetch all conversations of this user
        if ($action === 'get_conversations') {
            $stmt = $conn->prepare("
                SELECT c.id, c.name, c.is_group,
                       GROUP_CONCAT(DISTINCT uv.full_name ORDER BY uv.full_name SEPARATOR ', ') AS members_names
                FROM conversation c
                JOIN conversation_members cm ON c.id = cm.conversation_id
                JOIN users_view uv ON cm.user_id = uv.id AND cm.role = uv.role_type
                WHERE c.id IN (SELECT conversation_id FROM conversation_members WHERE user_id = ? AND role = ?)
                GROUP BY c.id, c.name, c.is_group
                ORDER BY c.created_at DESC
            ");
            $stmt->bind_param("ss", $userId, $userRole);
            $stmt->execute();
            $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // For 1-on-1 chats, if 'name' is null, set it to the other participant's name
            foreach ($conversations as &$conv) {
                if (!$conv['is_group'] && empty($conv['name'])) {
                    $participantNames = explode(', ', $conv['members_names']);
                    $otherParticipant = '';
                    foreach ($participantNames as $pName) {
                        if ($pName !== ($_SESSION['fullname'] ?? '')) { // Assuming $_SESSION['fullname'] is set
                            $otherParticipant = $pName;
                            break;
                        }
                    }
                    $conv['name'] = $otherParticipant ?: 'Private Chat'; // Fallback
                }
            }
            unset($conv); // Break the reference

            echo json_encode($conversations);
            exit();
        }

        // Fetch all users (for new chat)
        if ($action === 'get_users_for_chat') {
            // Assuming 'users_view' provides id, full_name, role_type, profile_picture
            // Exclude the current user from the list
            $stmt = $conn->prepare("SELECT id, full_name, role_type FROM users_view WHERE id != ? OR role_type != ?");
            $stmt->bind_param("ss", $userId, $userRole);
            $stmt->execute();
            $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            echo json_encode($users);
            exit();
        }
    }

    // If no action matched
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or action not specified.']);
    exit();
}

// --- HTML and JavaScript for the Messaging Interface (if not an API request) ---

// Fetch current user's profile picture for display in the sidebar
$profilePic = 'user.png'; // Default
if ($userRole === 'student') {
    $profileQuery = $conn->prepare("SELECT profile_picture FROM students WHERE student_id = ?");
    $profileQuery->bind_param("s", $userId);
    $profileQuery->execute();
    $student = $profileQuery->get_result()->fetch_assoc();
    if (!empty($student['profile_picture'])) {
        $candidate = __DIR__ . '/uploads/' . $student['profile_picture'];
        if (file_exists($candidate)) {
            $profilePic = 'uploads/' . htmlspecialchars($student['profile_picture']);
        }
    }
}
// Add similar logic for teacher/admin profile pictures if needed
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages - BEC Intranet</title>
<link rel="stylesheet" href="dashboard.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Your existing styles */
.messages-container { display: flex; gap: 20px; margin-top:20px; }
.conversations { width: 30%; background: var(--card-bg); border-radius:12px; padding:15px; }
.conversations ul { list-style:none; padding:0; }
.conversations li { padding:10px; cursor:pointer; border-radius:8px; transition:0.2s; margin-bottom: 5px; display: flex; align-items: center; }
.conversations li.active, .conversations li:hover { background: var(--sidebar-active); }
.new-chat { margin-top:15px; padding-top: 15px; border-top: 1px solid #eee; }
.new-chat select, .new-chat button { padding:8px; border-radius:6px; border:1px solid #ccc; margin-right:5px; }
.new-chat button { background: var(--btn-bg); color: var(--btn-text); cursor: pointer; }
.new-chat button:hover { background: var(--btn-hover-bg); color: var(--btn-hover-text); }

.chat-box { flex:1; display:flex; flex-direction:column; }
#chatMessages { height:400px; overflow-y:auto; background:var(--card-bg); padding:15px; border-radius:12px; margin-bottom:10px; display: flex; flex-direction: column; }
#chatInput { display: flex; }
#chatInput input { flex-grow: 1; padding:8px; border-radius:6px; border:1px solid #ccc; margin-right:5px; }
#chatInput button { padding:8px 12px; border-radius:6px; border:none; background:var(--btn-bg); color:var(--btn-text); cursor: pointer; }
#chatInput button:hover { background: var(--btn-hover-bg); color: var(--btn-hover-text); }

/* Message bubble styles */
.message-bubble {
    background-color: #e0e0e0;
    padding: 8px 12px;
    border-radius: 15px;
    margin-bottom: 10px;
    max-width: 70%;
    word-wrap: break-word;
    align-self: flex-start; /* Default for received messages */
    display: flex;
    flex-direction: column;
}
.message-bubble.sent {
    background-color: var(--btn-bg); /* Maroon */
    color: white;
    align-self: flex-end;
}
.message-sender {
    font-size: 0.8em;
    color: #666;
    margin-bottom: 3px;
}
.message-bubble.sent .message-sender {
    color: #ddd; /* Lighter color for sender name in sent messages */
}

.message-timestamp {
    text-align: right;
    margin-top: 3px;
    font-size: 0.7em;
    color: #999;
}
.message-bubble.sent .message-timestamp {
    color: #ccc;
}

.message-content {
    margin-top: 5px;
}

.message-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 10px;
    vertical-align: middle;
}
.message-header {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}
.message-bubble.sent .message-header {
    justify-content: flex-end;
}
.message-bubble.received .message-header {
    justify-content: flex-start;
}
</style>
</head>
<body>

<!-- Sidebar (copied from student_dashboard.php) -->
<aside class="sidebar">
  <div class="sidebar-header">
    <img src="assets image/logo2.png" alt="BEC Logo" class="logo">
    <h2>BEC</h2>
  </div>
  <ul class="menu">
    <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
    <li class="active"><a href="messages.php"><i class="fas fa-comment-dots"></i> Messages</a></li>
    <li><i class="fas fa-chalkboard-teacher"></i> Classes</li>
    <li><i class="fas fa-folder"></i> BEC Drive</li>
    <li><i class="fas fa-bell"></i> Notifications</li>
    <li id="sidebar-profile"><i class="fas fa-user"></i> Profile</li>
    <li><a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
  </ul>
</aside>

<main class="main-content">
<h2>Messages</h2>
<div class="messages-container">

  <!-- Conversations List -->
  <div class="conversations">
    <h3>Your Conversations</h3>
    <ul id="conversationList">
      <!-- Conversations will be loaded here by JavaScript -->
    </ul>

    <!-- New Chat -->
    <div class="new-chat">
      <h4>Start New Chat</h4>
      <select id="newUserSelect">
        <option value="">Select user to chat</option>
        <!-- Users will be loaded here by JavaScript -->
      </select>
      <button id="startChatBtn">Start Chat</button>
    </div>
  </div>

  <!-- Chat Box -->
  <div class="chat-box">
    <h3 id="chatTitle">Select a conversation to view messages</h3>
    <div id="chatMessages"></div>
    <div id="chatInput" style="display:none;">
      <input type="text" id="messageText" placeholder="Type a message...">
      <button id="sendMessageBtn">Send</button>
    </div>
  </div>
</div>
</main>

<script>
// Active conversation
let currentConversationId = null;
let currentConversationTitle = '';
const API_URL = 'http://localhost/Intranet-Based%20Com%20Systems/messages.php';  // e.g., 'http://localhost/bec/messages.php'

const conversationList = document.getElementById('conversationList');
const chatTitle = document.getElementById('chatTitle');
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const messageText = document.getElementById('messageText');
const sendMessageBtn = document.getElementById('sendMessageBtn');
const newUserSelect = document.getElementById('newUserSelect');
const startChatBtn = document.getElementById('startChatBtn');

const currentUserId = <?= json_encode($userId) ?>;
const currentUserFullname = <?= json_encode($_SESSION['fullname'] ?? 'You') ?>;
const currentUserProfilePic = <?= json_encode($profilePic) ?>; // Use the fetched profile pic

// --- Functions to Load Data ---

async function fetchConversations() {
    try {
        const response = await fetch(`${API_URL}?action=get_conversations`);
        const data = await response.json();
        if (response.ok) {
            conversationList.innerHTML = ''; // Clear existing list
            data.forEach(conv => {
                const li = document.createElement('li');
                li.dataset.id = conv.id;
                li.textContent = conv.name || conv.members_names; // Use name if group, else members
                li.addEventListener('click', () => loadMessages(conv.id, conv.name || conv.members_names));
                conversationList.appendChild(li);
            });
        } else {
            console.error('Error fetching conversations:', data.message);
        }
    } catch (error) {
        console.error('Network error fetching conversations:', error);
    }
}

async function fetchUsersForChat() {
    try {
        const response = await fetch(`${API_URL}?action=get_users_for_chat`);
        const data = await response.json();
        if (response.ok) {
            newUserSelect.innerHTML = '<option value="">Select user to chat</option>'; // Reset
            data.forEach(user => {
                // Exclude current user from the list
                if (!(user.id === currentUserId && user.role_type === '<?= $userRole ?>')) {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.dataset.role = user.role_type; // Store role for new chat
                    option.textContent = `${user.full_name} (${user.role_type})`;
                    newUserSelect.appendChild(option);
                }
            });
        } else {
            console.error('Error fetching users:', data.message);
        }
    } catch (error) {
        console.error('Network error fetching users:', error);
    }
}

async function loadMessages(convId, title) {
    currentConversationId = convId;
    currentConversationTitle = title;
    chatTitle.innerText = title;
    chatInput.style.display = 'flex'; // Show input box

    // Highlight active conversation in list
    document.querySelectorAll('#conversationList li').forEach(li => {
        li.classList.remove('active');
        if (li.dataset.id == convId) {
            li.classList.add('active');
        }
    });

    try {
        const response = await fetch(`${API_URL}?action=get_messages&conversation_id=${convId}`);
        const data = await response.json();
        if (response.ok) {
            chatMessages.innerHTML = ''; // Clear existing messages
            data.forEach(m => {
                const messageElement = document.createElement('div');
                messageElement.classList.add('message-bubble');
                const isSent = (m.sender_id === currentUserId && m.sender_role === '<?= $userRole ?>');

                if (isSent) {
                    messageElement.classList.add('sent');
                } else {
                    messageElement.classList.add('received');
                }

                const messageHeader = document.createElement('div');
                messageHeader.classList.add('message-header');

                // Add avatar for received messages
                if (!isSent) {
                    const avatar = document.createElement('img');
                    avatar.classList.add('message-avatar');
                    avatar.src = m.profile_picture && m.profile_picture !== 'default.png' ? `uploads/${m.profile_picture}` : 'user.png';
                    avatar.alt = m.full_name;
                    messageHeader.appendChild(avatar);
                }

                const senderName = document.createElement('div');
                senderName.classList.add('message-sender');
                senderName.textContent = isSent ? 'You' : m.full_name;
                messageHeader.appendChild(senderName);

                messageElement.appendChild(messageHeader);

                const messageContent = document.createElement('div');
                messageContent.classList.add('message-content');
                messageContent.textContent = m.message;
                messageElement.appendChild(messageContent);

                const timestamp = document.createElement('div');
                timestamp.classList.add('message-timestamp');
                timestamp.textContent = new Date(m.created_at).toLocaleString(); // Format date/time
                messageElement.appendChild(timestamp);

                chatMessages.appendChild(messageElement);
            });
            chatMessages.scrollTop = chatMessages.scrollHeight; // Scroll to bottom
        } else {
            console.error('Error fetching messages:', data.message);
            chatMessages.innerHTML = `<p style="color: red;">Error loading messages: ${data.message}</p>`;
        }
    } catch (error) {
        console.error('Network error fetching messages:', error);
        chatMessages.innerHTML = `<p style="color: red;">Network error loading messages.</p>`;
    }
}

// --- Event Listeners ---

sendMessageBtn.addEventListener('click', async () => {
    const msg = messageText.value.trim();
    if (!msg || !currentConversationId) return;

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversation_id: currentConversationId, message: msg })
        });
        const data = await response.json();
        if (response.ok && data.status === 'ok') {
            messageText.value = ''; // Clear input
            loadMessages(currentConversationId, currentConversationTitle); // Reload messages
        } else {
            console.error('Error sending message:', data.message);
            alert('Failed to send message: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Network error sending message:', error);
        alert('Network error. Could not send message.');
    }
});

messageText.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault(); // Prevent new line in textarea
        sendMessageBtn.click(); // Trigger send button click
    }
});

startChatBtn.addEventListener('click', async () => {
    const selectedOption = newUserSelect.options[newUserSelect.selectedIndex];
    const newUserId = selectedOption.value;
    const newUserRole = selectedOption.dataset.role;

    if (!newUserId || !newUserRole) {
        alert('Please select a user to start a chat.');
        return;
    }

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ new_chat_user: newUserId, new_chat_user_role: newUserRole })
        });
        const data = await response.json();
        if (response.ok && data.status === 'ok') {
            alert('New chat started!');
            newUserSelect.value = ''; // Reset select
            await fetchConversations(); // Refresh conversation list
            loadMessages(data.conversation_id, selectedOption.textContent); // Load the new chat
        } else if (response.ok && data.status === 'exists') {
            alert('Conversation with this user already exists!');
            newUserSelect.value = ''; // Reset select
            await fetchConversations(); // Refresh conversation list
            loadMessages(data.conversation_id, selectedOption.textContent); // Load the existing chat
        }
        else {
            console.error('Error starting chat:', data.message);
            alert('Failed to start chat: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Network error starting chat:', error);
        alert('Network error. Could not start chat.');
    }
});

// --- Initial Load ---
document.addEventListener('DOMContentLoaded', () => {
    fetchConversations();
    fetchUsersForChat();
});

// Optional: Polling for new messages (for basic real-time feel without WebSockets)
// setInterval(() => {
//     if (currentConversationId) {
//         loadMessages(currentConversationId, currentConversationTitle);
//     }
// }, 5000); // Poll every 5 seconds
</script>
</body>
</html>