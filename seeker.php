<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = t('nav.my_apps') . " — " . SITE_NAME;
$uid = $_SESSION['uid'];
$msg = '';

// ============ HELPER ============
if (!function_exists('column_exists')) {
    function column_exists($db, $table, $column) {
        $table  = $db->real_escape_string($table);
        $column = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $res && $res->num_rows > 0;
    }
}

// ============ HANDLE WITHDRAW ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_app'])) {
    $app_id = (int)$_POST['app_id'];
    $chk = $db->prepare("SELECT id FROM applications WHERE id=? AND seeker_id=? AND status='pending'");
    $chk->bind_param('ii', $app_id, $uid);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $db->query("DELETE FROM applications WHERE id=$app_id");
        $msg = "Application withdrawn successfully.";
    }
}

// ============ HANDLE MARK ALL NOTIFICATIONS READ ============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    try {
        if (column_exists($db, 'notifications', 'is_read')) {
            $db->query("UPDATE notifications SET is_read = 1 WHERE user_id = $uid");
            $msg = "All notifications marked as read.";
        }
    } catch (Throwable $e) { /* ignore */ }
}

// ============ STATS ============
$s = $db->prepare("SELECT COUNT(*) total, 
                          SUM(status='pending') pending, 
                          SUM(status='shortlisted') shortlisted, 
                          SUM(status='interview') interview,
                          SUM(status='hired') hired,
                          SUM(status='rejected') rejected
                   FROM applications WHERE seeker_id=?");
$s->bind_param('i', $uid); $s->execute(); $stats = $s->get_result()->fetch_assoc();

// ============ FETCH APPLICATIONS ============
$stmt = $db->prepare("
    SELECT a.*, j.title, j.location, j.salary, j.job_type, j.category, 
           u.company_name, u.phone AS employer_phone
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN users u ON j.employer_id = u.id
    WHERE a.seeker_id = ?
    ORDER BY a.applied_at DESC
");
$stmt->bind_param('i', $uid); $stmt->execute(); $apps = $stmt->get_result();

// ============ FETCH UNREAD NOTIFICATIONS (recent 3) ============
$recent_notifications = [];
$unread_count = 0;
try {
    if (column_exists($db, 'notifications', 'user_id')) {
        $has_read_col = column_exists($db, 'notifications', 'is_read');
        
        // Count unread
        if ($has_read_col) {
            $cq = $db->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
            $cq->bind_param('i', $uid);
            $cq->execute();
            $unread_count = (int)($cq->get_result()->fetch_assoc()['c'] ?? 0);
        }
        
        // Fetch recent 3 (read + unread)
        $nq = $db->prepare("SELECT id, title, message, link, " . ($has_read_col ? "is_read," : "0 AS is_read,") . " created_at 
                            FROM notifications 
                            WHERE user_id=? 
                            ORDER BY created_at DESC LIMIT 3");
        $nq->bind_param('i', $uid);
        $nq->execute();
        $recent_notifications = $nq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) { /* silently ignore */ }

// Determine "NEW" — a status change within 24 hours
function is_recent($datetime, $hours = 24) {
    if (empty($datetime)) return false;
    return (time() - strtotime($datetime)) < ($hours * 3600);
}

include 'inc/head.php';
?>

<div style="background: var(--gray-bg); padding: 30px 0 50px;">
    <div class="wrap" style="max-width: 1100px;">
        
        <?php if ($msg): ?><div class="alert alert-ok"><i class="fas fa-check-circle"></i> <?= h($msg) ?></div><?php endif; ?>
        <?php if (isset($_GET['msg'])): ?><div class="alert alert-ok"><?= h($_GET['msg']) ?></div><?php endif; ?>

        <!-- HEADER -->
        <div class="flex-between mb">
            <div>
                <h2 style="font-size: 1.6rem; margin-bottom: 4px; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-file-alt" style="color: var(--green);"></i> My Applications
                    <?php if ($unread_count > 0): ?>
                        <span style="background:#ef4444; color:#fff; font-size:0.7rem; padding:3px 9px; border-radius:12px; font-weight:700; animation: pulse 2s infinite;">
                            🔔 <?= $unread_count ?> new
                        </span>
                    <?php endif; ?>
                </h2>
                <p style="color: var(--gray); font-size: 0.95rem;">Track your application journey step-by-step.</p>
            </div>
            <div style="display:flex; gap:8px;">
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="margin:0;">
                        <button type="submit" name="mark_read" value="1" class="btn-sm btn-gray" title="Mark all as read">
                            <i class="fas fa-check-double"></i> Mark Read
                        </button>
                    </form>
                <?php endif; ?>
                <a href="jobs.php" class="btn-blue btn-sm"><i class="fas fa-search"></i> Browse Jobs</a>
            </div>
        </div>

        <!-- RECENT NOTIFICATIONS BAR -->
        <?php if (!empty($recent_notifications)): ?>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <h3 style="font-size:0.95rem; color:#111827; margin:0;">
                        <i class="fas fa-bell" style="color:#f59e0b;"></i> Recent Notifications
                    </h3>
                    <?php if ($unread_count > 0): ?>
                        <span style="font-size:0.75rem; color:#ef4444; font-weight:600;"><?= $unread_count ?> unread</span>
                    <?php endif; ?>
                </div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($recent_notifications as $n): ?>
                        <a href="<?= h($n['link'] ?? 'seeker.php') ?>" style="display:flex; gap:12px; padding:10px 12px; border-radius:8px; text-decoration:none; color:inherit; background:<?= empty($n['is_read']) ? '#eff6ff' : '#f9fafb' ?>; border-left:3px solid <?= empty($n['is_read']) ? '#3b82f6' : '#e5e7eb' ?>;">
                            <div style="flex-shrink:0; width:32px; height:32px; background:<?= empty($n['is_read']) ? '#dbeafe' : '#f3f4f6' ?>; color:<?= empty($n['is_read']) ? '#1e40af' : '#9ca3af' ?>; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                                <i class="fas fa-info-circle" style="font-size:0.8rem;"></i>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <p style="margin:0; font-size:0.88rem; color:#111827; font-weight:<?= empty($n['is_read']) ? '600' : '400' ?>;">
                                    <?= h($n['title'] ?? 'Notification') ?>
                                    <?php if (empty($n['is_read'])): ?>
                                        <span style="background:#ef4444; color:#fff; font-size:0.6rem; padding:1px 6px; border-radius:8px; margin-left:6px;">NEW</span>
                                    <?php endif; ?>
                                </p>
                                <p style="margin:2px 0 0; font-size:0.8rem; color:#6b7280; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?= h($n['message'] ?? '') ?>
                                </p>
                            </div>
                            <div style="flex-shrink:0; font-size:0.7rem; color:#9ca3af; text-align:right;">
                                <?= !empty($n['created_at']) ? date('d M', strtotime($n['created_at'])) : '' ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- STATS ROW -->
        <div class="stats-row">
            <div class="stat-box"><div class="num"><?= (int)$stats['total'] ?></div><div class="lbl">Total Applied</div></div>
            <div class="stat-box" style="border-top-color:#f59e0b;"><div class="num" style="color:#f59e0b;"><?= (int)$stats['pending'] ?></div><div class="lbl">Pending</div></div>
            <div class="stat-box" style="border-top-color:var(--blue);"><div class="num" style="color:var(--blue);"><?= (int)$stats['shortlisted'] ?></div><div class="lbl">Shortlisted</div></div>
            <div class="stat-box" style="border-top-color:#6366f1;"><div class="num" style="color:#6366f1;"><?= (int)$stats['interview'] ?></div><div class="lbl">Interview</div></div>
            <div class="stat-box" style="border-top-color:var(--green);"><div class="num" style="color:var(--green);"><?= (int)$stats['hired'] ?></div><div class="lbl">Hired</div></div>
        </div>

        <!-- APPLICATION LIST -->
        <?php if ($apps->num_rows === 0): ?>
            <div class="card empty-state" style="padding: 50px 20px;">
                <i class="fas fa-inbox" style="font-size: 3rem; color:#d1d5db; margin-bottom: 15px;"></i>
                <h3 style="color:#6b7280;">No applications yet</h3>
                <p style="color:#9ca3af; margin-top: 8px;">Start applying to jobs from the <a href="jobs.php">browse jobs page</a>.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php while ($a = $apps->fetch_assoc()): ?>
                <?php
                  $cls = 'pending';
                  if ($a['status']==='shortlisted') $cls='shortlisted';
                  elseif ($a['status']==='hired')   $cls='hired';
                  elseif ($a['status']==='rejected')$cls='rejected';
                  elseif ($a['status']==='interview')$cls='shortlisted';

                  // Determine latest status-change timestamp for "NEW" badge
                  $latest_change = null;
                  foreach (['shortlisted_at','interview_at','hired_at','rejected_at'] as $col) {
                      if (!empty($a[$col]) && ($latest_change === null || strtotime($a[$col]) > strtotime($latest_change))) {
                          $latest_change = $a[$col];
                      }
                  }
                  $is_new = $latest_change && is_recent($latest_change, 24);
                ?>
                
                <div class="card" style="margin:0; border-left: 4px solid <?= $cls === 'hired' ? 'var(--green)' : ($cls === 'rejected' ? 'var(--red)' : 'var(--blue)') ?>;">
                    
                    <!-- JOB HEADER -->
                    <div class="flex-between mb" style="margin-bottom: 20px;">
                        <div>
                            <h3 style="font-size: 1.2rem; margin-bottom: 4px; display:flex; align-items:center; gap:8px;">
                                <?= h($a['title']) ?>
                                <?php if ($is_new): ?>
                                    <span style="background:#ef4444; color:#fff; font-size:0.65rem; padding:2px 8px; border-radius:10px; font-weight:700;">NEW</span>
                                <?php endif; ?>
                            </h3>
                            <p style="color: var(--gray); font-size: 0.9rem;">
                                <i class="fas fa-building"></i> <?= h($a['company_name']) ?> • 
                                <i class="fas fa-map-marker-alt"></i> <?= h($a['location']) ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <span class="pill pill-<?= $cls ?>" style="font-size: 0.85rem; padding: 6px 14px;"><?= ucfirst($a['status']) ?></span>
                            <p style="font-size: 0.8rem; color: var(--gray); margin-top: 5px;">Applied <?= date('d M Y', strtotime($a['applied_at'])) ?></p>
                            <?php if ($latest_change): ?>
                                <p style="font-size: 0.75rem; color: var(--blue); margin-top: 3px; font-weight:600;">
                                    <i class="fas fa-clock"></i> Updated <?= date('d M, h:i A', strtotime($latest_change)) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- INTERVIEW DETAILS -->
                    <?php if ($a['status'] === 'interview' && !empty($a['interview_details'])): ?>
                        <div style="background: #eef2ff; border-left: 3px solid #6366f1; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;">
                            <strong style="color: #4338ca;"><i class="fas fa-calendar-check"></i> Interview Details:</strong>
                            <p style="margin: 5px 0 0; color: #374151;"><?= nl2br(h($a['interview_details'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- TIMELINE (same as before) -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 0 10px; overflow-x: auto;">
                        
                        <div style="text-align: center; flex: 1; min-width: 80px;">
                            <div style="width: 32px; height: 32px; background: var(--blue); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                <i class="fas fa-paper-plane" style="font-size: 0.8rem;"></i>
                            </div>
                            <p style="font-size: 0.75rem; font-weight: 600; color: var(--blue);">Applied</p>
                            <p style="font-size: 0.7rem; color: var(--gray);"><?= date('d M', strtotime($a['applied_at'])) ?></p>
                        </div>
                        
                        <div style="flex: 0 0 auto; height: 2px; background: <?= !empty($a['viewed_at']) ? 'var(--blue)' : '#e5e7eb' ?>; width: 30px; margin-top: 16px;"></div>

                        <div style="text-align: center; flex: 1; min-width: 80px;">
                            <div style="width: 32px; height: 32px; background: <?= !empty($a['viewed_at']) ? 'var(--blue)' : '#e5e7eb' ?>; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                <i class="fas fa-eye" style="font-size: 0.8rem;"></i>
                            </div>
                            <p style="font-size: 0.75rem; font-weight: 600; color: <?= !empty($a['viewed_at']) ? 'var(--blue)' : 'var(--gray-light)' ?>;">Viewed</p>
                            <p style="font-size: 0.7rem; color: var(--gray);"><?= !empty($a['viewed_at']) ? date('d M', strtotime($a['viewed_at'])) : '—' ?></p>
                        </div>

                        <div style="flex: 0 0 auto; height: 2px; background: <?= !empty($a['shortlisted_at']) ? 'var(--blue)' : '#e5e7eb' ?>; width: 30px; margin-top: 16px;"></div>

                        <div style="text-align: center; flex: 1; min-width: 80px;">
                            <div style="width: 32px; height: 32px; background: <?= !empty($a['shortlisted_at']) ? 'var(--blue)' : '#e5e7eb' ?>; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                <i class="fas fa-star" style="font-size: 0.8rem;"></i>
                            </div>
                            <p style="font-size: 0.75rem; font-weight: 600; color: <?= !empty($a['shortlisted_at']) ? 'var(--blue)' : 'var(--gray-light)' ?>;">Shortlisted</p>
                            <p style="font-size: 0.7rem; color: var(--gray);"><?= !empty($a['shortlisted_at']) ? date('d M', strtotime($a['shortlisted_at'])) : '—' ?></p>
                        </div>

                        <div style="flex: 0 0 auto; height: 2px; background: <?= !empty($a['interview_at']) ? 'var(--blue)' : '#e5e7eb' ?>; width: 30px; margin-top: 16px;"></div>

                        <div style="text-align: center; flex: 1; min-width: 80px;">
                            <div style="width: 32px; height: 32px; background: <?= !empty($a['interview_at']) ? '#6366f1' : '#e5e7eb' ?>; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                <i class="fas fa-phone" style="font-size: 0.8rem;"></i>
                            </div>
                            <p style="font-size: 0.75rem; font-weight: 600; color: <?= !empty($a['interview_at']) ? '#6366f1' : 'var(--gray-light)' ?>;">Interview</p>
                            <p style="font-size: 0.7rem; color: var(--gray);"><?= !empty($a['interview_at']) ? date('d M', strtotime($a['interview_at'])) : '—' ?></p>
                        </div>

                        <div style="flex: 0 0 auto; height: 2px; background: <?= (!empty($a['hired_at']) || !empty($a['rejected_at'])) ? 'var(--blue)' : '#e5e7eb' ?>; width: 30px; margin-top: 16px;"></div>

                        <div style="text-align: center; flex: 1; min-width: 80px;">
                            <?php if (!empty($a['hired_at'])): ?>
                                <div style="width: 32px; height: 32px; background: var(--green); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                    <i class="fas fa-check-circle" style="font-size: 0.8rem;"></i>
                                </div>
                                <p style="font-size: 0.75rem; font-weight: 600; color: var(--green);">Hired!</p>
                                <p style="font-size: 0.7rem; color: var(--gray);"><?= date('d M', strtotime($a['hired_at'])) ?></p>
                            <?php elseif (!empty($a['rejected_at'])): ?>
                                <div style="width: 32px; height: 32px; background: var(--red); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                    <i class="fas fa-times-circle" style="font-size: 0.8rem;"></i>
                                </div>
                                <p style="font-size: 0.75rem; font-weight: 600; color: var(--red);">Not Selected</p>
                                <p style="font-size: 0.7rem; color: var(--gray);"><?= date('d M', strtotime($a['rejected_at'])) ?></p>
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; background: #e5e7eb; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px;">
                                    <i class="fas fa-hourglass" style="font-size: 0.8rem;"></i>
                                </div>
                                <p style="font-size: 0.75rem; font-weight: 600; color: var(--gray-light);">Result</p>
                                <p style="font-size: 0.7rem; color: var(--gray);">Pending</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div style="display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; flex-wrap: wrap;">
                        
                        <?php if (in_array($a['status'], ['shortlisted', 'interview', 'hired'])): ?>
                            <a href="chat.php?app_id=<?= (int)$a['id'] ?>" class="btn-sm btn-green"><i class="fas fa-comments"></i> Chat</a>
                            
                            <?php if (!empty($a['employer_phone'])): ?>
                                <a href="https://wa.me/91<?= h(preg_replace('/\D/', '', $a['employer_phone'])) ?>" target="_blank" class="btn-sm btn-outline-green"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <a href="job.php?id=<?= (int)$a['job_id'] ?>" class="btn-sm btn-gray"><i class="fas fa-eye"></i> View Job</a>

                        <?php if ($a['status'] === 'pending'): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to withdraw this application?');" style="margin-left: auto;">
                                <input type="hidden" name="app_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="withdraw_app" value="1" class="btn-sm btn-red" style="background:none; border:1px solid #fca5a5; color:var(--red);"><i class="fas fa-times"></i> Withdraw</button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>

<?php include 'inc/foot.php'; ?>