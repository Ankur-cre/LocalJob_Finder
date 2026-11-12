<?php
require_once 'inc/auth.php';

$title = t('auth.admin_login') . " — " . SITE_NAME;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // 1. MASTER FALLBACK (Direct Login Bypass)
    // Agar user admin@localjobs.com aur admin123 dala, toh direct allow karo
    if ($email === 'admin@localjobs.com' && $pass === 'admin123') {
        
        // Check karo ki database me admin hai ya nahi
        $stmt = $db->prepare("SELECT * FROM users WHERE email=?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // Agar account nahi hai, toh naya bana do
        if (!$user) {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $ins = $db->prepare("INSERT INTO users (name, email, phone, password, role, status) VALUES ('Super Admin', ?, '9999999999', ?, 'admin', 'active')");
            $ins->bind_param('ss', $email, $hash);
            $ins->execute();
            $uid = $ins->insert_id;
            $name = 'Super Admin';
        } else {
            // Agar account hai lekin password galat hai, toh update kar do
            if (!password_verify($pass, $user['password'])) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $upd = $db->prepare("UPDATE users SET password=?, role='admin', status='active' WHERE email=?");
                $upd->bind_param('ss', $hash, $email);
                $upd->execute();
            }
            $uid = $user['id'];
            $name = $user['name'];
        }

        // Login session set karo
        $_SESSION['uid']  = $uid;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = 'admin';
        
        header("Location: admin.php");
        exit;
    }

    // 2. NORMAL LOGIN LOGIC (agar koi dusra admin ho toh)
    $stmt = $db->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['uid']  = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = 'admin';
        header("Location: admin.php");
        exit;
    }

    $error = "Invalid admin credentials.";
}

include 'inc/head.php';
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="auth-icon" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2><?= t('auth.admin_login') ?></h2>
            <p class="auth-sub">Secure access for administrators</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> <?= t('auth.email') ?></label>
                <input type="email" name="email" placeholder="admin@localjobs.com"
                       value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> <?= t('auth.password') ?></label>
                <div class="pass-wrap">
                    <input type="password" name="password" id="adminPass" placeholder="Enter password" required>
                    <button type="button" class="pass-toggle" onclick="togglePass('adminPass', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-blue btn-block" style="background:#0f172a;">
                <i class="fas fa-sign-in-alt"></i> <?= t('auth.login_btn') ?>
            </button>
        </form>

        <p class="auth-footer">
            <a href="login.php">← Back to normal login</a>
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