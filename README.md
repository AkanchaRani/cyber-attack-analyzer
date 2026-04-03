# 🛡️ CyberIDS — Cyber Attack Log Analyzer & Intrusion Detection Dashboard

A production-ready PHP & MySQL web application for detecting, logging, and visualizing cyber attacks.

---

## 📋 Project Structure

```
cyber-dashboard/
├── config/
│   └── db.php              ← Database connection, auto-installer, helper functions
├── includes/
│   └── layout.php          ← Shared sidebar, topbar, layout helpers
├── assets/
│   ├── css/style.css       ← Full dark/light mode responsive CSS
│   └── js/app.js           ← Theme toggle, chart helpers, animations
├── index.php               ← 📊 Dashboard (stats + charts + recent activity)
├── login.php               ← 🔐 Secure admin login
├── register.php            ← ✅ Admin registration with validation
├── logout.php              ← 🚪 Session destruction
├── view_logs.php           ← 📋 Attack logs (search, filter, pagination, CSV export)
├── blocked_ips.php         ← 🚫 Blocked IP management (add/remove)
├── upload_logs.php         ← 📤 Upload .txt/.log files for bulk analysis
├── manual_entry.php        ← ✏️  Manually enter a request for analysis
├── generate_attack.php     ← ⚡ Generate fake attacks for demo
└── sample_log.php          ← Download a sample attack log file
```

---

## 🗄️ Database Tables

| Table               | Description                                      |
|---------------------|--------------------------------------------------|
| `users`             | Admin accounts (hashed passwords, roles)        |
| `attack_logs`       | All detected attacks with IP, URL, type, severity|
| `blocked_ips`       | IPs auto-blocked after 3+ High-severity attacks  |
| `attack_signatures` | Pattern library for detection engine            |

**Tables and default signatures are auto-created on first run.**

---

## ⚙️ Requirements

- PHP 7.4+ (PHP 8.x recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache or Nginx with mod_rewrite
- PHP extensions: `mysqli`, `fileinfo`

---

## 🚀 Installation

### Step 1 — Clone / Extract
```bash
git clone <repo> /var/www/html/cyber-dashboard
# OR extract the ZIP to your web root
```

### Step 2 — Configure Database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← your MySQL username
define('DB_PASS', '');           // ← your MySQL password
define('DB_NAME', 'cyber_dashboard');
```

### Step 3 — Set Permissions
```bash
chmod 755 /var/www/html/cyber-dashboard
chmod 777 /var/www/html/cyber-dashboard/uploads
```

### Step 4 — Configure Apache Virtual Host (optional)
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/cyber-dashboard
    ServerName cyberids.local
    DirectoryIndex index.php
</VirtualHost>
```

### Step 5 — Access the Application
1. Open `http://localhost/cyber-dashboard/register.php`
2. Create your admin account
3. Login and explore!

---

## 🎯 Key Features

### 🔐 Authentication
- Secure registration with email validation
- `password_hash()` / `password_verify()` — bcrypt hashing
- Session-based login with role field for future RBAC expansion
- Clean logout with full session destruction

### 📊 Dashboard
- Animated stat counters (total, high/medium/low, blocked)
- Top attacking IP banner
- **Chart.js** doughnut chart for severity distribution
- **Chart.js** multi-line chart for attacks over last 14 days
- Top attack types bar chart
- Recent activity table

### 🔍 Detection Engine
- Database-driven signature matching (25+ built-in patterns)
- Detects: SQL Injection, Blind SQLi, XSS, Path Traversal, Command Injection, Brute Force, Code Injection, Port Scans, Automated Attacks
- Severity assignment: High / Medium / Low
- Works on uploaded files AND manual entries

### 🚫 Auto-Block Logic
- If an IP accumulates **3+ High-severity** attacks → automatically added to `blocked_ips`
- Blocked IPs are skipped during future log analysis
- Admin can manually block/unblock any IP

### 📤 Log File Upload
- Supports `.txt` and `.log` files up to 5MB
- Line-by-line analysis
- Extracts IP addresses from common log formats

### 📋 Log Management
- Search by IP, URL, or attack type
- Filter by severity and date range
- Pagination (20 per page)
- **CSV export** with current filters applied

---

## 🛡️ Security Features

- All DB queries use **prepared statements** (no SQL injection possible)
- All outputs use `htmlspecialchars()` (XSS prevention)
- `filter_var()` for IP and email validation
- File upload validation (type + size checks)
- Session-based authentication on all protected pages
- Password hashing with PHP `PASSWORD_DEFAULT` (bcrypt)

---

## 📖 Viva Talking Points

1. **Why prepared statements?** — They separate SQL code from data, preventing injection attacks entirely.
2. **How does the detection engine work?** — It queries the `attack_signatures` table and uses `stripos()` to case-insensitively match patterns in the payload.
3. **Why auto-block at 3 High attacks?** — Balances false-positive risk vs. blocking repeat attackers quickly.
4. **How is the severity assigned?** — The signature table has a severity column; whichever signature matches first (ordered High→Medium→Low) wins.
5. **Dark mode implementation?** — CSS custom properties (`--var`) swapped via `data-theme` attribute on `<html>`, stored in `localStorage`.

---

## 🧪 Quick Demo Steps

1. Register → Login
2. Go to **Generate Attacks** → Click "+50"
3. Visit **Dashboard** to see charts populate
4. Go to **Attack Logs** → search for `192.168` → export CSV
5. Go to **Blocked IPs** to see auto-blocked addresses
6. Go to **Upload Logs** → download sample → upload it
7. Go to **Manual Entry** → paste a SQL injection payload

---

*Built with PHP, MySQL, Chart.js | No frameworks required*
