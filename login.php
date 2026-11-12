<?php
require_once 'inc/auth.php';

if (logged_in()) {
    $dest = role() === 'admin' ? 'admin.php'
          : (role() === 'employer' ? 'employer.php' : 'seeker-home.php');
    header("Location: $dest");
    exit;
}

$title = t('nav.login') . " — " . SITE_NAME;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = "Email and password are required.";
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($pass, $user['password'])) {
            $error = "Invalid email or password.";
        } 
        elseif ($user['role'] === 'employer') {
            // Employer direct login allowed (pending status block nahi)
            $_SESSION['uid']  = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = 'employer';

            header("Location: employer.php");
            exit;
        } 
        elseif ($user['status'] === 'pending') {
            $error = "Your account is pending approval. Please wait for admin approval.";
        } 
        elseif ($user['status'] === 'rejected') {
            $error = "Your account has been rejected. Please contact support.";
        } 
        else {
            // Normal Active Login
            $_SESSION['uid']  = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            $dest = $user['role'] === 'admin' ? 'admin.php'
                  : ($user['role'] === 'employer' ? 'employer.php' : 'seeker-home.php');
            header("Location: $dest");
            exit;
        }
    }
}

include 'inc/head.php';
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="auth-icon"><i class="fas fa-sign-in-alt"></i></div>
            <h2><?= t('auth.welcome_back') ?></h2>
            <p class="auth-sub"><?= t('auth.login_subtitle') ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <!-- ★ SUCCESS MESSAGE (from account delete / other actions) ★ -->
        <?php if (isset($_GET['msg'])): ?>
            <?php
              $msg_type = $_GET['type'] ?? 'success';
              $msg_class = ($msg_type === 'info') ? 'alert-info' : 'alert-ok';
              $msg_icon  = ($msg_type === 'info') ? 'fa-info-circle' : 'fa-check-circle';
            ?>
            <div class="alert <?= $msg_class ?>" style="display:flex; align-items:flex-start; gap:10px;">
                <i class="fas <?= $msg_icon ?>" style="margin-top:3px;"></i>
                <div style="flex:1;">
                    <?php if ($msg_type === 'success'): ?>
                        <strong style="display:block; margin-bottom:2px;">Success</strong>
                    <?php endif; ?>
                    <span><?= h($_GET['msg']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> <?= t('auth.email') ?></label>
                <input type="email" name="email" placeholder="you@example.com"
                       value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> <?= t('auth.password') ?></label>
                <div class="pass-wrap">
                    <input type="password" name="password" id="loginPass" placeholder="Enter password" required>
                    <button type="button" class="pass-toggle" onclick="togglePass('loginPass', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-blue btn-block">
                <i class="fas fa-sign-in-alt"></i> <?= t('auth.login_btn') ?>
            </button>
        </form>

        <div class="auth-divider"><span><?= t('auth.new_here') ?></span></div>

        <div class="auth-links">
            <a href="reg-seeker.php" class="btn-outline-green">
                <i class="fas fa-user"></i> <?= t('auth.register_seeker') ?>
            </a>
            <a href="reg-employer.php" class="btn-outline-blue">
                <i class="fas fa-building"></i> <?= t('auth.register_employer') ?>
            </a>
        </div>

        <p class="auth-footer">
            <i class="fas fa-shield-alt"></i>
            <a href="admin-auth.php"><?= t('auth.admin_login') ?></a>
        </p>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}
</script>

<?php include 'inc/foot.php'; ?>