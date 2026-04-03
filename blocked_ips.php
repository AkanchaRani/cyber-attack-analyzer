<?php
// ============================================================
//  blocked_ips.php — View and manage blocked IP addresses
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$db = getDB();
$msg = '';

// ── Manually block an IP ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_ip'])) {
    $ip     = trim($_POST['ip_address'] ?? '');
    $reason = trim($_POST['reason'] ?? 'Manually blocked by administrator');

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $msg = '<div class="alert alert-danger">Invalid IP address format.</div>';
    } else {
        $stmt = $db->prepare("INSERT IGNORE INTO blocked_ips (ip_address, reason) VALUES (?,?)");
        $stmt->bind_param('ss', $ip, $reason);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0
            ? '<div class="alert alert-success">✅ IP blocked successfully.</div>'
            : '<div class="alert alert-warning">⚠️ IP already in blocklist.</div>';
        $stmt->close();
    }
}

// ── Unblock IP ────────────────────────────────────────────────
if (isset($_GET['unblock'])) {
    $id   = (int)$_GET['unblock'];
    $stmt = $db->prepare("DELETE FROM blocked_ips WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
    header('Location: blocked_ips.php?unblocked=1'); exit;
}

if (isset($_GET['unblocked'])) {
    $msg = '<div class="alert alert-success">✅ IP unblocked successfully.</div>';
}

// ── Fetch list ────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $db->prepare("SELECT * FROM blocked_ips WHERE ip_address LIKE ? ORDER BY blocked_at DESC");
    $like = "%$search%"; $stmt->bind_param('s', $like); $stmt->execute();
    $blocked = $stmt->get_result();
} else {
    $blocked = $db->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC");
}

$totalBlocked = $db->query("SELECT COUNT(*) as c FROM blocked_ips")->fetch_assoc()['c'];

layout_start('Blocked IPs', 'blocked');
?>

<?= $msg ?>

<div class="page-header">
  <div>
    <h2>🚫 Blocked IPs</h2>
    <p><?= $totalBlocked ?> IP addresses currently blocked</p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:1rem;margin-bottom:1.25rem;">

  <!-- Manual Block Form -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">➕ Manually Block IP</div>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>IP Address</label>
          <input type="text" name="ip_address" class="form-control"
                 placeholder="e.g. 203.0.113.1" required>
        </div>
        <div class="form-group">
          <label>Reason</label>
          <input type="text" name="reason" class="form-control"
                 placeholder="Reason for blocking..." value="Manually blocked by administrator">
        </div>
        <button type="submit" name="block_ip" class="btn btn-danger btn-block">
          🚫 Block IP
        </button>
      </form>
    </div>
  </div>

  <!-- Search -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🔍 Search Blocked IPs</div>
    </div>
    <div class="card-body">
      <form method="GET">
        <div class="form-group">
          <label>Search by IP</label>
          <input type="text" name="search" class="form-control"
                 placeholder="Enter IP or partial IP..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div style="display:flex;gap:.5rem;">
          <button type="submit" class="btn btn-primary">🔍 Search</button>
          <a href="blocked_ips.php" class="btn btn-outline">✕ Clear</a>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- Blocked IPs Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🛡️ Blocklist</div>
    <span class="badge badge-blocked"><?= $totalBlocked ?> entries</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>IP Address</th>
          <th>Reason</th>
          <th>Attack Count</th>
          <th>Blocked At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($blocked->num_rows === 0): ?>
        <tr>
          <td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
            No blocked IPs found.
          </td>
        </tr>
        <?php else: while ($r = $blocked->fetch_assoc()):
          $cnt = $db->prepare("SELECT COUNT(*) as c FROM attack_logs WHERE ip_address = ?");
          $cnt->bind_param('s', $r['ip_address']); $cnt->execute();
          $atkCount = $cnt->get_result()->fetch_assoc()['c']; $cnt->close();
        ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $r['id'] ?></td>
          <td><code class="ip-pill"><?= htmlspecialchars($r['ip_address']) ?></code></td>
          <td style="font-size:.85rem;"><?= htmlspecialchars($r['reason']) ?></td>
          <td>
            <a href="view_logs.php?search=<?= urlencode($r['ip_address']) ?>" class="badge badge-high">
              <?= $atkCount ?> attacks
            </a>
          </td>
          <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars($r['blocked_at']) ?></td>
          <td>
            <a href="?unblock=<?= $r['id'] ?>"
               onclick="return confirm('Unblock <?= htmlspecialchars($r['ip_address']) ?>?')"
               class="btn btn-success btn-sm">✅ Unblock</a>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_end(); ?>
