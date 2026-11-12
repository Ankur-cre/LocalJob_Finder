<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/site-config.php';

$title = $title ?? SITE_NAME;

$no_search_pages = ['login.php', 'reg-seeker.php', 'reg-employer.php', 'admin-auth.php', 'join.php'];
$show_search = logged_in() && !in_array(basename($_SERVER['PHP_SELF']), $no_search_pages);

$home_url = 'index.php';
$unread_count = 0;
$my_notifs = null;

if (logged_in()) {
    if (role() === 'seeker')        $home_url = 'seeker-home.php';
    elseif (role() === 'employer')  $home_url = 'employer.php';
    elseif (role() === 'admin')     $home_url = 'admin.php';

    $uid = (int)$_SESSION['uid'];

    try {
        $chk = $db->query("SHOW COLUMNS FROM `notifications` LIKE 'is_read'");
        if ($chk && $chk->num_rows === 0) {
            $db->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {}

    try {
        $nq = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        if ($nq) {
            $nq->bind_param('i', $uid);
            $nq->execute();
            $my_notifs = $nq->get_result();
        }
    } catch (Throwable $e) {}

    try {
        $cq = $db->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($cq) {
            $cq->bind_param('i', $uid);
            $cq->execute();
            $unread_count = (int)($cq->get_result()->fetch_assoc()['c'] ?? 0);
        }
    } catch (Throwable $e) {}
}

$nav_accent = '';
if (logged_in()) {
    if (role() === 'seeker')        $nav_accent = 'nav-seeker';
    elseif (role() === 'employer')  $nav_accent = 'nav-employer';
    elseif (role() === 'admin')     $nav_accent = 'nav-admin';
}

$cur_lang = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= h($cur_lang) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/legal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notif-wrap { position: relative; display: inline-block; }
        .notif-btn { background: none; border: none; font-size: 1.2rem; color: var(--text-soft); cursor: pointer; position: relative; padding: 5px; }
        .notif-dot { position: absolute; top: 0px; right: 0px; min-width: 16px; height: 16px; background: var(--red); border-radius: 10px; border: 2px solid #fff; color: #fff; font-size: 0.6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; padding: 0 3px; }
        .notif-dropdown { display: none; position: absolute; top: 100%; right: 0; width: 340px; background: #fff; border: 1px solid var(--gray-border); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 9999; margin-top: 10px; overflow: hidden; }
        .notif-dropdown.show { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notif-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid var(--gray-border); background: #f9fafb; }
        .notif-head strong { font-size: 0.95rem; color: var(--text); }
        .notif-close { background: none; border: none; color: var(--gray); cursor: pointer; }
        .notif-body { max-height: 350px; overflow-y: auto; }
        .notif-item-wrap { display: flex; border-bottom: 1px solid #f3f4f6; align-items: stretch; width: 100%; }
        .notif-item-wrap:hover { background: #f9fafb; }
        .notif-item-wrap.unread { background: #eff6ff; border-left: 3px solid var(--blue); }
        .notif-item-content { flex: 1; padding: 12px 15px; cursor: pointer; min-width: 0; }
        .notif-title { font-size: 0.88rem; font-weight: 600; color: var(--text); margin-bottom: 4px; }
        .notif-msg-preview { font-size: 0.82rem; color: var(--gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 5px; }
        .notif-time { font-size: 0.7rem; color: var(--gray-light); }
        .notif-clear-btn { display: flex; align-items: center; justify-content: center; width: 40px; flex-shrink: 0; background: transparent; border: none; border-left: 1px solid #f3f4f6; color: #d1d5db; cursor: pointer; }
        .notif-clear-btn:hover { background: #fee2e2; color: var(--red); }
        .notif-footer { padding: 10px; text-align: center; border-top: 1px solid var(--gray-border); background: #f9fafb; }
        .notif-clear-all { background: none; border: none; color: var(--red); font-size: 0.8rem; font-weight: 600; cursor: pointer; }
        .notif-empty { padding: 20px; text-align: center; color: var(--gray); font-size: 0.9rem; }

        .profile-drop-wrap { position: relative; display: inline-block; }
        .profile-drop-btn { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 4px 10px; border-radius: 20px; transition: background 0.2s; color: var(--text); }
        .profile-drop-btn:hover { background: var(--gray-bg); }
        .profile-avatar-small { width: 32px; height: 32px; border-radius: 50%; background: #6366f1; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
        .profile-avatar-small img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .profile-drop-menu { display: none; position: absolute; top: 100%; right: 0; min-width: 220px; background: #fff; border: 1px solid var(--gray-border); border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); z-index: 9999; margin-top: 8px; overflow: hidden; }
        .profile-drop-menu.show { display: block; }
        .profile-drop-menu a, .profile-drop-menu button { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #374151; text-decoration: none; font-size: 0.88rem; background: none; border: none; width: 100%; text-align: left; cursor: pointer; font-family: inherit; }
        .profile-drop-menu a:hover, .profile-drop-menu button:hover { background: #f9fafb; color: #6366f1; text-decoration: none; }
        .profile-drop-menu i { width: 16px; color: #9ca3af; }
        .profile-drop-menu hr { margin: 4px 0; border: none; border-top: 1px solid #f3f4f6; }

        .msg-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999; display: none; align-items: center; justify-content: center; }
        .msg-modal.show { display: flex; }
        .msg-modal-content { background: #fff; width: 500px; max-width: 90%; border-radius: 12px; overflow: hidden; }
        .msg-modal-header { padding: 15px 20px; border-bottom: 1px solid var(--gray-border); display: flex; justify-content: space-between; align-items: center; background: #f9fafb; }
        .msg-modal-header h3 { margin: 0; font-size: 1.1rem; }
        .msg-modal-close { background: none; border: none; font-size: 1.2rem; color: var(--gray); cursor: pointer; }
        .msg-modal-body { padding: 20px; font-size: 0.95rem; color: var(--text-soft); line-height: 1.6; }
        .msg-modal-footer { padding: 15px 20px; border-top: 1px solid var(--gray-border); text-align: right; }

        .nav-menu { flex-wrap: wrap; }
        .nav-menu a { white-space: nowrap; }
        
        @media (max-width: 768px) {
            .notif-dropdown { width: 300px; right: -60px; }
            .profile-drop-btn span.username { display: none; }
        }
    </style>
</head>
<body>

<nav class="nav <?= $nav_accent ?>">
    <div class="wrap nav-wrap">
        <a href="<?= $home_url ?>" class="brand">
            <i class="fas fa-briefcase"></i> <?= SITE_NAME ?>
        </a>

        <?php if ($show_search): ?>
        <form method="GET" action="jobs.php" class="nav-search">
            <input type="text" name="q" placeholder="<?= t('nav.search_placeholder') ?>" value="<?= h($_GET['q'] ?? '') ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        <?php endif; ?>

        <ul class="nav-menu">
            <li><a href="<?= $home_url ?>"><i class="fas fa-home"></i> <?= t('nav.home') ?></a></li>

            <?php if (logged_in()): ?>

                <?php if (role() === 'seeker'): ?>
                    <li><a href="jobs.php"><i class="fas fa-briefcase"></i> Browse Jobs</a></li>
                    <li><a href="seeker.php"><i class="fas fa-file-alt"></i> Applications</a></li>
                <?php elseif (role() === 'employer'): ?>
                    <li><a href="jobs.php"><i class="fas fa-briefcase"></i> Browse Jobs</a></li>
                    <li><a href="post.php"><i class="fas fa-plus-circle"></i> Post Job</a></li>
                    <li><a href="employer.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                <?php elseif (role() === 'admin'): ?>
                    <li><a href="admin.php"><i class="fas fa-shield-alt"></i> Admin Panel</a></li>
                <?php endif; ?>

                <li class="notif-wrap">
                    <button type="button" class="notif-btn" onclick="toggleNotif(event)">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notif-dot"><?= $unread_count > 99 ? '99+' : (int)$unread_count ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-head">
                            <strong><i class="fas fa-bell" style="color:var(--blue); margin-right:5px;"></i> Notifications <?php if ($unread_count > 0): ?><span style="color:var(--red);">(<?= (int)$unread_count ?>)</span><?php endif; ?></strong>
                            <button type="button" class="notif-close" onclick="closeNotif(event)"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="notif-body">
                            <?php if ($my_notifs && $my_notifs->num_rows > 0): ?>
                                <?php while($n = $my_notifs->fetch_assoc()): ?>
                                    <div class="notif-item-wrap <?= $n['is_read'] == 0 ? 'unread' : '' ?>">
                                        <div class="notif-item-content" onclick="openMsg(<?= (int)$n['id'] ?>, '<?= h(addslashes($n['title'] ?? '')) ?>', '<?= h(addslashes($n['message'] ?? '')) ?>', '<?= date('d M, h:i A', strtotime($n['created_at'])) ?>', <?= $n['is_read'] == 0 ? 'true' : 'false' ?>)">
                                            <div class="notif-title"><?= h($n['title']) ?></div>
                                            <div class="notif-msg-preview"><?= h($n['message']) ?></div>
                                            <div class="notif-time"><i class="far fa-clock"></i> <?= date('d M, h:i A', strtotime($n['created_at'])) ?></div>
                                        </div>
                                        <form method="POST" action="clear-notifications.php" style="margin:0;">
                                            <input type="hidden" name="clear_single" value="1">
                                            <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                            <button type="submit" class="notif-clear-btn"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notif-empty"><i class="fas fa-bell-slash" style="font-size:1.5rem; color:#d1d5db; margin-bottom:10px; display:block;"></i>No new notifications</div>
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
                </li>

                <li class="profile-drop-wrap">
                    <button type="button" class="profile-drop-btn" onclick="toggleProfileMenu(event)">
                        <div class="profile-avatar-small">
                            <?php if (role() === 'employer' && !empty($_SESSION['name'])): ?>
                                <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <span class="username" style="font-weight:600; font-size:0.88rem;"><?= h($_SESSION['name'] ?? 'User') ?></span>
                        <i class="fas fa-chevron-down" style="font-size:0.7rem; color:#9ca3af;"></i>
                    </button>
                    <div class="profile-drop-menu" id="profileMenu">
                        <div style="padding:14px 16px; border-bottom:1px solid #f3f4f6; background:#f9fafb;">
                            <strong style="display:block; color:#111827;"><?= h($_SESSION['name'] ?? 'User') ?></strong>
                            <small style="color:#6b7280; text-transform:capitalize;"><?= h(role()) ?></small>
                        </div>
                        <?php if (role() === 'seeker'): ?>
                            <a href="seeker-home.php"><i class="fas fa-home"></i> Dashboard</a>
                            <a href="seeker.php"><i class="fas fa-file-alt"></i> My Applications</a>
                            <a href="seeker-settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <?php elseif (role() === 'employer'): ?>
                            <a href="employer.php"><i class="fas fa-home"></i> Dashboard</a>
                            <a href="employer-settings.php?tab=company"><i class="fas fa-building"></i> Company Profile</a>
                            <a href="post.php"><i class="fas fa-plus-circle"></i> Post Job</a>
                            <a href="employer-settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <?php else: ?>
                            <a href="admin.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
                        <?php endif; ?>
                        <hr>
                        <a href="logout.php" style="color:#dc2626;"><i class="fas fa-sign-out-alt" style="color:#dc2626;"></i> Logout</a>
                    </div>
                </li>
            <?php else: ?>
                <li><a href="login.php" class="btn-blue btn-sm"><i class="fas fa-sign-in-alt"></i> <?= t('nav.signin') ?></a></li>
                <li><a href="join.php" class="btn-outline-blue btn-sm"><?= t('nav.get_started') ?></a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<div id="msgModal" class="msg-modal" onclick="closeMsgModal(event)">
    <div class="msg-modal-content" onclick="event.stopPropagation()">
        <div class="msg-modal-header">
            <h3 id="modalTitle">Notification</h3>
            <button class="msg-modal-close" onclick="closeMsgModal(event)"><i class="fas fa-times"></i></button>
        </div>
        <div class="msg-modal-body">
            <p id="modalMessage" style="white-space: pre-wrap;"></p>
            <div style="margin-top: 15px; font-size: 0.8rem; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px;">
                <i class="far fa-clock"></i> <span id="modalTime"></span>
            </div>
        </div>
        <div class="msg-modal-footer">
            <button class="btn-blue btn-sm" onclick="closeMsgModal(event)">Close</button>
        </div>
    </div>
</div>

<script>
function toggleNotif(e) {
    e.preventDefault(); e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('show');
    document.getElementById('profileMenu').classList.remove('show');
}
function closeNotif(e) {
    e.preventDefault(); e.stopPropagation();
    document.getElementById('notifDropdown').classList.remove('show');
}
function toggleProfileMenu(e) {
    e.preventDefault(); e.stopPropagation();
    document.getElementById('profileMenu').classList.toggle('show');
    document.getElementById('notifDropdown').classList.remove('show');
}
document.addEventListener('click', function(e) {
    var notif = document.getElementById('notifDropdown');
    var prof = document.getElementById('profileMenu');
    if (notif && !e.target.closest('.notif-wrap')) notif.classList.remove('show');
    if (prof && !e.target.closest('.profile-drop-wrap')) prof.classList.remove('show');
});

function openMsg(id, title, message, time, isUnread) {
    document.getElementById('modalTitle').innerText = title;
    document.getElementById('modalMessage').innerText = message;
    document.getElementById('modalTime').innerText = time;
    document.getElementById('msgModal').classList.add('show');
    if (isUnread) {
        let fd = new FormData();
        fd.append('mark_read', 1);
        fd.append('id', id);
        fetch('mark-read.php', { method: 'POST', body: fd }).then(() => {
            const el = document.querySelector(`.notif-item-content[onclick*="openMsg(${id},"]`);
            if (el) {
                const wrap = el.closest('.notif-item-wrap');
                if (wrap) wrap.classList.remove('unread');
            }
            const badge = document.querySelector('.notif-dot');
            if (badge) {
                const current = parseInt(badge.textContent) || 0;
                if (current > 1) badge.textContent = current - 1;
                else badge.remove();
            }
        });
    }
}
function closeMsgModal(e) {
    if(e) e.stopPropagation();
    document.getElementById('msgModal').classList.remove('show');
}
</script>

<main class="page">
<div class="wrap">