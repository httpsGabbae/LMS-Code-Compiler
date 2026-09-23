<?php // api/submit.php
require_once __DIR__.'/../config/database.php'; require_once __DIR__.'/../config/languages.php'; header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? ''); $l = $_POST['language'] ?? ''; $code = $_POST['code'] ?? ''; $out = $_POST['output'] ?? '';
$filename = basename(trim($_POST['filename'] ?? ''));
if ($filename !== '') {
  $derived = ext_lang($filename);
  if ($derived === null || mb_strlen($filename) > 255) { http_response_code(400); echo json_encode(['error'=>'bad filename']); exit; }
  $l = $derived;
}
if ($n === '' || $c === '' || $code === '' || mb_strlen($n) > 100 || mb_strlen($c) > 50 || strlen($code) > 50000 || mb_strlen($out) > 20000) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
$known = ['python','javascript','php','java','csharp','cpp','c','html','css'];
if (!in_array($l, $known, true)) { http_response_code(400); echo json_encode(['error'=>'bad language']); exit; }
$m = db();
try {
  $st = $m->prepare("INSERT INTO submissions (student_name,class_code,filename,language,code,output) VALUES (?,?,?,?,?,?)");
  if ($st === false) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
  $st->bind_param('ssssss', $n, $c, $filename, $l, $code, $out);
  if ($st->execute() !== true) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
} catch (mysqli_sql_exception $ex) { http_response_code(500); echo json_encode(['error'=>'db failed']); exit; }
echo json_encode(['ok'=>true,'id'=>$m->insert_id]);
