<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/languages.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? '');
$files = json_decode($_POST['files'] ?? '[]', true);
if ($n === '' || $c === '' || !is_array($files) || !$files || count($files) > 20) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }

// Runner copied from api/run.php (Judge0-first/local-fallback), minus headers.
// Temp paths use fixed names only; $filename never touches the filesystem.
function run_code(string $lang, string $code, string $stdin): array {
  $jid = judge_id($lang);
  if ($jid === null) return ['status'=>'bad language','stdout'=>'','stderr'=>'','time'=>''];
  $payload = json_encode(['language_id'=>$jid,'source_code'=>base64_encode($code),'stdin'=>base64_encode($stdin)]);
  $ch = curl_init(rtrim(JUDGE0_URL,'/').'/submissions?base64_encoded=true&wait=true');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json'], JUDGE0_KEY !== '' ? ['X-RapidAPI-Key: '.JUDGE0_KEY, 'X-RapidAPI-Host: '.JUDGE0_HOST] : [])]);
  $resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($resp !== false) {
    $j = json_decode((string)$resp, true);
    if (is_array($j)) {
      $stdout = substr((string)base64_decode($j['stdout'] ?? ''), 0, 20000);
      $stderr = substr(trim((string)base64_decode($j['stderr'] ?? '')."\n".(string)base64_decode($j['compile_output'] ?? '')), 0, 20000);
      return ['status'=>$j['status']['description'] ?? ('http '.$http),'stdout'=>$stdout,'stderr'=>$stderr,'time'=>$j['time'] ?? ''];
    }
  }
  if (in_array($lang, ['cpp','c'], true)) {
    return ['status'=>'needs Docker','stdout'=>'','stderr'=>'C/C++ need gcc or Judge0 Docker (not installed on this demo server). Python/PHP/JS/Java run locally.','time'=>''];
  }
  $tmp = sys_get_temp_dir().'/lcc_run_'.bin2hex(random_bytes(8));
  @mkdir($tmp, 0700, true);
  $pyBin = file_exists('C:\Users\doruc\AppData\Local\Python\bin\python.exe') ? 'C:\Users\doruc\AppData\Local\Python\bin\python.exe' : 'python';
  $phpBin = file_exists('D:\Xampp\php\php.exe') ? 'D:\Xampp\php\php.exe' : 'php';
  $nodeBin = file_exists('C:\Program Files\nodejs\node.exe') ? 'C:\Program Files\nodejs\node.exe' : 'node';
  $javacBin = file_exists('C:\Program Files\Eclipse Adoptium\jdk-17.0.17.10-hotspot\bin\javac.exe') ? 'C:\Program Files\Eclipse Adoptium\jdk-17.0.17.10-hotspot\bin\javac.exe' : 'javac';
  $javaBin = file_exists('C:\Program Files\Eclipse Adoptium\jdk-17.0.17.10-hotspot\bin\java.exe') ? 'C:\Program Files\Eclipse Adoptium\jdk-17.0.17.10-hotspot\bin\java.exe' : 'java';
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
    if ($cscDll === null || $refDir === null) { @rmdir($tmp); return ['status'=>'needs SDK','stdout'=>'','stderr'=>'dotnet SDK csc not found','time'=>'']; }
    $refArgs = ''; foreach (['mscorlib.dll','netstandard.dll','System.Runtime.dll','System.Console.dll','System.Collections.dll','System.Linq.dll','System.Linq.Expressions.dll','System.Text.RegularExpressions.dll','System.Threading.dll','System.Threading.Tasks.dll'] as $r) { if (file_exists($refDir.$r)) $refArgs .= ' /r:'.escapeshellarg($refDir.$r); }
    $sdkVer = '8.0.0'; $sdkTfm = 'net8.0';
    if (preg_match('/(\d+)\.(\d+)\.(\d+)/', (string)$refDir, $vm)) { $sdkVer = $vm[1].'.'.$vm[2].'.'.$vm[3]; $sdkTfm = 'net'.$vm[1].'.'.$vm[2]; }
    file_put_contents($tmp.'/Main.runtimeconfig.json', '{"runtimeOptions":{"tfm":"'.$sdkTfm.'","framework":{"name":"Microsoft.NETCore.App","version":"'.$sdkVer.'"}}}');
    $runCmd = escapeshellarg($dotnetBin).' '.escapeshellarg($cscDll).' /nologo'.$refArgs.' /out:'.escapeshellarg($tmp.'/Main.dll').' '.escapeshellarg($tmp.'/Main.cs').' && '.escapeshellarg($dotnetBin).' '.escapeshellarg($tmp.'/Main.dll');
  }
  $desc = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
  $proc = proc_open($runCmd, $desc, $pipes, $tmp);
  if (!is_resource($proc)) { @unlink($tmp.'/main.py'); @unlink($tmp.'/main.php'); @unlink($tmp.'/main.js'); @unlink($tmp.'/Main.java'); @unlink($tmp.'/Main.class'); @unlink($tmp.'/Main.cs'); @unlink($tmp.'/Main.dll'); @unlink($tmp.'/Main.pdb'); @unlink($tmp.'/Main.runtimeconfig.json'); @rmdir($tmp); return ['status'=>'local runner failed','stdout'=>'','stderr'=>'','time'=>'']; }
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
    if (strlen($stdout) > 20000 || strlen($stderr) > 20000) { @proc_terminate($proc); break; }
  }
  foreach ([1,2] as $i) { $rest = stream_get_contents($pipes[$i]); if ($rest !== false) { if ($i === 1) $stdout .= $rest; else $stderr .= $rest; } fclose($pipes[$i]); }
  $exit = @proc_close($proc);
  if ($timedOut) $stderr .= "\n[TIMEOUT after 10s — demo limit]";
  $stdout = substr($stdout, 0, 20000); $stderr = substr(trim($stderr), 0, 20000);
  @unlink($tmp.'/main.py'); @unlink($tmp.'/main.php'); @unlink($tmp.'/main.js'); @unlink($tmp.'/Main.java'); @unlink($tmp.'/Main.class'); @unlink($tmp.'/Main.cs'); @unlink($tmp.'/Main.dll'); @unlink($tmp.'/Main.pdb'); @unlink($tmp.'/Main.runtimeconfig.json'); @rmdir($tmp);
  $status = $timedOut ? 'Timeout' : (((int)$exit !== 0) ? 'Runtime Error' : 'Accepted');
  return ['status'=>$status.' (local demo)','stdout'=>$stdout,'stderr'=>$stderr,'time'=>''];
}

function check_tc_rows(string $class, string $lang): array {
  $m = db();
  $stmt = $m->prepare('SELECT stdin, expected_stdout FROM test_cases WHERE class_code=? AND language=?');
  if (!$stmt) { $m->close(); return []; }
  $stmt->bind_param('ss', $class, $lang);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close(); $m->close();
  return $rows;
}

$out = []; $truncated = false; $t0 = microtime(true);
$sibs = [];
foreach ($files as $f) {
  if (is_array($f) && isset($f['filename']) && isset($f['code']) && is_string($f['filename'])) $sibs[basename((string)$f['filename'])] = (string)$f['code'];
}
foreach ($files as $f) {
  if (microtime(true) - $t0 > 60) { $truncated = true; break; }
  $fname = (is_array($f) && isset($f['filename'])) ? (string)$f['filename'] : '';
  $code = (is_array($f) && isset($f['code'])) ? (string)$f['code'] : '';
  $safe = basename($fname);
  if ($safe === '') $safe = 'file';
  $lang = ext_lang($fname);
  if ($lang === null) { $out[] = ['filename'=>$safe,'kind'=>'unknown','status'=>'error','detail'=>'bad filename']; continue; }
  $kind = lang_kind($lang);
  if (strlen($code) > 50000) { $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'error','detail'=>'too large']; continue; }
  if ($kind === 'run') {
    if (trim($code)==='') { $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'skip','detail'=>'empty file']; }
    else {
    $cases = check_tc_rows($c, $lang);
    if (!$cases) {
      $r = run_code($lang, $code, '');
      $pass = (strpos($r['status'], 'Accepted') !== false);
      $detail = $pass ? 'smoke exit 0' : trim($r['stderr'] !== '' ? $r['stderr'] : $r['status']);
      if ($pass && $r['stdout'] !== '') $detail .= ' stdout: '.substr($r['stdout'], 0, 200);
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>$pass ? 'pass' : 'fail','detail'=>$detail];
    } else {
      $details = []; $allPass = true;
      foreach ($cases as $i => $tc) {
        $r = run_code($lang, $code, (string)$tc['stdin']);
        $ok = (norm_output($r['stdout']) === norm_output((string)$tc['expected_stdout']) && strpos($r['status'], 'Accepted') !== false);
        if (!$ok) $allPass = false;
        $details[] = 'case'.($i + 1).' '.($ok ? 'pass' : 'fail');
      }
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>$allPass ? 'pass' : 'fail','detail'=>implode('; ', $details)];
    }
    }
  } elseif ($kind === 'preview') {
    $src = $code;
    if (isset($sibs['style.css'])) { $css = $sibs['style.css']; $src = (string)preg_replace_callback('/<link[^>]+href=["\']style\.css["\'][^>]*>/i', function () use ($css) { return '<style>'.$css.'</style>'; }, $src); }
    if (isset($sibs['app.js'])) { $js = $sibs['app.js']; $src = (string)preg_replace_callback('/<script[^>]+src=["\']app\.js["\'][^>]*><\/script>/i', function () use ($js) { return '<script>'.$js.'</script>'; }, $src); }
    $rendered = $src;
    if (stripos($code, '<?php') !== false) {
      $ptmp = sys_get_temp_dir().'/lcc_prev_'.bin2hex(random_bytes(8));
      @mkdir($ptmp, 0700, true);
      file_put_contents($ptmp.'/preview.php', $src);
      $phpBin = file_exists('D:\Xampp\php\php.exe') ? 'D:\Xampp\php\php.exe' : 'php';
      $rendered = '';
      $rproc = proc_open(escapeshellarg($phpBin).' '.escapeshellarg($ptmp.'/preview.php'), [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $rpipes);
      if (is_resource($rproc)) {
        fclose($rpipes[0]);
        stream_set_blocking($rpipes[1], false); stream_set_blocking($rpipes[2], false);
        $rout = ''; $rt0 = microtime(true); $rtimed = false;
        while (true) {
          $rst = proc_get_status($rproc);
          $rread = []; if (!feof($rpipes[1])) $rread[] = $rpipes[1]; if (!feof($rpipes[2])) $rread[] = $rpipes[2];
          if ($rread) { $rw = null; $re = null; @stream_select($rread, $rw, $re, 0, 200000); foreach ($rread as $rr) { $chunk = fread($rr, 8192); if ($chunk !== false && $chunk !== '') { if ($rr === $rpipes[1]) $rout .= $chunk; } } }
          if (!$rst['running']) break;
          if (microtime(true) - $rt0 > 10) { $rtimed = true; @proc_terminate($rproc); break; }
          usleep(50000);
          if (strlen($rout) > 20000) { @proc_terminate($rproc); break; }
        }
        foreach ([1,2] as $i) { $rest = stream_get_contents($rpipes[$i]); if ($rest !== false && $i === 1) $rout .= $rest; fclose($rpipes[$i]); }
        @proc_close($rproc);
        if ($rtimed) $rout .= "\n[TIMEOUT after 10s — demo limit]";
        $rendered = substr($rout, 0, 20000);
      }
      @unlink($ptmp.'/preview.php'); @rmdir($ptmp);
    }
    $cases = check_tc_rows($c, 'html');
    if (!$cases) {
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'pass','detail'=>'rendered '.strlen($rendered).' bytes'];
    } else {
      $details = []; $allPass = true;
      foreach ($cases as $i => $tc) {
        $ok = (strpos($rendered, (string)$tc['expected_stdout']) !== false);
        if (!$ok) $allPass = false;
        $details[] = 'rule'.($i + 1).' '.($ok ? 'pass' : 'fail');
      }
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>$allPass ? 'pass' : 'fail','detail'=>implode('; ', $details)];
    }
  } elseif ($kind === 'validate') {
    if (substr_count($code, '{') !== substr_count($code, '}') || substr_count($code, '(') !== substr_count($code, ')') || substr_count($code, '[') !== substr_count($code, ']')) {
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'fail','detail'=>'unbalanced braces']; continue;
    }
    if (preg_match('/:\s*[;}]/', $code)) {
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'fail','detail'=>'empty declaration value']; continue;
    }
    $cases = check_tc_rows($c, 'css');
    if (!$cases) {
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>'pass','detail'=>'valid css'];
    } else {
      $details = []; $allPass = true;
      foreach ($cases as $i => $tc) {
        $ok = (strpos($code, (string)$tc['expected_stdout']) !== false);
        if (!$ok) $allPass = false;
        $details[] = 'rule'.($i + 1).' '.($ok ? 'pass' : 'fail');
      }
      $out[] = ['filename'=>$safe,'kind'=>$kind,'status'=>$allPass ? 'pass' : 'fail','detail'=>implode('; ', $details)];
    }
  } else {
    $out[] = ['filename'=>$safe,'kind'=>'unknown','status'=>'skip','detail'=>'unknown kind'];
  }
}
echo json_encode(['results'=>$out,'truncated'=>$truncated]);
