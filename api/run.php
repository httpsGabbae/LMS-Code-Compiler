<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/languages.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$lang = $_POST['language'] ?? '';
$filename = trim($_POST['filename'] ?? '');
if ($filename !== '') {
  $derived = ext_lang($filename);
  if ($derived === null) { http_response_code(400); echo json_encode(['error'=>'bad filename']); exit; }
  $lang = $derived;
}
$jid = judge_id($lang);
$code = $_POST['code'] ?? '';
$stdin = $_POST['stdin'] ?? '';
if ($jid === null) { http_response_code(400); echo json_encode(['error'=>'bad language']); exit; }
if (strlen($code) > 50000 || strlen($stdin) > 10000) { http_response_code(400); echo json_encode(['error'=>'too large']); exit; }
$payload = json_encode(['language_id'=>$jid,'source_code'=>base64_encode($code),'stdin'=>base64_encode($stdin)]);
$ch = curl_init(rtrim(JUDGE0_URL,'/').'/submissions?base64_encoded=true&wait=true');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'], JUDGE0_KEY !== '' ? ['X-RapidAPI-Key: '.JUDGE0_KEY, 'X-RapidAPI-Host: '.JUDGE0_HOST] : [])]);
$resp = curl_exec($ch); $err = curl_error($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($resp !== false) {
  $j = json_decode((string)$resp, true);
  if (is_array($j)) {
    $stdout = substr((string)base64_decode($j['stdout'] ?? ''), 0, 20000);
    $stderr = substr(trim((string)base64_decode($j['stderr'] ?? '')."\n".(string)base64_decode($j['compile_output'] ?? '')), 0, 20000);
    echo json_encode(['status'=>$j['status']['description'] ?? ('http '.$http),'stdout'=>$stdout,'stderr'=>$stderr,'time'=>$j['time'] ?? '']);
    exit;
  }
}
$piston = null; // Piston public API is whitelist-only since 2026-02-15 — demo uses local exec instead (no Docker, no key).
if (in_array($lang, ['cpp','c'], true)) {
  echo json_encode(['status'=>'needs Docker','stdout'=>'','stderr'=>'C/C++ need gcc or Judge0 Docker (not installed on this demo server). Python/PHP/JS/Java run locally.','time'=>'']);
  exit;
}
$tmp = sys_get_temp_dir().'/lcc_run_'.bin2hex(random_bytes(8));
@mkdir($tmp, 0700, true);
$pyBin = file_exists('C:\\Users\\doruc\\AppData\\Local\\Python\\bin\\python.exe') ? 'C:\\Users\\doruc\\AppData\\Local\\Python\\bin\\python.exe' : 'python';
$phpBin = file_exists('D:\\Xampp\\php\\php.exe') ? 'D:\\Xampp\\php\\php.exe' : 'php';
$nodeBin = file_exists('C:\\Program Files\\nodejs\\node.exe') ? 'C:\\Program Files\\nodejs\\node.exe' : 'node';
$javacBin = file_exists('C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.17.10-hotspot\\bin\\javac.exe') ? 'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.17.10-hotspot\\bin\\javac.exe' : 'javac';
$javaBin = file_exists('C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.17.10-hotspot\\bin\\java.exe') ? 'C:\\Program Files\\Eclipse Adoptium\\jdk-17.0.17.10-hotspot\\bin\\java.exe' : 'java';
$cscDll = null; foreach ((array)glob('C:/Program Files/dotnet/sdk/*/Roslyn/bincore/csc.dll') as $d) { $cscDll = $d; }
$dotnetBin = file_exists('C:\Program Files\dotnet\dotnet.exe') ? 'C:\Program Files\dotnet\dotnet.exe' : 'dotnet';
$runCmd = '';
if ($lang === 'python') { file_put_contents($tmp.'/main.py', $code); $runCmd = escapeshellarg($pyBin).' '.escapeshellarg($tmp.'/main.py'); }
elseif ($lang === 'php') { file_put_contents($tmp.'/main.php', $code); $runCmd = escapeshellarg($phpBin).' '.escapeshellarg($tmp.'/main.php'); }
elseif ($lang === 'javascript') { file_put_contents($tmp.'/main.js', $code); $runCmd = escapeshellarg($nodeBin).' '.escapeshellarg($tmp.'/main.js'); }
elseif ($lang === 'java') { file_put_contents($tmp.'/Main.java', $code); $runCmd = escapeshellarg($javacBin).' '.escapeshellarg($tmp.'/Main.java').' && '.escapeshellarg($javaBin).' -cp '.escapeshellarg($tmp).' Main'; }
elseif ($lang === 'csharp') {
  file_put_contents($tmp.'/Main.cs', $code);
  $refDir = null; foreach ((array)glob('C:/Program Files/dotnet/packs/Microsoft.NETCore.App.Ref/*/ref/net*/') as $g) { $refDir = rtrim($g, '/\\').'/'; }
  if ($cscDll === null || $refDir === null) { @rmdir($tmp); echo json_encode(['status'=>'needs SDK','stdout'=>'','stderr'=>'dotnet SDK csc not found','time'=>'']); exit; }
  $refArgs = ''; foreach (['mscorlib.dll','netstandard.dll','System.Runtime.dll','System.Console.dll','System.Collections.dll','System.Linq.dll','System.Linq.Expressions.dll','System.Text.RegularExpressions.dll','System.Threading.dll','System.Threading.Tasks.dll'] as $r) { if (file_exists($refDir.$r)) $refArgs .= ' /r:'.escapeshellarg($refDir.$r); }
  $sdkVer = '8.0.0'; $sdkTfm = 'net8.0';
  if (preg_match('/(\d+)\.(\d+)\.(\d+)/', (string)$refDir, $vm)) { $sdkVer = $vm[1].'.'.$vm[2].'.'.$vm[3]; $sdkTfm = 'net'.$vm[1].'.'.$vm[2]; }
  file_put_contents($tmp.'/Main.runtimeconfig.json', '{"runtimeOptions":{"tfm":"'.$sdkTfm.'","framework":{"name":"Microsoft.NETCore.App","version":"'.$sdkVer.'"}}}');
  $runCmd = escapeshellarg($dotnetBin).' '.escapeshellarg($cscDll).' /nologo'.$refArgs.' /out:'.escapeshellarg($tmp.'/Main.dll').' '.escapeshellarg($tmp.'/Main.cs').' && '.escapeshellarg($dotnetBin).' '.escapeshellarg($tmp.'/Main.dll');
}
$desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$proc = proc_open($runCmd, $desc, $pipes, $tmp);
if (!is_resource($proc)) { http_response_code(502); echo json_encode(['error'=>'local runner failed to start']); exit; }
fwrite($pipes[0], $stdin); fclose($pipes[0]);
stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
$stdout = ''; $stderr = ''; $t0 = microtime(true); $timedOut = false;
while (true) {
  $st = proc_get_status($proc);
  $read = []; if (!feof($pipes[1])) $read[] = $pipes[1]; if (!feof($pipes[2])) $read[] = $pipes[2];
  if ($read) { $w = null; $e = null; @stream_select($read, $w, $e, 0, 200000); foreach ($read as $r) { $chunk = fread($r, 8192); if ($chunk !== false && $chunk !== '') { if ($r === $pipes[1]) $stdout .= $chunk; else $stderr .= $chunk; } } }
  if (!$st['running']) break;
  if (microtime(true) - $t0 > 10) { $timedOut = true; @proc_terminate($proc); break; }
  usleep(50000);
  if (strlen($stdout) > 20000 || strlen($stderr) > 20000) break;
}
foreach ([1,2] as $i) { $rest = stream_get_contents($pipes[$i]); if ($rest !== false) { if ($i === 1) $stdout .= $rest; else $stderr .= $rest; } fclose($pipes[$i]); }
@proc_close($proc);
if ($timedOut) $stderr .= "\n[TIMEOUT after 10s — demo limit]";
$stdout = substr($stdout, 0, 20000); $stderr = substr(trim($stderr), 0, 20000);
@unlink($tmp.'/main.py'); @unlink($tmp.'/main.php'); @unlink($tmp.'/main.js'); @unlink($tmp.'/Main.java'); @unlink($tmp.'/Main.class'); @unlink($tmp.'/Main.cs'); @unlink($tmp.'/Main.dll'); @unlink($tmp.'/Main.pdb'); @unlink($tmp.'/Main.runtimeconfig.json'); @rmdir($tmp);
echo json_encode(['status'=>($timedOut ? 'Timeout' : 'Accepted').' (local demo)','stdout'=>$stdout,'stderr'=>$stderr,'time'=>'']);
