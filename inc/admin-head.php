<?php
// ============================================
// OUTPUT BUFFERING — Prevents "headers already sent"
// ============================================
ob_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site-config.php';
must_role('admin');

$uid = $_SESSION['uid'];
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch Admin Notifications
$nq = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$nq->bind_param('i', $uid); $nq->execute(); $my_notifs = $nq->get_result();
$cq = $db->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
$cq->bind_param('i', $uid); $cq->execute(); $unread_count = $cq->get_result()->fetch_assoc()['c'];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Admin') ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: #1E293B; color: #F8FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        a { text-decoration: none; }

        :root {
            --bg-body: #1E293B; --bg-sidebar: #0F172A; --bg-card: #172235; --bg-card-hover: #1A263B;
            --text-main: #F8FAFC; --text-muted: #94A3B8;
            --accent-blue: #3B82F6; --accent-green: #10B981; --accent-red: #EF4444; --accent-yellow: #F59E0B;
            --border-color: #263449;
        }

        .sidebar { width: 240px; background: var(--bg-sidebar); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 1000; transition: transform 0.3s ease; }
        .sidebar-brand { padding: 24px; font-size: 1.2rem; font-weight: 700; color: #fff; display: flex; align-items: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-nav { padding: 20px 15px; flex: 1; overflow-y: auto; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: var(--text-muted); text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 0.9rem; margin-bottom: 6px; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: var(--bg-card); color: #fff; }
        .nav-item.active { background: var(--accent-blue); color: #fff; }
        .nav-item i { width: 20px; text-align: center; font-size: 1rem; }
        .sidebar-footer { padding: 20px 15px; border-top: 1px solid var(--border-color); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(239, 68, 68, 0.1); color: var(--accent-red); border-radius: 8px; font-weight: 600; transition: all 0.2s; font-size: 0.9rem; }
        .logout-btn:hover { background: var(--accent-red); color: #fff; }

        .main-content { margin-left: 240px; flex: 1; display: flex; flex-direction: column; width: calc(100% - 240px); }
        .topbar { height: 70px; background: var(--bg-body); padding: 0 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); position: relative; }
        .search-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; padding: 8px 16px; color: #fff; width: 300px; display: flex; align-items: center; gap: 10px; }
        .search-box input { background: none; border: none; color: #fff; outline: none; width: 100%; font-size: 0.9rem; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .user-profile { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 36px; height: 36px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); }

        /* Admin Notification CSS */
        .notif-wrap { position: relative; }
        .notif-btn { background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer; position: relative; padding: 5px; }
        .notif-dot { position: absolute; top: 2px; right: 2px; width: 10px; height: 10px; background: var(--accent-red); border-radius: 50%; border: 2px solid var(--bg-body); }
        .notif-dropdown { display: none; position: absolute; top: 100%; right: 0; width: 340px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 9999; overflow: hidden; margin-top: 10px; }
        .notif-dropdown.show { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notif-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); }
        .notif-head strong { font-size: 0.95rem; color: #fff; }
        .notif-close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; }
        .notif-close:hover { color: var(--accent-red); }
        
        .notif-body { max-height: 350px; overflow-y: auto; }
        .notif-item-wrap { display: flex; border-bottom: 1px solid var(--border-color); align-items: stretch; }
        .notif-item-wrap:hover { background: var(--bg-card-hover); }
        .notif-item-wrap.unread { background: rgba(59, 130, 246, 0.05); border-left: 3px solid var(--accent-blue); }
        
        .notif-item-content { flex: 1; padding: 12px 15px; cursor: pointer; user-select: none; }
        .notif-title { font-size: 0.88rem; font-weight: 600; color: #fff; margin-bottom: 4px; }
        .notif-msg-preview { font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .notif-time { font-size: 0.7rem; color: #64748B; }
        
        .notif-clear-btn { display: flex; align-items: center; justify-content: center; width: 40px; background: transparent; border: none; border-left: 1px solid var(--border-color); color: #64748B; cursor: pointer; font-size: 0.9rem; }
        .notif-clear-btn:hover { background: rgba(239, 68, 68, 0.2); color: var(--accent-red); }
        
        .notif-footer { padding: 10px; text-align: center; border-top: 1px solid var(--border-color); background: rgba(0,0,0,0.2); }
        .notif-clear-all { background: none; border: none; color: var(--accent-red); font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; justify-content: center; width: 100%; }
        .notif-clear-all:hover { text-decoration: underline; }
        
        .notif-empty { padding: 20px; text-align: center; color: var(--text-muted); font-size: 0.9rem; }

        /* Admin Message Modal */
        .msg-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 99999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .msg-modal.show { display: flex; animation: fadeIn 0.2s ease; }
        .msg-modal-content { background: var(--bg-card); width: 500px; max-width: 90%; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); overflow: hidden; border: 1px solid var(--border-color); }
        .msg-modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); }
        .msg-modal-header h3 { margin: 0; font-size: 1.1rem; color: #fff; }
        .msg-modal-close { background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer; }
        .msg-modal-close:hover { color: var(--accent-red); }
        .msg-modal-body { padding: 25px 20px; font-size: 0.95rem; color: #e2e8f0; line-height: 1.6; }
        .msg-modal-footer { padding: 15px 20px; border-top: 1px solid var(--border-color); text-align: right; }

        .content-area { padding: 30px; flex: 1; }
        .page-title { font-size: 1.4rem; font-weight: 600; margin-bottom: 24px; color: #fff; }
        
        .card { background: var(--bg-card); border-radius: 12px; padding: 24px; border: 1px solid var(--border-color); margin-bottom: 24px; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { text-align: left; padding: 16px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
        .admin-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; color: #e2e8f0; }
        .admin-table tr:hover td { background: var(--bg-card-hover); }

        .pill { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .pill-active { background: rgba(16, 185, 129, 0.15); color: var(--accent-green); }
        .pill-pending { background: rgba(245, 158, 11, 0.15); color: var(--accent-yellow); }
        .pill-rejected { background: rgba(239, 68, 68, 0.15); color: var(--accent-red); }
        .pill-role { background: rgba(59, 130, 246, 0.15); color: var(--accent-blue); }
        .pill-deleted { background: rgba(100, 116, 139, 0.15); color: #94A3B8; }

        .btn-sm { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-blue { background: var(--accent-blue); color: #fff; }
        .btn-green { background: var(--accent-green); color: #fff; }
        .btn-red { background: var(--accent-red); color: #fff; }
        .btn-gray { background: var(--bg-card-hover); color: #fff; border: 1px solid var(--border-color); }
        .btn-yellow { background: var(--accent-yellow); color: #000; }

        .alert { padding: 14px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: 0.9rem; }
        .alert-ok { background: rgba(16, 185, 129, 0.15); color: var(--accent-green); border: 1px solid rgba(16, 185, 129, 0.2); }

        .folder-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .folder-tab { background: var(--bg-card); border: 1px solid var(--border-color); padding: 10px 18px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; color: var(--text-muted); text-decoration: none; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .folder-tab:hover { background: var(--bg-card-hover); color: #fff; }
        .folder-tab.active { background: var(--accent-blue); color: #fff; border-color: var(--accent-blue); }

        .modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:none; align-items:center; justify-content:center; }
        .modal-content { background: var(--bg-card); width: 650px; max-width: 95%; border-radius: 16px; padding: 30px; border: 1px solid var(--border-color); position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.5); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 20px; color: var(--text-muted); cursor: pointer; font-size: 1.2rem; }
        .close-modal:hover { color: #fff; }
        .modal-header { margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; }
        .modal-header h2 { color: #fff; font-size: 1.3rem; margin-bottom: 6px; }
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .profile-item label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; text-transform: uppercase; }
        .profile-item div { font-size: 0.95rem; font-weight: 500; color: #fff; word-break: break-word; }
        .profile-full { grid-column: 1 / -1; }
        .profile-gallery { display: flex; gap: 10px; overflow-x: auto; margin-top: 10px; }
        .profile-gallery img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); }

        .sidebar-toggle { display: none; background: none; border: none; color: #fff; font-size: 1.2rem; cursor: pointer; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .sidebar-toggle { display: block; }
            .search-box { display: none; }
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-brand">LOCAL JOBS</div>
        <div class="sidebar-nav">
            <a href="admin.php" class="nav-item <?= $current_page=='admin.php'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="admin-users.php" class="nav-item <?= $current_page=='admin-users.php'?'active':'' ?>"><i class="fas fa-users"></i> Manage Users</a>
            <a href="admin-verify.php" class="nav-item <?= $current_page=='admin-verify.php'?'active':'' ?>"><i class="fas fa-user-check"></i> Verification Requests</a>
            <a href="admin-jobs.php" class="nav-item <?= $current_page=='admin-jobs.php'?'active':'' ?>"><i class="fas fa-briefcase"></i> Manage Jobs</a>
            <a href="admin-reports.php" class="nav-item <?= $current_page=='admin-reports.php'?'active':'' ?>"><i class="fas fa-shield-alt"></i> Reports & Moderation</a>
            <a href="admin-monetization.php" class="nav-item <?= $current_page=='admin-monetization.php'?'active':'' ?>"><i class="fas fa-wallet"></i> Monetization</a>
            <a href="admin-settings.php" class="nav-item <?= $current_page=='admin-settings.php'?'active':'' ?>"><i class="fas fa-cog"></i> Settings</a>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:15px;">
                <button class="sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('show')"><i class="fas fa-bars"></i></button>
                <div class="search-box">
                    <i class="fas fa-search" style="color:var(--text-muted); font-size:0.9rem;"></i>
                    <input type="text" placeholder="Search...">
                </div>
            </div>
            <div class="topbar-right">
                
                <!-- ADMIN NOTIFICATION BELL -->
                <div class="notif-wrap">
                    <button type="button" class="notif-btn" onclick="toggleNotif(event)">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-head">
                            <strong><i class="fas fa-bell" style="color:var(--accent-blue); margin-right:5px;"></i> Notifications</strong>
                            <button type="button" class="notif-close" onclick="closeNotif(event)"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="notif-body">
                            <?php if ($my_notifs && $my_notifs->num_rows > 0): ?>
                                <?php while($n = $my_notifs->fetch_assoc()): ?>
                                    <div class="notif-item-wrap <?= $n['is_read'] == 0 ? 'unread' : '' ?>">
                                        <div class="notif-item-content" 
                                             ondblclick="openMsg(<?= $n['id'] ?>, '<?= h(addslashes($n['title'])) ?>', '<?= h(addslashes($n['message'])) ?>', '<?= date('d M, h:i A', strtotime($n['created_at'])) ?>')"
                                             title="Double click to read full message">
                                            <div class="notif-title"><?= h($n['title']) ?></div>
                                            <div class="notif-msg-preview"><?= h($n['message']) ?></div>
                                            <div class="notif-time"><i class="far fa-clock"></i> <?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
                                        </div>
                                        <form method="POST" action="clear-notifications.php" style="margin:0;">
                                            <input type="hidden" name="clear_single" value="1">
                                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                            <button type="submit" class="notif-clear-btn" title="Clear this notification"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notif-empty"><i class="fas fa-bell-slash" style="font-size:1.5rem; color:#d1d5db; margin-bottom:10px; display:block;"></i> No new notifications</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($my_notifs && $my_notifs->num_rows > 0): ?>
                        <div class="notif-footer">
                            <form method="POST" action="clear-notifications.php" style="margin:0;">
                                <input type="hidden" name="clear_all" value="1">
                                <button type="submit" class="notif-clear-all" onclick="return confirm('Clear all notifications?');"><i class="fas fa-trash-alt"></i> Clear All</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- END NOTIFICATION BELL -->

                <div class="user-profile">
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                    <span style="font-size:0.9rem; font-weight:500;"><?= h($_SESSION['name']) ?></span>
                </div>
            </div>
        </div>

        <div class="content-area">
            <?php if (isset($_GET['msg'])): ?><div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($_GET['msg']) ?></div><?php endif; ?>

    <!-- ADMIN MESSAGE MODAL -->
    <div id="msgModal" class="msg-modal" onclick="closeMsgModal(event)">
        <div class="msg-modal-content" onclick="event.stopPropagation()">
            <div class="msg-modal-header">
                <h3 id="modalTitle">Notification</h3>
                <button class="msg-modal-close" onclick="closeMsgModal(event)"><i class="fas fa-times"></i></button>
            </div>
            <div class="msg-modal-body">
                <p id="modalMessage" style="white-space: pre-wrap;"></p>
                <div style="margin-top: 15px; font-size: 0.8rem; color: #64748B; border-top: 1px solid var(--border-color); padding-top: 10px;">
                    <i class="far fa-clock"></i> <span id="modalTime"></span>
                </div>
            </div>
            <div class="msg-modal-footer">
                <button class="btn-sm btn-blue" onclick="closeMsgModal(event)">Close</button>
            </div>
        </div>
    </div>

    <script>
        function toggleNotif(e) {
            if(e) { e.preventDefault(); e.stopPropagation(); }
            document.getElementById('notifDropdown').classList.toggle('show');
        }
        function closeNotif(e) {
            if(e) { e.preventDefault(); e.stopPropagation(); }
            document.getElementById('notifDropdown').classList.remove('show');
        }
        document.addEventListener('click', function(e) {
            var notif = document.getElementById('notifDropdown');
            if (notif && !e.target.closest('.notif-wrap')) notif.classList.remove('show');
        });

        function openMsg(id, title, message, time) {
            document.getElementById('modalTitle').innerText = title;
            document.getElementById('modalMessage').innerText = message;
            document.getElementById('modalTime').innerText = time;
            document.getElementById('msgModal').classList.add('show');
            
            let formData = new FormData();
            formData.append('mark_read', 1);
            formData.append('id', id);
            
            fetch('mark-read.php', { method: 'POST', body: formData }).then(() => {
                let clickedItem = document.querySelector(`.notif-item-content[ondblclick*="openMsg(${id},"]`).closest('.notif-item-wrap');
                if (clickedItem) clickedItem.classList.remove('unread');
            });
        }

        function closeMsgModal(e) {
            if(e) e.stopPropagation();
            document.getElementById('msgModal').classList.remove('show');
        }
    </script>