<?php
require_once 'inc/auth.php';
$title = t('auth.register_seeker', 'Register') . " — " . SITE_NAME;
$error = '';

if (logged_in()) { header("Location: index.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $district= trim($_POST['district'] ?? '');
    $state   = trim($_POST['state'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $pass2   = $_POST['password2'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || $city === '' || $district === '' || $state === '' || $pass === '') {
        $error = "Saare required fields bharo (Location bhi zaroori hai).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email daalo.";
    } elseif (strlen($pass) < 6) {
        $error = "Password kam se kam 6 characters ka hona chahiye.";
    } elseif ($pass !== $pass2) {
        $error = "Dono passwords match nahi kar rahe.";
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Yeh email pehle se registered hai.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $db->prepare("
                INSERT INTO users (name, email, phone, city, district, state, password, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'seeker', 'active')
            ");
            $ins->bind_param('sssssss', $name, $email, $phone, $city, $district, $state, $hash);
            if ($ins->execute()) {
                header("Location: login.php?msg=" . urlencode("Registration successful! Ab login karo."));
                exit;
            } else {
                $error = "Registration fail ho gaya. Try again.";
            }
        }
    }
}

include 'inc/head.php';
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-head">
            <div class="auth-icon auth-icon-green"><i class="fas fa-user-plus"></i></div>
            <h2>Create Job Seeker Account</h2>
            <p class="auth-sub">Find your next job — free to register</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-err"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Full Name *</label>
                <input type="text" name="name" placeholder="e.g. Ram Sharma" value="<?= h($_POST['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" name="email" placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-phone"></i> Phone *</label>
                <input type="text" name="phone" placeholder="9876543210" pattern="[0-9]{10}" value="<?= h($_POST['phone'] ?? '') ?>" required>
                <small>10-digit mobile number</small>
            </div>

            <!-- NEW LOCATION FIELDS -->
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-city"></i> City *</label>
                    <input type="text" name="city" placeholder="e.g. Nichlaul" value="<?= h($_POST['city'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-map"></i> District *</label>
                    <input type="text" name="district" placeholder="e.g. Maharajganj" value="<?= h($_POST['district'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label><i class="fas fa-map-marked-alt"></i> State *</label>
                <select name="state" required>
                    <option value="">-- Select State --</option>
                    <?php foreach(['Uttar Pradesh', 'Bihar', 'Delhi', 'Maharashtra', 'Madhya Pradesh', 'Rajasthan', 'Gujarat', 'Punjab', 'Haryana', 'Uttarakhand', 'West Bengal'] as $s): ?>
                        <option value="<?= $s ?>" <?= (($_POST['state'] ?? '') === $s) ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <div class="pass-wrap">
                        <input type="password" name="password" id="rPass" placeholder="Min 6 chars" required>
                        <button type="button" class="pass-toggle" onclick="togglePass('rPass', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password *</label>
                    <div class="pass-wrap">
                        <input type="password" name="password2" id="rPass2" placeholder="Retype password" required>
                        <button type="button" class="pass-toggle" onclick="togglePass('rPass2', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-green btn-block"><i class="fas fa-user-plus"></i> Register</button>
        </form>

        <p class="auth-footer">Already have an account? <a href="login.php">Login</a></p>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
    else { inp.type = 'password'; btn.innerHTML = '<i class="fas fa-eye"></i>'; }
}
</script>

<?php include 'inc/foot.php'; ?>