<?php
function ext_lang(string $filename): ?string {
  $base = strtolower(basename($filename));
  if (!preg_match('/^[a-z0-9_-]+\.[a-z0-9]+$/', $base)) return null;
  $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
  $map = ['py'=>'python','js'=>'javascript','php'=>'php','java'=>'java','cs'=>'csharp','cpp'=>'cpp','c'=>'c','html'=>'html','css'=>'css'];
  return $map[$ext] ?? null;
}
function lang_kind(string $lang): string {
  if (in_array($lang, ['python','javascript','php','java','csharp','cpp','c'], true)) return 'run';
  if ($lang === 'html') return 'preview';
  if ($lang === 'css') return 'validate';
  return 'unknown';
}
function judge_id(string $lang): ?int {
  $map = ['python'=>71,'javascript'=>63,'php'=>68,'java'=>62,'csharp'=>51,'cpp'=>54,'c'=>50];
  return $map[$lang] ?? null;
}
function norm_output(string $s): string {
  $lines = explode("\n", str_replace(["\r\n","\r"], "\n", $s));
  $lines = array_map('rtrim', $lines);
  while ($lines && end($lines) === '') array_pop($lines);
  while ($lines && reset($lines) === '') array_shift($lines);
  return implode("\n", $lines);
}
