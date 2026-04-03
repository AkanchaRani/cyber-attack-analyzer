<?php
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
require_once __DIR__ . '/config/db.php';
$error = '';

// ── Check karo koi user already hai ya nahi ──────────────────
$db = getDB();
$userCount = $db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$isFirstUser = ($userCount == 0);

// ── Agar already users hain, registration band ────────────────
// Sirf pehla user register kar sakta hai (wo admin banega)
// Baad mein admin hi naye users add karega
if (!$isFirstUser && empty($_GET['invite'])) {
    // Registration blocked — redirect to login
    header('Location: login.php?msg=registration_closed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm']  ?? '');

    if (!$name || !$email || !$pass || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->bind_param('s', $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email already registered.';
        } else {
            // Pehla user = admin, baaki = analyst
            $role = $isFirstUser ? 'admin' : 'analyst';
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins  = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            $ins->bind_param('ssss', $name, $email, $hash, $role);
            if ($ins->execute()) {
                header('Location: login.php?registered=1'); exit;
            } else {
                $error = 'Registration failed. Try again.';
            }
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register — CyberIDS</title>
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
      <h1><?= $isFirstUser ? 'SETUP ADMIN' : 'CREATE ACCOUNT' ?></h1>
      <p><?= $isFirstUser
            ? 'First user becomes Administrator'
            : 'Invited by Admin — Analyst access only' ?></p>
    </div>

    <!-- Role badge -->
    <div style="text-align:center;margin-bottom:1.2rem;">
      <span class="badge <?= $isFirstUser ? 'badge-high' : 'badge-medium' ?>"
            style="font-size:.85rem;padding:.4rem 1rem;">
        <?= $isFirstUser ? '👑 Role: ADMIN' : '🔍 Role: ANALYST' ?>
      </span>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($isFirstUser): ?>
    <div class="alert alert-info">
      ℹ️ You are the <strong>first user</strong> — you will get full Admin access.
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" class="form-control"
               placeholder="John Smith" required
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" class="form-control"
               placeholder="admin@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password <span style="color:var(--muted);font-weight:400;">(min 6 chars)</span></label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block"
              style="margin-top:1.2rem;padding:.85rem;">
        <?= $isFirstUser ? '👑 Create Admin Account' : '✅ Create Analyst Account' ?>
      </button>
    </form>
    <p style="text-align:center;margin-top:1.3rem;font-size:.85rem;color:var(--muted);">
      Already have an account? <a href="login.php" style="color:var(--cyan);">Sign in</a>
    </p>
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
