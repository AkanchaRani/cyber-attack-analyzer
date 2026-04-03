<?php
function requireAuth(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . getBase() . 'login.php');
        exit;
    }
}

function getBase(): string {
    // All PHP files live in the project root folder (same level as assets/)
    // So relative path to assets is always just "assets/..."
    return '';
}

function layout_start(string $title, string $active = ''): void {
    $user  = $_SESSION['user'] ?? ['name' => 'Admin', 'role' => 'admin'];
    $init  = strtoupper(substr($user['name'], 0, 1));
    $base  = getBase();

    $isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

    $nav = [
        ['href'=>'index.php',          'icon'=>'📊','label'=>'Dashboard',       'key'=>'dashboard', 'all'=>true],
        ['href'=>'view_logs.php',       'icon'=>'📋','label'=>'Attack Logs',     'key'=>'logs',      'all'=>true],
        ['href'=>'blocked_ips.php',     'icon'=>'🚫','label'=>'Blocked IPs',     'key'=>'blocked',   'all'=>true],
        ['href'=>'upload_logs.php',     'icon'=>'📤','label'=>'Upload Logs',     'key'=>'upload',    'all'=>false],
        ['href'=>'manual_entry.php',    'icon'=>'✏️', 'label'=>'Manual Entry',   'key'=>'manual',    'all'=>false],
        ['href'=>'generate_attack.php', 'icon'=>'⚡','label'=>'Generate Attacks','key'=>'generate',  'all'=>false],
        ['href'=>'manage_users.php',    'icon'=>'👥','label'=>'Manage Users',    'key'=>'users',     'all'=>false],
    ];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> — CyberIDS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* INLINE FALLBACK — agar external CSS na aaye tab bhi layout sahi rahe */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Rajdhani',sans-serif;background:#020408;color:#e2e8f0;min-height:100vh}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🛡️</div>
    <div class="brand-text">
      <h2>CyberIDS</h2>
      <p>Intrusion Detection</p>
    </div>
    <div class="live-pill"><div class="live-dot"></div>LIVE</div>
  </div>

  <div class="sidebar-section">Navigation</div>
  <?php foreach ($nav as $item): ?>
  <?php if ($item['all'] || $isAdmin): ?>
  <a href="<?= $base . $item['href'] ?>" class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>">
    <span class="nav-icon"><?= $item['icon'] ?></span>
    <?= $item['label'] ?>
    <?php if($item['key']==='users' && $isAdmin): ?>
    <span class="nav-badge">ADMIN</span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
  <?php endforeach; ?>

  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= $init ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="user-role"><?= strtoupper($user['role']) ?></div>
      </div>
    </div>
    <a href="<?= $base ?>logout.php" class="btn btn-outline btn-sm btn-block">🚪 Sign Out</a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <button class="btn btn-outline btn-sm" style="display:none" id="menuBtn"
            onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
    <div class="topbar-actions">
      <div class="topbar-clock" id="clock">--:--:--</div>
      <button class="theme-btn" id="themeBtn" onclick="toggleTheme()">☀ Light</button>
    </div>
  </header>
  <div class="content">
<?php
}

function layout_end(): void { ?>
  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<script>
/* ── Theme ── */
(function(){
  var t = localStorage.getItem('cyberTheme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);
  var b = document.getElementById('themeBtn');
  if (b) b.textContent = t === 'dark' ? '☀ Light' : '🌙 Dark';
})();

function toggleTheme() {
  var cur  = document.documentElement.getAttribute('data-theme');
  var next = cur === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('cyberTheme', next);
  var b = document.getElementById('themeBtn');
  if (b) b.textContent = next === 'dark' ? '☀ Light' : '🌙 Dark';
}

/* ── Live Clock ── */
function tick() {
  var el = document.getElementById('clock');
  if (el) el.textContent = new Date().toLocaleTimeString('en-GB');
}
tick(); setInterval(tick, 1000);

/* ── Stat counter animation ── */
document.querySelectorAll('.stat-value[data-target]').forEach(function(el) {
  var target = parseInt(el.dataset.target, 10) || 0;
  if (target === 0) return;
  var step = Math.max(1, Math.ceil(target / 50));
  var cur  = 0;
  var iv   = setInterval(function() {
    cur = Math.min(cur + step, target);
    el.textContent = cur.toLocaleString();
    if (cur >= target) clearInterval(iv);
  }, 20);
});

/* ── Auto-dismiss alerts ── */
document.querySelectorAll('.alert').forEach(function(el) {
  setTimeout(function() {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 500);
  }, 5000);
});

/* ── Chart helpers ── */
function buildPieChart(id, labels, data) {
  var ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: data,
        backgroundColor: ['rgba(255,23,68,0.7)', 'rgba(255,179,0,0.7)', 'rgba(0,193,50,0.6)'],
        borderColor: ['#ff1744', '#ffb300', '#00c132'],
        borderWidth: 2,
        hoverOffset: 10
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#8892b0', padding: 16, font: { size: 11 } }
        }
      }
    }
  });
}

function buildLineChart(id, labels, datasets) {
  var ctx = document.getElementById(id);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#8892b0', padding: 12, font: { size: 11 } } }
      },
      scales: {
        x: {
          grid: { color: 'rgba(0,255,65,0.04)' },
          ticks: { color: '#3d6b52', font: { size: 9, family: 'Share Tech Mono' } }
        },
        y: {
          grid: { color: 'rgba(0,255,65,0.04)' },
          ticks: { color: '#3d6b52', font: { size: 9, family: 'Share Tech Mono' } },
          beginAtZero: true
        }
      }
    }
  });
}
</script>
</body>
</html>
<?php }
