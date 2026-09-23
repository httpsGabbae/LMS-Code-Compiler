<?php
date_default_timezone_set('Asia/Manila');
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lcc_compiler');
define('JUDGE0_URL', getenv('JUDGE0_URL') ?: 'http://localhost:2358');
define('JUDGE0_KEY', getenv('JUDGE0_KEY') ?: '');
define('JUDGE0_HOST', getenv('JUDGE0_HOST') ?: '');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!function_exists('e')) { function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('csrf_token')) { function csrf_token(): string { if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } return $_SESSION['csrf_token']; } }
if (!function_exists('csrf_field')) { function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; } }
if (!function_exists('verify_csrf')) { function verify_csrf(?string $t): bool { return isset($_SESSION['csrf_token']) && is_string($t) && $t !== '' && hash_equals($_SESSION['csrf_token'], $t); } }
if (!function_exists('require_csrf')) { function require_csrf(): void { if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !verify_csrf($_POST['csrf_token'] ?? null)) { http_response_code(419); exit('CSRF failed'); } } }
function db(): mysqli { $m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_error) { http_response_code(500); exit('DB connect failed'); } $m->set_charset('utf8mb4'); $m->query("SET time_zone='+08:00'"); return $m; }
