<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';
requireAuth();
$db = getDB();

$total   = $db->query("SELECT COUNT(*) as c FROM attack_logs")->fetch_assoc()['c'];
$high    = $db->query("SELECT COUNT(*) as c FROM attack_logs WHERE severity='High'")->fetch_assoc()['c'];
$medium  = $db->query("SELECT COUNT(*) as c FROM attack_logs WHERE severity='Medium'")->fetch_assoc()['c'];
$low     = $db->query("SELECT COUNT(*) as c FROM attack_logs WHERE severity='Low'")->fetch_assoc()['c'];
$blocked = $db->query("SELECT COUNT(*) as c FROM blocked_ips")->fetch_assoc()['c'];

$topRow = $db->query("SELECT ip_address, COUNT(*) as cnt FROM attack_logs GROUP BY ip_address ORDER BY cnt DESC LIMIT 1")->fetch_assoc();
$topIp  = $topRow ? $topRow['ip_address'] : null;
$topCnt = $topRow ? $topRow['cnt'] : 0;

// Chart data — last 14 days
$chartRes = $db->query("SELECT DATE(detected_at) as d,
    SUM(severity='High') as h, SUM(severity='Medium') as m, SUM(severity='Low') as l
    FROM attack_logs WHERE detected_at >= CURDATE()-INTERVAL 13 DAY GROUP BY d ORDER BY d");
$labels=$dH=$dM=$dL=[];
while($r=$chartRes->fetch_assoc()){
    $labels[]=date('M j',strtotime($r['d']));
    $dH[]=(int)$r['h']; $dM[]=(int)$r['m']; $dL[]=(int)$r['l'];
}

$recent   = $db->query("SELECT * FROM attack_logs ORDER BY detected_at DESC LIMIT 10");
$topTypes = $db->query("SELECT attack_type,COUNT(*) as cnt FROM attack_logs GROUP BY attack_type ORDER BY cnt DESC LIMIT 6");
$rows_tt  = []; $maxT=1; while($r=$topTypes->fetch_assoc()){$rows_tt[]=$r;if($r['cnt']>$maxT)$maxT=$r['cnt'];}

layout_start('Dashboard','dashboard');

// Unauthorized access message
if (!empty($_GET['err']) && $_GET['err'] === 'unauthorized'):
?>
<div class="alert alert-danger">🚫 Access Denied — Admin privileges required for that page.</div>
<?php
endif;
?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card c-total">
    <div class="stat-icon-wrap">🎯</div>
    <div class="stat-value" data-target="<?=$total?>"><?=$total?></div>
    <div class="stat-label">Total Attacks</div>
  </div>
  <div class="stat-card c-high">
    <div class="stat-icon-wrap">🔴</div>
    <div class="stat-value" data-target="<?=$high?>"><?=$high?></div>
    <div class="stat-label">High Severity</div>
  </div>
  <div class="stat-card c-medium">
    <div class="stat-icon-wrap">🟡</div>
    <div class="stat-value" data-target="<?=$medium?>"><?=$medium?></div>
    <div class="stat-label">Medium Severity</div>
  </div>
  <div class="stat-card c-low">
    <div class="stat-icon-wrap">🟢</div>
    <div class="stat-value" data-target="<?=$low?>"><?=$low?></div>
    <div class="stat-label">Low Severity</div>
  </div>
  <div class="stat-card c-blocked">
    <div class="stat-icon-wrap">🚫</div>
    <div class="stat-value" data-target="<?=$blocked?>"><?=$blocked?></div>
    <div class="stat-label">Blocked IPs</div>
  </div>
</div>

<!-- THREAT BANNER -->
<?php if($topIp): ?>
<div class="threat-banner">
  <span style="font-size:1.3rem;">⚠️</span>
  <span style="color:var(--muted);font-size:.82rem;font-family:var(--font-mono);">TOP THREAT:</span>
  <code class="ip-pill"><?=htmlspecialchars($topIp)?></code>
  <span style="color:var(--muted);font-size:.85rem;">— <strong style="color:var(--red);"><?=$topCnt?></strong> attacks recorded</span>
  <a href="blocked_ips.php" class="btn btn-danger btn-sm" style="margin-left:auto;">🚫 View Blocked IPs</a>
</div>
<?php endif; ?>

<!-- CHARTS -->
<div class="charts-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title">📊 SEVERITY SPLIT</div>
    </div>
    <div class="card-body" style="height:270px;position:relative;">
      <canvas id="pieChart"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">📈 ATTACK TIMELINE — LAST 14 DAYS</div>
      <a href="view_logs.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="height:270px;position:relative;">
      <canvas id="lineChart"></canvas>
    </div>
  </div>
</div>

<!-- BOTTOM -->
<div class="bottom-grid">
  <!-- Recent logs -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🕐 RECENT ATTACKS</div>
      <a href="view_logs.php" class="btn btn-outline btn-sm">All Logs →</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>IP Address</th><th>Attack Type</th><th>Severity</th><th>Time</th></tr></thead>
        <tbody>
          <?php if($recent->num_rows===0): ?>
          <tr><td colspan="4" class="empty-state">
            <span class="icon">📭</span>
            <h3>No attacks logged</h3>
            <p><a href="generate_attack.php" style="color:var(--cyan);">Generate demo data</a></p>
          </td></tr>
          <?php else: while($r=$recent->fetch_assoc()): ?>
          <tr>
            <td><code class="ip-pill"><?=htmlspecialchars($r['ip_address'])?></code></td>
            <td style="font-size:.85rem;"><?=htmlspecialchars($r['attack_type'])?></td>
            <td><span class="badge badge-<?=strtolower($r['severity'])?> badge-dot"><?=$r['severity']?></span></td>
            <td style="font-size:.75rem;color:var(--muted);font-family:var(--font-mono);"><?=date('M j H:i',strtotime($r['detected_at']))?></td>
          </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top types -->
  <div class="card">
    <div class="card-header"><div class="card-title">🏆 TOP ATTACK TYPES</div></div>
    <div class="card-body">
      <?php if(empty($rows_tt)): ?>
      <div class="empty-state"><span class="icon">📭</span><h3>No data yet</h3></div>
      <?php else:
        $colors=['linear-gradient(90deg,#ff2d55,#7c3aed)','linear-gradient(90deg,#ffd60a,#ff9500)','linear-gradient(90deg,#00ff9d,#00b8d9)','linear-gradient(90deg,#a855f7,#4f46e5)','linear-gradient(90deg,#00f5ff,#00ff9d)','linear-gradient(90deg,#ff9500,#ff2d55)'];
        foreach($rows_tt as $i=>$r): $pct=round($r['cnt']/$maxT*100); ?>
      <div style="margin-bottom:.9rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:.35rem;font-size:.83rem;">
          <span><?=htmlspecialchars($r['attack_type'])?></span>
          <strong style="font-family:var(--font-mono);font-size:.78rem;"><?=$r['cnt']?></strong>
        </div>
        <div class="progress-bg">
          <div class="progress-fill" style="width:<?=$pct?>%;background:<?=$colors[$i%6]?>;"></div>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<script>
buildPieChart('pieChart',['HIGH','MEDIUM','LOW'],[<?=$high?>,<?=$medium?>,<?=$low?>]);
buildLineChart('lineChart', <?=json_encode($labels)?>, [
  {label:'HIGH',   data:<?=json_encode($dH)?>, borderColor:'#ff1744', backgroundColor:'rgba(255,23,68,0.07)',  fill:true, tension:.4, pointBackgroundColor:'#ff1744', pointRadius:3},
  {label:'MEDIUM', data:<?=json_encode($dM)?>, borderColor:'#ffb300', backgroundColor:'rgba(255,179,0,0.05)', fill:true, tension:.4, pointBackgroundColor:'#ffb300', pointRadius:3},
  {label:'LOW',    data:<?=json_encode($dL)?>, borderColor:'#00c132', backgroundColor:'rgba(0,193,50,0.05)',   fill:true, tension:.4, pointBackgroundColor:'#00c132', pointRadius:3},
]);
</script>

<?php layout_end(); ?>
