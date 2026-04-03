<?php
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/config/db.php';
$error = '';
$info  = '';

// Messages
if (!empty($_GET['msg'])) {
    if ($_GET['msg'] === 'registration_closed') {
        $info = 'Registration is closed. Only Admin can add new users.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if (!$email || !$pass) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = [
                'name'  => $user['name'],
                'role'  => $user['role'],
                'email' => $user['email']
            ];
            header('Location: index.php'); exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — CyberIDS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-orb auth-orb-1"></div>
<div class="auth-orb auth-orb-2"></div>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="shield-wrap">🛡️</div>
      <h1>CYBER IDS</h1>
      <p>Intrusion Detection System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($info): ?>
    <div class="alert alert-warning">🔒 <?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <?php if (!empty($_GET['registered'])): ?>
    <div class="alert alert-success">✅ Account created! Please login.</div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="admin@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block"
              style="margin-top:1.2rem;padding:.85rem;">
        🔐 SIGN IN
      </button>
    </form>

    <p style="text-align:center;margin-top:1.3rem;font-size:.82rem;color:var(--muted);">
      No account? <a href="register.php" style="color:var(--cyan);">Register here</a>
    </p>

    <div style="margin-top:1rem;padding:.85rem;background:rgba(0,245,255,0.04);
                border:1px solid var(--border);border-radius:10px;
                font-size:.78rem;color:var(--muted);font-family:var(--font-mono);">
      <span style="color:var(--cyan);">// NOTE:</span> First registered user gets Admin access.
    </div>
  </div>
</div>
<script>
(function(){
  var t = localStorage.getItem('cyberTheme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);
})();
</script>
</body>
</html>
