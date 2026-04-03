<?php
// ============================================================
//  manual_entry.php — Manually enter a log line for analysis
// ============================================================
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();

// Sirf Admin access kar sakta hai
if ($_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php?err=unauthorized');
    exit;
}

$db = getDB();
$msg = '';
$detected = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip     = trim($_POST['ip_address']  ?? '');
    $url    = trim($_POST['request_url'] ?? '');
    $status = trim($_POST['status_code'] ?? '');

    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $msg = '<div class="alert alert-danger">⚠️ Please enter a valid IP address.</div>';
    } elseif ($url === '') {
        $msg = '<div class="alert alert-danger">⚠️ Request URL cannot be empty.</div>';
    } else {
        if (isBlocked($ip)) {
            $msg = '<div class="alert alert-warning">🚫 This IP is already blocked. Log entry skipped.</div>';
        } else {
            $payload = "$ip $url $status";
            $match   = analyzePayload($payload);

            if ($match) {
                $stmt = $db->prepare(
                    "INSERT INTO attack_logs (ip_address, request_url, attack_type, severity) VALUES (?,?,?,?)"
                );
                $stmt->bind_param('ssss', $ip, $url, $match['attack_type'], $match['severity']);
                $stmt->execute();
                $stmt->close();
                autoBlockIfNeeded($ip);
                $detected = $match;
                $msg = '<div class="alert alert-danger">🚨 Attack detected and logged!</div>';
            } else {
                // Log as benign
                $type = 'Unknown / Benign';
                $sev  = 'Low';
                $stmt = $db->prepare(
                    "INSERT INTO attack_logs (ip_address, request_url, attack_type, severity) VALUES (?,?,?,?)"
                );
                $stmt->bind_param('ssss', $ip, $url, $type, $sev);
                $stmt->execute();
                $stmt->close();
                $msg = '<div class="alert alert-success">✅ Entry logged. No suspicious patterns detected.</div>';
            }
        }
    }
}

layout_start('Manual Log Entry', 'manual');
?>

<div class="page-header">
  <div>
    <h2>✏️ Manual Log Entry</h2>
    <p>Enter a request manually for instant analysis</p>
  </div>
  <a href="view_logs.php" class="btn btn-outline btn-sm">📋 View All Logs</a>
</div>

<?= $msg ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">

  <!-- Entry Form -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📝 Request Details</div>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>IP Address <span style="color:var(--danger);">*</span></label>
          <input type="text" name="ip_address" class="form-control"
                 placeholder="e.g. 192.168.1.100"
                 value="<?= htmlspecialchars($_POST['ip_address'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Request URL / Payload <span style="color:var(--danger);">*</span></label>
          <textarea name="request_url" class="form-control" rows="4"
                    placeholder="/search?q=' OR '1'='1--"
                    required style="resize:vertical;"><?= htmlspecialchars($_POST['request_url'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>HTTP Status Code</label>
          <select name="status_code" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach ([200,301,302,400,401,403,404,500,502,503] as $code): ?>
            <option value="<?= $code ?>" <?= ($_POST['status_code']??'')==$code?'selected':'' ?>><?= $code ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem;">
          🔍 Analyze &amp; Log Entry
        </button>
      </form>
    </div>
  </div>

  <!-- Detection Result + Quick Examples -->
  <div>
    <?php if ($detected): ?>
    <div class="card" style="margin-bottom:1rem;border:1px solid var(--danger);">
      <div class="card-header" style="background:rgba(233,69,96,.1);">
        <div class="card-title" style="color:var(--danger);">🚨 Threat Detected</div>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:center;">
          <div style="background:var(--bg-secondary);padding:1rem;border-radius:8px;">
            <div style="font-size:1.8rem;margin-bottom:.25rem;">⚔️</div>
            <div style="font-size:.75rem;color:var(--text-muted);">Attack Type</div>
            <div style="font-weight:700;font-size:.95rem;"><?= htmlspecialchars($detected['attack_type']) ?></div>
          </div>
          <div style="background:var(--bg-secondary);padding:1rem;border-radius:8px;">
            <div style="font-size:1.8rem;margin-bottom:.25rem;">🎯</div>
            <div style="font-size:.75rem;color:var(--text-muted);">Severity</div>
            <div>
              <span class="badge badge-<?= strtolower($detected['severity']) ?> badge-dot" style="font-size:.9rem;">
                <?= $detected['severity'] ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <div class="card-title">💡 Quick Test Payloads</div>
      </div>
      <div class="card-body">
        <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:.75rem;">
          Click to auto-fill the URL field:
        </p>
        <?php
        $examples = [
            ["SQL Injection",       "/search?q=' OR '1'='1--",               "High"],
            ["XSS Attack",          "/page?msg=<script>alert('xss')</script>","High"],
            ["Path Traversal",      "/files/../../../etc/passwd",             "High"],
            ["Brute Force",         "/wp-login.php?user=admin&pass=admin123", "Medium"],
            ["Command Injection",   "/cmd?exec=; ls -la /",                  "High"],
            ["Benign Request",      "/products/shoes?color=blue&size=10",    "—"],
        ];
        ?>
        <?php foreach ($examples as $ex): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    background:var(--bg-secondary);border-radius:6px;padding:.5rem .75rem;
                    margin-bottom:.4rem;gap:.5rem;cursor:pointer;"
             onclick="document.querySelector('[name=request_url]').value='<?= addslashes($ex[1]) ?>'">
          <span style="font-size:.82rem;font-family:monospace;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars($ex[1]) ?>
          </span>
          <span class="badge badge-<?= $ex[2] === 'High' ? 'high' : ($ex[2] === 'Medium' ? 'medium' : 'info') ?>"
                style="flex-shrink:0;font-size:.7rem;"><?= $ex[2] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php layout_end(); ?>
