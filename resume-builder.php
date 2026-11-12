<?php
require_once 'inc/auth.php';
must_role('seeker');
$title = "Resume Builder — " . SITE_NAME;
$uid = $_SESSION['uid'];

$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$skills = $db->query("SELECT skill_name FROM seeker_skills WHERE seeker_id=$uid")->fetch_all(MYSQLI_ASSOC);
$edu = $db->query("SELECT * FROM seeker_education WHERE seeker_id=$uid")->fetch_all(MYSQLI_ASSOC);
$exp = $db->query("SELECT * FROM seeker_experience WHERE seeker_id=$uid")->fetch_all(MYSQLI_ASSOC);

include 'inc/head.php';
?>

<style>
    @media print {
        .no-print { display: none !important; }
        body { background: #fff; }
        .resume-paper { box-shadow: none !important; border: none !important; }
    }
</style>

<div style="background: #e5e7eb; padding: 30px 0;">
    <div class="wrap" style="max-width: 900px;">
        
        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1 style="font-size: 1.4rem;"><i class="fas fa-file-pdf" style="color:var(--red);"></i> Resume Builder</h1>
            <button onclick="window.print()" class="btn-blue"><i class="fas fa-print"></i> Print / Save as PDF</button>
        </div>

        <!-- RESUME PAPER PREVIEW -->
        <div class="resume-paper" style="background:#fff; padding: 50px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 4px; color: #000;">
            
            <!-- Header -->
            <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px;">
                <h1 style="font-size: 2.5rem; margin-bottom: 5px; font-family: serif;"><?= h($user['name']) ?></h1>
                <p style="font-size: 1rem; color: #333;"><?= h($user['headline'] ?: 'Professional') ?></p>
                <p style="font-size: 0.9rem; color: #555; margin-top: 5px;">
                    <?= h($user['email']) ?> | <?= h($user['phone']) ?> | <?= h($user['city']) ?>, <?= h($user['state']) ?>
                </p>
            </div>

            <!-- Summary -->
            <?php if ($user['about_me']): ?>
            <div style="margin-bottom: 25px;">
                <h3 style="font-size: 1.2rem; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Professional Summary</h3>
                <p style="line-height: 1.6; font-size: 0.95rem;"><?= nl2br(h($user['about_me'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Skills -->
            <?php if (count($skills) > 0): ?>
            <div style="margin-bottom: 25px;">
                <h3 style="font-size: 1.2rem; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Key Skills</h3>
                <p style="font-size: 0.95rem; line-height: 1.6;">
                    <?php echo implode(' &bull; ', array_column($skills, 'skill_name')); ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Experience -->
            <?php if (count($exp) > 0): ?>
            <div style="margin-bottom: 25px;">
                <h3 style="font-size: 1.2rem; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Work Experience</h3>
                <?php foreach($exp as $e): ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display:flex; justify-content:space-between;">
                            <strong><?= h($e['job_title']) ?></strong>
                            <span><?= h($e['start_date']) ?> - <?= $e['is_current'] ? 'Present' : h($e['end_date']) ?></span>
                        </div>
                        <p style="font-style: italic; color: #555;"><?= h($e['company_name']) ?></p>
                        <p style="font-size: 0.9rem; margin-top: 5px;"><?= nl2br(h($e['description'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Education -->
            <?php if (count($edu) > 0): ?>
            <div style="margin-bottom: 25px;">
                <h3 style="font-size: 1.2rem; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-transform: uppercase;">Education</h3>
                <?php foreach($edu as $ed): ?>
                    <div style="margin-bottom: 10px; display:flex; justify-content:space-between;">
                        <div>
                            <strong><?= h($ed['qualification']) ?></strong><br>
                            <span style="font-size:0.9rem; color:#555;"><?= h($ed['school_name']) ?></span>
                        </div>
                        <span><?= h($ed['passing_year']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'inc/foot.php'; ?>