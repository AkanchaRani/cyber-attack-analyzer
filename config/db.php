<?php
// ============================================================
//  config/db.php — Database connection & auto-installer
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PASS', 'your_password_here');
function getDB(): mysqli {
    static $conn = null;
    if ($conn !== null) return $conn;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die('<div style="font-family:monospace;background:#1a1a2e;color:#e94560;padding:2rem;border-radius:8px;margin:2rem auto;max-width:600px;">
            <h2>⚠ Database Connection Failed</h2>
            <p>' . htmlspecialchars($conn->connect_error) . '</p>
            <p style="color:#aaa">Please check your MySQL credentials in <code>config/db.php</code></p>
        </div>');
    }

    // Create database if not exists
    $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);

    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(100)  NOT NULL,
            `email`      VARCHAR(150)  NOT NULL UNIQUE,
            `password`   VARCHAR(255)  NOT NULL,
            `role`       ENUM('admin','analyst') NOT NULL DEFAULT 'admin',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `attack_logs` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address`  VARCHAR(45)  NOT NULL,
            `request_url` TEXT         NOT NULL,
            `attack_type` VARCHAR(100) NOT NULL,
            `severity`    ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low',
            `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `blocked_ips` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `ip_address` VARCHAR(45)  NOT NULL UNIQUE,
            `reason`     VARCHAR(255) NOT NULL DEFAULT 'Auto-blocked: 3+ High severity attacks',
            `blocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `attack_signatures` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `pattern`     VARCHAR(500) NOT NULL,
            `attack_type` VARCHAR(100) NOT NULL,
            `severity`    ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $sql) {
        if (!$conn->query($sql)) {
            die('Table creation error: ' . $conn->error);
        }
    }

    // Seed default signatures
    $count = $conn->query("SELECT COUNT(*) as c FROM attack_signatures")->fetch_assoc()['c'];
    if ($count == 0) {
        $seeds = [
            // SQL Injection
            ["pattern" => "' OR '1'='1",           "attack_type" => "SQL Injection",       "severity" => "High"],
            ["pattern" => "UNION SELECT",            "attack_type" => "SQL Injection",       "severity" => "High"],
            ["pattern" => "DROP TABLE",              "attack_type" => "SQL Injection",       "severity" => "High"],
            ["pattern" => "INSERT INTO",             "attack_type" => "SQL Injection",       "severity" => "High"],
            ["pattern" => "1=1--",                   "attack_type" => "SQL Injection",       "severity" => "High"],
            ["pattern" => "SLEEP(",                  "attack_type" => "Blind SQL Injection", "severity" => "High"],
            ["pattern" => "BENCHMARK(",              "attack_type" => "Blind SQL Injection", "severity" => "High"],
            // XSS
            ["pattern" => "<script>",                "attack_type" => "XSS",                 "severity" => "High"],
            ["pattern" => "javascript:",             "attack_type" => "XSS",                 "severity" => "High"],
            ["pattern" => "onerror=",               "attack_type" => "XSS",                 "severity" => "Medium"],
            ["pattern" => "onload=",                "attack_type" => "XSS",                 "severity" => "Medium"],
            ["pattern" => "alert(",                  "attack_type" => "XSS",                 "severity" => "Medium"],
            // Path Traversal
            ["pattern" => "../../../",               "attack_type" => "Path Traversal",      "severity" => "High"],
            ["pattern" => "..\\..\\",               "attack_type" => "Path Traversal",      "severity" => "High"],
            ["pattern" => "/etc/passwd",             "attack_type" => "Path Traversal",      "severity" => "High"],
            // Brute Force
            ["pattern" => "wp-login.php",            "attack_type" => "Brute Force",         "severity" => "Medium"],
            ["pattern" => "admin/login",             "attack_type" => "Brute Force",         "severity" => "Medium"],
            ["pattern" => "phpmyadmin",              "attack_type" => "Recon / Scan",        "severity" => "Low"],
            // Command Injection
            ["pattern" => "; ls -la",                "attack_type" => "Command Injection",   "severity" => "High"],
            ["pattern" => "| cat /etc",              "attack_type" => "Command Injection",   "severity" => "High"],
            ["pattern" => "`whoami`",                "attack_type" => "Command Injection",   "severity" => "High"],
            // Others
            ["pattern" => "eval(",                   "attack_type" => "Code Injection",      "severity" => "High"],
            ["pattern" => "base64_decode(",          "attack_type" => "Code Injection",      "severity" => "Medium"],
            ["pattern" => "nmap",                    "attack_type" => "Port Scan",           "severity" => "Low"],
            ["pattern" => "sqlmap",                  "attack_type" => "Automated Attack",    "severity" => "High"],
        ];
        $stmt = $conn->prepare("INSERT INTO attack_signatures (pattern, attack_type, severity) VALUES (?,?,?)");
        foreach ($seeds as $s) {
            $stmt->bind_param('sss', $s['pattern'], $s['attack_type'], $s['severity']);
            $stmt->execute();
        }
        $stmt->close();
    }

    return $conn;
}

// ── Helper: auto-block IP if it has 3+ High attacks ──────────────────────────
function autoBlockIfNeeded(string $ip): void {
    $db = getDB();
    // skip if already blocked
    $chk = $db->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
    $chk->bind_param('s', $ip); $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $chk->close(); return; }
    $chk->close();

    $cnt = $db->prepare("SELECT COUNT(*) as c FROM attack_logs WHERE ip_address = ? AND severity = 'High'");
    $cnt->bind_param('s', $ip); $cnt->execute();
    $row = $cnt->get_result()->fetch_assoc();
    $cnt->close();

    if ($row['c'] >= 3) {
        $reason = "Auto-blocked: {$row['c']} High-severity attacks detected";
        $ins = $db->prepare("INSERT IGNORE INTO blocked_ips (ip_address, reason) VALUES (?,?)");
        $ins->bind_param('ss', $ip, $reason); $ins->execute(); $ins->close();
    }
}

// ── Helper: check if IP is blocked ───────────────────────────────────────────
function isBlocked(string $ip): bool {
    $db = getDB();
    $s = $db->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
    $s->bind_param('s', $ip); $s->execute();
    $found = $s->get_result()->num_rows > 0;
    $s->close();
    return $found;
}

// ── Helper: analyze a URL/log line against signatures ────────────────────────
function analyzePayload(string $payload): ?array {
    $db  = getDB();
    $res = $db->query("SELECT * FROM attack_signatures ORDER BY FIELD(severity,'High','Medium','Low')");
    while ($sig = $res->fetch_assoc()) {
        if (stripos($payload, $sig['pattern']) !== false) {
            return ['attack_type' => $sig['attack_type'], 'severity' => $sig['severity']];
        }
    }
    return null;
}
