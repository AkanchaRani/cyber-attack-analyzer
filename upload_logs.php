<?php
// ============================================================
//  upload_logs.php — Upload a .txt log file for analysis
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
$results = [];
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logfile'])) {
    $file = $_FILES['logfile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Error code: ' . $file['error'];
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['txt','log'])) {
        $error = 'Only .txt and .log files are accepted.';
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'File size must not exceed 5 MB.';
    } else {
        $lines    = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $detected = 0;
        $skipped  = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Try to extract IP from beginning of line (common log formats)
            preg_match('/^(\d{1,3}(?:\.\d{1,3}){3})/', $line, $ipMatch);
            $ip = $ipMatch[1] ?? '0.0.0.0';

            if (isBlocked($ip)) { $skipped++; continue; }

            $match = analyzePayload($line);
            if ($match) {
                $stmt = $db->prepare(
                    "INSERT INTO attack_logs (ip_address, request_url, attack_type, severity) VALUES (?,?,?,?)"
                );
                $url = mb_substr($line, 0, 500);
                $stmt->bind_param('ssss', $ip, $url, $match['attack_type'], $match['severity']);
                $stmt->execute();
                $stmt->close();
                autoBlockIfNeeded($ip);
                $detected++;
                $results[] = ['ip' => $ip, 'type' => $match['attack_type'], 'severity' => $match['severity'], 'line' => mb_substr($line, 0, 100)];
            }
        }

        $total = count($lines);
        $success = "Analyzed <strong>$total</strong> lines. Detected <strong>$detected</strong> attacks, skipped <strong>$skipped</strong> blocked IPs.";
    }
}

layout_start('Upload Log File', 'upload');
?>

<div class="page-header">
  <div>
    <h2>📤 Upload Log File</h2>
    <p>Upload a .txt or .log file to analyze for intrusion patterns</p>
  </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">

  <!-- Upload Form -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📂 Select Log File</div>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label>Log File (.txt or .log, max 5MB)</label>
          <input type="file" name="logfile" class="form-control" accept=".txt,.log" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          🚀 Upload &amp; Analyze
        </button>
      </form>
    </div>
  </div>

  <!-- Format Guide -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📖 Supported Log Formats</div>
    </div>
    <div class="card-body" style="font-size:.85rem;color:var(--text-muted);line-height:1.8;">
      <p style="margin-bottom:.75rem;">The analyzer detects suspicious patterns in any text log. Best results with:</p>
      <code style="display:block;background:var(--bg-secondary);padding:.75rem;border-radius:6px;font-size:.8rem;white-space:pre-wrap;">
192.168.1.100 GET /search?q=' OR '1'='1 200
10.0.0.5 POST /login.php?user=admin 401
172.16.0.8 GET /../../etc/passwd 404
192.168.1.50 GET /page?x=&lt;script&gt;alert(1)&lt;/script&gt; 200
      </code>
      <p style="margin-top:.75rem;">One log entry per line. IP address should be at the start of the line.</p>
    </div>
  </div>

</div>

<!-- Sample log download -->
<div class="card" style="margin-bottom:1.25rem;">
  <div class="card-header">
    <div class="card-title">📝 Download Sample Log File</div>
  </div>
  <div class="card-body">
    <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1rem;">
      Don't have a log file? Download this sample to test the analyzer:
    </p>
    <a href="sample_log.php" class="btn btn-accent" target="_blank">⬇️ Download sample_attack.log</a>
  </div>
</div>

<!-- Detection Results -->
<?php if (!empty($results)): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">🎯 Detection Results</div>
    <span class="badge badge-high"><?= count($results) ?> threats found</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>IP Address</th><th>Attack Type</th><th>Severity</th><th>Log Snippet</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $i => $r): ?>
        <tr>
          <td style="color:var(--text-muted);"><?= $i+1 ?></td>
          <td><code class="ip-pill"><?= htmlspecialchars($r['ip']) ?></code></td>
          <td><?= htmlspecialchars($r['type']) ?></td>
          <td><span class="badge badge-<?= strtolower($r['severity']) ?> badge-dot"><?= $r['severity'] ?></span></td>
          <td style="font-size:.8rem;font-family:monospace;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
              title="<?= htmlspecialchars($r['line']) ?>">
            <?= htmlspecialchars($r['line']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php layout_end(); ?>
