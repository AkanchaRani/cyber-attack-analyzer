<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

// ── Sirf Admin access kar sakta hai ──────────────────────────
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php?err=unauthorized'); exit;
}

$db  = getDB();
$msg = '';

// ── Naya user add karo ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name  = trim($_POST['name']     ?? '');
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $role  = in_array($_POST['role'], ['admin','analyst']) ? $_POST['role'] : 'analyst';

    if (!$name || !$email || !$pass) {
        $msg = '<div class="alert alert-danger">⚠️ All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '<div class="alert alert-danger">⚠️ Invalid email address.</div>';
    } elseif (strlen($pass) < 6) {
        $msg = '<div class="alert alert-danger">⚠️ Password must be at least 6 characters.</div>';
    } else {
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->bind_param('s', $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = '<div class="alert alert-warning">⚠️ Email already registered.</div>';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins  = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
            $ins->bind_param('ssss', $name, $email, $hash, $role);
            $ins->execute();
            $msg = '<div class="alert alert-success">✅ User <strong>' . htmlspecialchars($name) . '</strong> added successfully as <strong>' . ucfirst($role) . '</strong>.</div>';
            $ins->close();
        }
        $chk->close();
    }
}

// ── User delete karo ─────────────────────────────────────────
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Apne aap ko delete nahi kar sakte
    if ($delId === (int)$_SESSION['user_id']) {
        $msg = '<div class="alert alert-danger">❌ You cannot delete your own account.</div>';
    } else {
        $del = $db->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param('i', $delId); $del->execute(); $del->close();
        header('Location: manage_users.php?deleted=1'); exit;
    }
}

// ── Role change karo ─────────────────────────────────────────
if (isset($_GET['promote']) || isset($_GET['demote'])) {
    $uid     = (int)($_GET['promote'] ?? $_GET['demote']);
    $newRole = isset($_GET['promote']) ? 'admin' : 'analyst';
    if ($uid !== (int)$_SESSION['user_id']) {
        $upd = $db->prepare("UPDATE users SET role=? WHERE id=?");
        $upd->bind_param('si', $newRole, $uid); $upd->execute(); $upd->close();
    }
    header('Location: manage_users.php?updated=1'); exit;
}

if (!empty($_GET['deleted'])) $msg = '<div class="alert alert-success">✅ User deleted successfully.</div>';
if (!empty($_GET['updated'])) $msg = '<div class="alert alert-success">✅ User role updated.</div>';

// ── Fetch all users ───────────────────────────────────────────
$users = $db->query("SELECT * FROM users ORDER BY role ASC, created_at ASC");

layout_start('Manage Users', 'users');
?>

<?= $msg ?>

<div class="page-header">
  <div>
    <h2>👥 User Management</h2>
    <p>Add users, assign roles, control access</p>
  </div>
</div>

<!-- Role Explanation -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.4rem;">
  <div class="card" style="border-color:rgba(255,45,85,0.2);">
    <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
      <div style="font-size:1.8rem;">👑</div>
      <div>
        <div style="font-weight:700;color:var(--red);font-family:var(--font-mono);font-size:.85rem;margin-bottom:.4rem;">ADMIN ROLE</div>
        <div style="font-size:.83rem;color:var(--muted);line-height:1.7;">
          ✅ Dashboard dekh sakta hai<br>
          ✅ Logs upload/analyze kar sakta hai<br>
          ✅ IPs block/unblock kar sakta hai<br>
          ✅ Users add/delete kar sakta hai<br>
          ✅ Attacks generate kar sakta hai
        </div>
      </div>
    </div>
  </div>
  <div class="card" style="border-color:rgba(255,214,10,0.2);">
    <div class="card-body" style="display:flex;gap:1rem;align-items:flex-start;">
      <div style="font-size:1.8rem;">🔍</div>
      <div>
        <div style="font-weight:700;color:var(--yellow);font-family:var(--font-mono);font-size:.85rem;margin-bottom:.4rem;">ANALYST ROLE</div>
        <div style="font-size:.83rem;color:var(--muted);line-height:1.7;">
          ✅ Dashboard dekh sakta hai<br>
          ✅ Attack logs dekh sakta hai<br>
          ✅ Blocked IPs dekh sakta hai<br>
          ❌ Users manage nahi kar sakta<br>
          ❌ Attacks generate nahi kar sakta
        </div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1rem;">

  <!-- ADD USER FORM -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">➕ Add New User</div>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" class="form-control" placeholder="John Smith" required>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
        </div>
        <div class="form-group">
          <label>Password <span style="color:var(--muted);font-weight:400">(min 6 chars)</span></label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label>Assign Role</label>
          <select name="role" class="form-control">
            <option value="analyst">🔍 Analyst (Limited access)</option>
            <option value="admin">👑 Admin (Full access)</option>
          </select>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary btn-block" style="margin-top:.5rem;">
          ➕ Add User
        </button>
      </form>
    </div>
  </div>

  <!-- USERS TABLE -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📋 All Users</div>
      <span class="badge badge-info"><?= $users->num_rows ?> users</span>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($u = $users->fetch_assoc()):
            $isSelf = ($u['id'] == $_SESSION['user_id']);
          ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:.7rem;">
                <div style="width:32px;height:32px;border-radius:8px;
                            background:<?= $u['role']==='admin' ? 'linear-gradient(135deg,var(--red),var(--purple))' : 'linear-gradient(135deg,var(--yellow),var(--cyan2))' ?>;
                            display:flex;align-items:center;justify-content:center;
                            font-family:var(--font-head);font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($u['name'],0,1)) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.88rem;">
                    <?= htmlspecialchars($u['name']) ?>
                    <?php if($isSelf): ?>
                    <span style="font-size:.65rem;color:var(--cyan);font-family:var(--font-mono);"> (you)</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size:.75rem;color:var(--muted);font-family:var(--font-mono);"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge <?= $u['role']==='admin' ? 'badge-high' : 'badge-medium' ?>">
                <?= $u['role']==='admin' ? '👑 Admin' : '🔍 Analyst' ?>
              </span>
            </td>
            <td style="font-size:.78rem;color:var(--muted);font-family:var(--font-mono);">
              <?= date('M j, Y', strtotime($u['created_at'])) ?>
            </td>
            <td>
              <?php if (!$isSelf): ?>
              <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                <?php if ($u['role'] === 'analyst'): ?>
                <a href="?promote=<?= $u['id'] ?>"
                   onclick="return confirm('Promote <?= htmlspecialchars($u['name']) ?> to Admin?')"
                   class="btn btn-success btn-sm">👑 Promote</a>
                <?php else: ?>
                <a href="?demote=<?= $u['id'] ?>"
                   onclick="return confirm('Demote <?= htmlspecialchars($u['name']) ?> to Analyst?')"
                   class="btn btn-warning btn-sm">🔍 Demote</a>
                <?php endif; ?>
                <a href="?delete=<?= $u['id'] ?>"
                   onclick="return confirm('Delete user <?= htmlspecialchars($u['name']) ?>? This cannot be undone.')"
                   class="btn btn-danger btn-sm">🗑️</a>
              </div>
              <?php else: ?>
              <span style="font-size:.78rem;color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php layout_end(); ?>
