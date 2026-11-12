<?php
require_once 'inc/auth.php';

if (!logged_in()) { header("Location: login.php"); exit; }

$app_id = (int)($_GET['app_id'] ?? 0);
$my_uid = (int)$_SESSION['uid'];
$my_role = role();
$title = "Chat — " . SITE_NAME;

if (!$app_id) { header("Location: employer.php"); exit; }

// ============ AUTO-CREATE NEEDED TABLES/COLUMNS ============
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            receiver_id INT NOT NULL,
            application_id INT NOT NULL,
            message TEXT NOT NULL,
            file_path VARCHAR(255) NULL,
            file_type VARCHAR(30) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_app (application_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add missing columns if table existed
    foreach (['file_path' => "VARCHAR(255) NULL", 'file_type' => "VARCHAR(30) NULL", 'is_read' => "TINYINT(1) NOT NULL DEFAULT 0"] as $col => $def) {
        if (!column_exists($db, 'messages', $col)) {
            try { $db->query("ALTER TABLE messages ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
        }
    }
} catch (Throwable $e) {}

// ============ FETCH APPLICATION ============
$stmt = $db->prepare("
    SELECT a.*, j.title AS job_title, j.employer_id, j.id AS job_id,
           su.name AS seeker_name, su.profile_photo AS seeker_photo,
           eu.name AS employer_name, eu.company_name, eu.profile_photo AS employer_photo
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users su ON a.seeker_id = su.id
    JOIN users eu ON j.employer_id = eu.id
    WHERE a.id = ?
");
$stmt->bind_param('i', $app_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) { die("Chat not found."); }

// Security: Only seeker or employer of this application
if ($my_uid != $app['seeker_id'] && $my_uid != $app['employer_id']) {
    die("Unauthorized access.");
}

$receiver_id = ($my_uid == $app['seeker_id']) ? $app['employer_id'] : $app['seeker_id'];
$other_name = ($my_uid == $app['seeker_id']) 
    ? ($app['company_name'] ?: $app['employer_name'])
    : $app['seeker_name'];
$other_photo = ($my_uid == $app['seeker_id']) ? $app['employer_photo'] : $app['seeker_photo'];
$my_photo = ($my_uid == $app['seeker_id']) ? $app['seeker_photo'] : $app['employer_photo'];

// ============ HANDLE AJAX: SEND MESSAGE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_msg') {
    header('Content-Type: application/json');
    
    $msg = trim($_POST['message'] ?? '');
    $file_path = null;
    $file_type = null;
    
    // File upload
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_img = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowed_doc = ['pdf', 'doc', 'docx'];
        $max_size = 2 * 1024 * 1024;
        
        if (in_array($ext, $allowed_img) || in_array($ext, $allowed_doc)) {
            if ($_FILES['attachment']['size'] <= $max_size) {
                $dir = __DIR__ . '/storage/chat';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = 'chat_' . $app_id . '_' . $my_uid . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $filename)) {
                    $file_path = 'storage/chat/' . $filename;
                    $file_type = in_array($ext, $allowed_img) ? 'image' : 'document';
                }
            }
        }
    }
    
    if ($msg === '' && $file_path === null) {
        echo json_encode(['ok' => false, 'msg' => 'Message is empty']);
        exit;
    }
    
    if (strlen($msg) > 500) {
        echo json_encode(['ok' => false, 'msg' => 'Message too long (max 500 chars)']);
        exit;
    }
    
    $ins = $db->prepare("INSERT INTO messages (sender_id, receiver_id, application_id, message, file_path, file_type) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->bind_param('iiisss', $my_uid, $receiver_id, $app_id, $msg, $file_path, $file_type);
    
    if ($ins->execute()) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => $db->error]);
    }
    exit;
}

// ============ HANDLE AJAX: GET MESSAGES ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'get_msgs') {
    // Mark received messages as read
    $db->query("UPDATE messages SET is_read = 1 WHERE application_id = $app_id AND receiver_id = $my_uid AND is_read = 0");
    
    $msgs = $db->prepare("
        SELECT m.*, u.name AS sender_name, u.profile_photo AS sender_photo
        FROM messages m 
        JOIN users u ON m.sender_id = u.id
        WHERE m.application_id = ? 
        ORDER BY m.created_at ASC
    ");
    $msgs->bind_param('i', $app_id);
    $msgs->execute();
    $res = $msgs->get_result();
    
    $output = '';
    $last_date = '';
    while ($m = $res->fetch_assoc()) {
        $is_me = ($m['sender_id'] == $my_uid);
        $bubble_class = $is_me ? 'my-msg' : 'their-msg';
        $time = date('h:i A', strtotime($m['created_at']));
        $date = date('d M Y', strtotime($m['created_at']));
        
        // Date separator
        if ($date !== $last_date) {
            $output .= "<div class='chat-date-sep'><span>$date</span></div>";
            $last_date = $date;
        }
        
        $avatar_initial = strtoupper(substr($m['sender_name'], 0, 1));
        $avatar_html = !empty($m['sender_photo']) 
            ? '<img src="' . h($m['sender_photo']) . '" alt="">'
            : $avatar_initial;
        
        $output .= "<div class='chat-bubble-wrap $bubble_class'>";
        
        if (!$is_me) {
            $output .= "<div class='chat-avatar-mini'>$avatar_html</div>";
        }
        
        $output .= "<div class='chat-bubble'>";
        
        // File attachment
        if (!empty($m['file_path'])) {
            if ($m['file_type'] === 'image') {
                $output .= "<div class='chat-img-wrap'><img src='" . h($m['file_path']) . "' alt='attachment' onclick=\"window.open(this.src, '_blank')\"></div>";
            } else {
                $output .= "<a href='" . h($m['file_path']) . "' target='_blank' class='chat-doc-link'><i class='fas fa-file-pdf'></i> View Document</a>";
            }
        }
        
        // Text message
        if (!empty($m['message'])) {
            $output .= "<div class='msg-text'>" . nl2br(h($m['message'])) . "</div>";
        }
        
        $output .= "<div class='msg-time'>$time";
        if ($is_me) {
            $output .= " <i class='fas fa-check" . ($m['is_read'] ? '-double' : '') . "' style='" . ($m['is_read'] ? 'color:#60a5fa;' : 'opacity:0.6;') . "'></i>";
        }
        $output .= "</div></div></div>";
    }
    
    if ($output === '') {
        $output = '<div class="chat-empty"><i class="fas fa-comments" style="font-size:2.5rem; color:#d1d5db; margin-bottom:10px; display:block;"></i><p style="color:#9ca3af; margin:0;">No messages yet. Say hello! 👋</p></div>';
    }
    
    echo $output;
    exit;
}

include 'inc/head.php';
?>

<style>
.chat-page-wrap { background: #f4f7fc; padding: 24px; margin: -20px; min-height: calc(100vh - 80px); }
.chat-page-wrap * { box-sizing: border-box; }

.chat-header-bar {
    background: #fff; border-radius: 12px; padding: 16px 20px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 16px; flex-wrap: wrap; gap: 12px;
    border: 1px solid #e5e7eb;
}
.chat-header-info { display: flex; align-items: center; gap: 12px; }
.chat-header-avatar { width: 44px; height: 44px; border-radius: 50%; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; overflow: hidden; flex-shrink: 0; }
.chat-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
.chat-header-info h2 { font-size: 1.05rem; margin: 0 0 3px; color: #111827; }
.chat-header-info p { font-size: 0.8rem; margin: 0; color: #6b7280; }

.chat-container {
    background: #fff; border-radius: 12px;
    border: 1px solid #e5e7eb; display: flex;
    flex-direction: column; height: calc(100vh - 240px);
    min-height: 400px; max-height: 700px; overflow: hidden;
}

.chat-messages {
    flex: 1; padding: 20px; overflow-y: auto;
    background: #f9fafb; display: flex; flex-direction: column;
    gap: 8px;
}
.chat-messages::-webkit-scrollbar { width: 6px; }
.chat-messages::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }

.chat-empty { text-align: center; padding: 60px 20px; }

.chat-date-sep { text-align: center; margin: 12px 0; position: relative; }
.chat-date-sep span { background: #fff; padding: 3px 12px; border-radius: 10px; font-size: 0.7rem; color: #9ca3af; font-weight: 600; border: 1px solid #e5e7eb; }

.chat-bubble-wrap { display: flex; gap: 8px; align-items: flex-end; max-width: 80%; }
.chat-bubble-wrap.my-msg { align-self: flex-end; flex-direction: row-reverse; }
.chat-bubble-wrap.their-msg { align-self: flex-start; }

.chat-avatar-mini { width: 30px; height: 30px; border-radius: 50%; background: #e0e7ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0; overflow: hidden; }
.chat-avatar-mini img { width: 100%; height: 100%; object-fit: cover; }

.chat-bubble { padding: 10px 14px; border-radius: 14px; font-size: 0.9rem; line-height: 1.5; word-wrap: break-word; }
.my-msg .chat-bubble { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-bottom-right-radius: 4px; }
.their-msg .chat-bubble { background: #fff; color: #374151; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #f3f4f6; }

.msg-text { white-space: pre-wrap; word-break: break-word; }
.msg-time { font-size: 0.68rem; margin-top: 4px; opacity: 0.75; text-align: right; display: flex; align-items: center; gap: 4px; justify-content: flex-end; }

.chat-img-wrap { margin: 4px 0; }
.chat-img-wrap img { max-width: 200px; max-height: 200px; border-radius: 8px; cursor: pointer; }

.chat-doc-link { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.15); padding: 6px 10px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; margin: 4px 0; color: inherit; }
.my-msg .chat-doc-link { color: #fff; background: rgba(255,255,255,0.2); }
.their-msg .chat-doc-link { color: #6366f1; background: #eff6ff; }

.chat-input-area {
    padding: 14px 16px; background: #fff;
    border-top: 1px solid #f3f4f6;
}
.chat-input-row { display: flex; gap: 8px; align-items: flex-end; }
.chat-input-row input[type="text"] {
    flex: 1; padding: 10px 14px; border: 1.5px solid #d1d5db;
    border-radius: 24px; outline: none; font-size: 0.9rem;
    font-family: inherit; transition: border 0.2s;
    max-height: 100px;
}
.chat-input-row input[type="text"]:focus { border-color: #6366f1; }
.chat-input-row input[type="file"] { display: none; }

.chat-icon-btn {
    width: 40px; height: 40px; border-radius: 50%; border: none;
    background: #f3f4f6; color: #6b7280; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0; transition: all 0.2s;
}
.chat-icon-btn:hover { background: #e5e7eb; color: #374151; }
.chat-send-btn { background: #6366f1; color: #fff; }
.chat-send-btn:hover { background: #4f46e5; color: #fff; }

.chat-attach-preview {
    display: none; padding: 8px 12px;
    background: #eff6ff; border-radius: 8px;
    margin-bottom: 8px; font-size: 0.82rem; color: #1e40af;
    align-items: center; justify-content: space-between;
}
.chat-attach-preview.show { display: flex; }
.chat-attach-preview .remove-attach { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 1rem; }

@media (max-width: 600px) {
    .chat-page-wrap { padding: 12px; }
    .chat-container { height: calc(100vh - 200px); }
    .chat-messages { padding: 12px; }
    .chat-bubble-wrap { max-width: 90%; }
}
</style>

<div class="chat-page-wrap">

<!-- HEADER -->
<div class="chat-header-bar">
    <div class="chat-header-info">
        <div class="chat-header-avatar">
            <?php if (!empty($other_photo)): ?>
                <img src="<?= h($other_photo) ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($other_name, 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <h2><i class="fas fa-comments" style="color:#6366f1; margin-right:6px;"></i> <?= h($other_name) ?></h2>
            <p>Regarding: <strong><?= h($app['job_title']) ?></strong></p>
        </div>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="<?= $my_role === 'employer' ? 'job-applicants.php?id=' . $app['job_id'] : 'seeker.php' ?>" 
           style="background:#f3f4f6; color:#374151; padding:8px 14px; border-radius:8px; text-decoration:none; font-size:0.85rem; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<!-- CHAT -->
<div class="chat-container">
    <div class="chat-messages" id="chatBox">
        <div class="chat-empty">
            <i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#6366f1; margin-bottom:10px; display:block;"></i>
            <p style="color:#9ca3af; margin:0;">Loading messages...</p>
        </div>
    </div>
    
    <div class="chat-input-area">
        <div class="chat-attach-preview" id="attachPreview">
            <span><i class="fas fa-paperclip"></i> <span id="attachName">file.jpg</span></span>
            <button type="button" class="remove-attach" onclick="clearAttachment()"><i class="fas fa-times"></i></button>
        </div>
        
        <form id="chatForm" class="chat-input-row">
            <input type="file" id="fileInput" accept="image/*,.pdf,.doc,.docx" onchange="handleFileSelect(this)">
            <button type="button" class="chat-icon-btn" onclick="document.getElementById('fileInput').click()" title="Attach file">
                <i class="fas fa-paperclip"></i>
            </button>
            <input type="text" id="msgInput" placeholder="Type your message..." autocomplete="off" maxlength="500">
            <button type="submit" class="chat-icon-btn chat-send-btn" title="Send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    </div>
</div>

</div>

<script>
const chatBox = document.getElementById('chatBox');
const chatForm = document.getElementById('chatForm');
const msgInput = document.getElementById('msgInput');
const fileInput = document.getElementById('fileInput');
const attachPreview = document.getElementById('attachPreview');
const attachName = document.getElementById('attachName');

let lastHTML = '';
let isFetching = false;

// Load messages
function loadMessages() {
    if (isFetching) return;
    if (document.hidden) return; // Don't poll when tab hidden
    
    isFetching = true;
    fetch('chat.php?app_id=<?= $app_id ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_msgs'
    })
    .then(res => res.text())
    .then(html => {
        if (html !== lastHTML) {
            const wasAtBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 100;
            chatBox.innerHTML = html;
            lastHTML = html;
            if (wasAtBottom) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }
        isFetching = false;
    })
    .catch(err => {
        console.error('Load error:', err);
        isFetching = false;
    });
}

// Send message
chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const msg = msgInput.value.trim();
    const file = fileInput.files[0];
    
    if (!msg && !file) return;
    
    const formData = new FormData();
    formData.append('action', 'send_msg');
    formData.append('message', msg);
    if (file) formData.append('attachment', file);
    
    msgInput.value = '';
    clearAttachment();
    
    // Optimistic disable
    const sendBtn = chatForm.querySelector('.chat-send-btn');
    sendBtn.disabled = true;
    
    fetch('chat.php?app_id=<?= $app_id ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        sendBtn.disabled = false;
        if (data.ok) {
            loadMessages();
        } else {
            alert('Failed: ' + (data.msg || 'Unknown error'));
        }
    })
    .catch(err => {
        sendBtn.disabled = false;
        alert('Network error. Please try again.');
        console.error(err);
    });
});

// File selection
function handleFileSelect(input) {
    if (input.files && input.files[0]) {
        const f = input.files[0];
        const maxSize = 2 * 1024 * 1024;
        if (f.size > maxSize) {
            alert('File too large. Max 2MB.');
            input.value = '';
            return;
        }
        attachName.textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
        attachPreview.classList.add('show');
    }
}

function clearAttachment() {
    fileInput.value = '';
    attachPreview.classList.remove('show');
    attachName.textContent = '';
}

// Initial load + poll
loadMessages();
setInterval(loadMessages, 3000);

// Focus input on load
window.addEventListener('load', () => msgInput.focus());

// Handle Enter to send
msgInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit'));
    }
});

// Resize on visibility
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) loadMessages();
});
</script>

<?php include 'inc/foot.php'; ?>