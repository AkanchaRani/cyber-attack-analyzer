<?php
// ============================================================
//  view_logs.php — Attack logs with search, filter, CSV export
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

$db = getDB();

// ── Inputs ───────────────────────────────────────────────────
$search    = trim($_GET['search']    ?? '');
$severity  = trim($_GET['severity']  ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = "(ip_address LIKE ? OR request_url LIKE ? OR attack_type LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if (in_array($severity, ['High','Medium','Low'])) {
    $where[]  = "severity = ?";
    $params[] = $severity;
    $types   .= 's';
}
if ($date_from !== '') {
    $where[]  = "DATE(detected_at) >= ?";
    $params[] = $date_from;
    $types   .= 's';
}
if ($date_to !== '') {
    $where[]  = "DATE(detected_at) <= ?";
    $params[] = $date_to;
    $types   .= 's';
}
$whereSQL = implode(' AND ', $where);

// ── CSV Export ────────────────────────────────────────────────
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attack_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','IP Address','Request URL','Attack Type','Severity','Detected At']);
    $stmt = $db->prepare("SELECT * FROM attack_logs WHERE $whereSQL ORDER BY detected_at DESC");
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) fputcsv($out, $r);
    fclose($out);
    exit;
}

// ── Pagination ────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) as c FROM attack_logs WHERE $whereSQL");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total    = $countStmt->get_result()->fetch_assoc()['c'];
$pages    = max(1, ceil($total / $perPage));
$countStmt->close();

$dataStmt = $db->prepare("SELECT * FROM attack_logs WHERE $whereSQL ORDER BY detected_at DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$dataStmt->bind_param($allTypes, ...$allParams);
$dataStmt->execute();
$rows = $dataStmt->get_result();

layout_start('Attack Logs', 'logs');
?>

<div class="page-header">
  <div>
    <h2>📋 Attack Logs</h2>
    <p>Total records: <strong><?= $total ?></strong></p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>1])) ?>" class="btn btn-success btn-sm">⬇️ Export CSV</a>
    <a href="manual_entry.php" class="btn btn-accent btn-sm">✏️ Add Entry</a>
  </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <div class="form-group">
        <label>Search IP / URL / Type</label>
        <input type="text" name="search" class="form-control" placeholder="e.g. 192.168.1.1"
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="form-group">
        <label>Severity</label>
        <select name="severity" class="form-control">
          <option value="">All</option>
          <?php foreach (['High','Medium','Low'] as $s): ?>
          <option value="<?= $s ?>" <?= $severity === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
      </div>
      <div class="form-group">
        <label>Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
      </div>
      <div class="form-group">
        <label>&nbsp;</label>
        <div style="display:flex;gap:.4rem;">
          <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
          <a href="view_logs.php" class="btn btn-outline btn-sm">✕ Clear</a>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Logs Table -->
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>IP Address</th>
          <th>Request URL</th>
          <th>Attack Type</th>
          <th>Severity</th>
          <th>Detected At</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows->num_rows === 0): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted);">
            No logs match your filters.
          </td>
        </tr>
        <?php else: while ($r = $rows->fetch_assoc()):
          $isBlocked = isBlocked($r['ip_address']);
        ?>
        <tr>
          <td style="color:var(--text-muted);font-size:.8rem;"><?= $r['id'] ?></td>
          <td>
            <code class="ip-pill"><?= htmlspecialchars($r['ip_address']) ?></code>
            <?php if ($isBlocked): ?>
            <span class="badge badge-blocked" style="margin-left:.3rem;font-size:.65rem;">BLOCKED</span>
            <?php endif; ?>
          </td>
          <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem;"
              title="<?= htmlspecialchars($r['request_url']) ?>">
            <?= htmlspecialchars($r['request_url']) ?>
          </td>
          <td><?= htmlspecialchars($r['attack_type']) ?></td>
          <td><span class="badge badge-<?= strtolower($r['severity']) ?> badge-dot"><?= $r['severity'] ?></span></td>
          <td style="font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars($r['detected_at']) ?></td>
          <td>
            <?php if ($isBlocked): ?>
            <span class="badge badge-blocked">🚫 Blocked</span>
            <?php else: ?>
            <span class="badge badge-info">✓ Logged</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
    <span style="font-size:.85rem;color:var(--text-muted);margin-right:auto;">
      Page <?= $page ?> of <?= $pages ?>
    </span>
    <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
       class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php layout_end(); ?>
