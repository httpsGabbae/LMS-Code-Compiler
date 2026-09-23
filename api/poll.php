<?php // api/poll.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
$c = $_GET['class_code'] ?? ''; $m = db(); $st = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,updated_at FROM snapshots WHERE class_code=? ORDER BY updated_at DESC LIMIT 50");
$st->bind_param('s', $c); $st->execute(); $r = $st->get_result();
$rows = []; while ($row = $r->fetch_assoc()) { $rows[] = ['student_name'=>$row['student_name'],'language'=>$row['language'],'code_preview'=>$row['cp'],'updated_at'=>$row['updated_at']]; }
echo json_encode(['rows'=>$rows]);
