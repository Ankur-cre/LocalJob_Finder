<?php
// Ye file sirf naya password hash generate karne ke liye hai
$new_password = 'admin123'; // Yahan apna naya password likho
$hash = password_hash($new_password, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html>
<head><title>Password Hash Generator</title></head>
<body style="font-family:sans-serif; padding:20px;">
    <h2>Password Hash Generator</h2>
    <p><strong>Password:</strong> <?= htmlspecialchars($new_password) ?></p>
    <p><strong>Naya Hash:</strong><br><textarea rows="3" cols="80"><?= htmlspecialchars($hash) ?></textarea></p>
    <p style="color:red;"><strong>Warning:</strong> Is file ko kaam hone ke baad turant delete kar dena!</p>
</body>
</html>