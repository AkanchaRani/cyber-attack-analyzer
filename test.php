<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<title>CSS Test — CyberIDS</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1rem;padding:2rem;">

<div id="result" style="font-size:2rem;">Testing...</div>

<div style="font-family:monospace;background:#0a1628;border:1px solid rgba(0,245,255,0.2);padding:1.5rem;border-radius:12px;max-width:500px;width:100%;font-size:.85rem;color:#8892b0;">
  <div style="color:#00f5ff;margin-bottom:.5rem;">📁 CSS Path Check:</div>
  <div>CSS file: <span style="color:#00ff9d;">assets/css/style.css</span></div>
  <div style="margin-top:.5rem;color:#00f5ff;">📂 Your project should be at:</div>
  <div style="color:#ffd60a;">C:\xampp\htdocs\Project\cyber-dashboard\</div>
</div>

<script>
  // If CSS loaded, background will be dark (#020408)
  var bg = window.getComputedStyle(document.body).backgroundColor;
  var el = document.getElementById('result');
  // Dark background = rgb(2,4,8)
  if (bg === 'rgb(2, 4, 8)' || bg === 'rgb(6, 13, 26)') {
    el.innerHTML = '✅ CSS Loaded Successfully!';
    el.style.color = '#00ff9d';
  } else {
    el.innerHTML = '❌ CSS NOT Loading (bg=' + bg + ')';
    el.style.color = '#ff2d55';
    el.style.fontFamily = 'monospace';
    el.style.fontSize = '1rem';
    // Show fallback info
    document.body.style.background = '#1a1a2e';
    document.body.style.color = '#fff';
  }
</script>
</body>
</html>
