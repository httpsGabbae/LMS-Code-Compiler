<?php // api/snapshot.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? ''); $l = $_POST['language'] ?? ''; $code = $_POST['code'] ?? '';
if ($n === '' || $c === '' || mb_strlen($n) > 100 || mb_strlen($c) > 50 || strlen($code) > 50000) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
$map = ['python'=>71,'javascript'=>63,'php'=>68,'java'=>62,'cpp'=>54,'c'=>50];
if (!isset($map[$l])) { http_response_code(400); echo json_encode(['error'=>'bad language']); exit; }
$m = db();
try {
  $st = $m->prepare("INSERT INTO snapshots (student_name,class_code,language,code) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language), code=VALUES(code)");
  if ($st === false) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
  $st->bind_param('ssss', $n, $c, $l, $code);
  if ($st->execute() !== true) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
} catch (mysqli_sql_exception $ex) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
echo json_encode(['ok'=>true]);
