<?php // api/submit.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? ''); $l = $_POST['language'] ?? ''; $code = $_POST['code'] ?? ''; $out = $_POST['output'] ?? '';
if ($n === '' || $c === '' || $code === '' || mb_strlen($n) > 100 || mb_strlen($c) > 50 || strlen($code) > 50000 || mb_strlen($out) > 20000) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
$map = ['python'=>71,'javascript'=>63,'php'=>68,'java'=>62,'cpp'=>54,'c'=>50];
if (!isset($map[$l])) { http_response_code(400); echo json_encode(['error'=>'bad language']); exit; }
$m = db();
try {
  $st = $m->prepare("INSERT INTO submissions (student_name,class_code,language,code,output) VALUES (?,?,?,?,?)");
  if ($st === false) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
  $st->bind_param('sssss', $n, $c, $l, $code, $out);
  if ($st->execute() !== true) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
} catch (mysqli_sql_exception $ex) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
echo json_encode(['ok'=>true,'id'=>$m->insert_id]);
