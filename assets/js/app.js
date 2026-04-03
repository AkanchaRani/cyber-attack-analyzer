// ================================================================
//  assets/js/app.js — Dashboard JavaScript
// ================================================================

// ── Dark / Light mode ────────────────────────────────────────────
(function () {
  const saved = localStorage.getItem('theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
})();

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  const btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = next === 'dark' ? '☀️ Light' : '🌙 Dark';
}

// ── Sidebar mobile toggle ─────────────────────────────────────────
function toggleSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
}

// ── Auto-dismiss alerts ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  // Set theme button label
  const theme = localStorage.getItem('theme') || 'dark';
  const btn = document.getElementById('themeToggle');
  if (btn) btn.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';

  // Dismiss alerts after 4 s
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  // Animate stat numbers
  document.querySelectorAll('.stat-value[data-target]').forEach(el => {
    const target = parseInt(el.dataset.target, 10);
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString();
      if (current >= target) clearInterval(timer);
    }, 30);
  });
});

// ── Chart helpers ─────────────────────────────────────────────────
function buildPieChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: ['#e94560', '#ffd60a', '#06d6a0'],
        borderColor: 'transparent',
        hoverOffset: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: '#8892b0', padding: 16, font: { size: 12 } },
        },
      },
    },
  });
}

function buildLineChart(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { labels: { color: '#8892b0', font: { size: 12 } } } },
      scales: {
        x: {
          grid: { color: 'rgba(136,146,176,.1)' },
          ticks: { color: '#8892b0' },
        },
        y: {
          grid: { color: 'rgba(136,146,176,.1)' },
          ticks: { color: '#8892b0', stepSize: 1 },
          beginAtZero: true,
        },
      },
    },
  });
}
