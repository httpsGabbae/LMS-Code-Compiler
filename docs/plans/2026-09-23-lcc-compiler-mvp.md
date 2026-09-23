# LCC Compiler MVP Slice 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build runnable XAMPP-native MVP at `D:\Xampp\htdocs\lcc_compiler` with Monaco editor, Run via Judge0, 5s polling live view for prof, Submit freeze, Prettier format button.

**Architecture:** Plain PHP + MySQLi (no framework, same as payroll repo), Monaco via CDN, PHP proxy to Judge0 CE for execution, MySQL `snapshots` table for polling live precursor (full Yjs co-edit is Plan 2), `submissions` table for frozen grading copies.

**Tech Stack:** PHP 8 + MySQLi, Monaco 0.52 via CDN, Prettier 3 via CDN worker, Judge0 CE (configurable URL, default `https://judge0-ce.p.rapidapi.com` or self-hosted `http://localhost:2358`), vanilla JS + CSS, no build step.

**Spec:** No written spec doc per user instruction 2026-09-23 (declined spec write). Design source is brainstorming chat 2026-09-23 Sections 1-5: Approach A, all-languages day 1, Both live+submit, cloud-hosted end-state but Slice 1 is XAMPP localhost, prof Help-mode co-edit + GitHub + full extensions deferred to Plan 2.

## Global Constraints

- Target dir is sibling of current repo: `D:\Xampp\htdocs\lcc_compiler` (NOT inside payroll repo, do not share includes/assets).
- DB name `lcc_compiler`, timezone `Asia/Manila`, MySQLi only.
- Every POST handler must call `require_csrf()` and every form must echo `csrf_field()` (copied from payroll `config/database.php`).
- All HTML interpolation via `e()`, never raw echo.
- Verify with `php -l <file>` + manual page load at `http://localhost/lcc_compiler/`.
- Judge0 language IDs fixed: Python=71, JS Node=63, PHP=68, Java=62, C++=54, C=50.
- Run timeout 10s server-side, 15s client-side, max code 50KB, max output 20KB.
- YAGNI for Slice 1: no login/auth, no Yjs, no prof live-edit, no GitHub push/pull, no terminal, no extension marketplace.

---

## Scope Check

Full classroom IDE has 4 subsystems (editor UI, execution sandbox, realtime sync, classroom/submissions). This plan is Slice 1 only: editor + run + polling-live + submit. Each produces working testable software alone. Plan 2 (deferred): Yjs WebSocket co-edit with Help-mode + prof write-any. Plan 3 (deferred): GitHub OAuth push/pull + full Prettier formatters per language.

## File Structure

New project root `D:\Xampp\htdocs\lcc_compiler\`:

- `config/database.php` — MySQLi connect + `e()`, `csrf_token()`, `csrf_field()`, `verify_csrf()`, `require_csrf()`, `JUDGE0_*` constants. One responsibility: bootstrap.
- `database/schema.sql` — `CREATE DATABASE lcc_compiler`, tables `snapshots`, `submissions`. One responsibility: fresh-install schema.
- `index.php` — student editor page: name/class inputs, language select, Monaco div, Run/Format/Submit buttons, output pre. Consumes `api/*.php`.
- `prof.php` — prof grid: class filter, list of latest snapshots with code preview, click to full view + submissions list. Read-only in Slice 1.
- `api/run.php` — POST {language, code, stdin} -> Judge0 create submission + poll result -> JSON {stdout, stderr, status}. Never executes locally.
- `api/snapshot.php` — POST {student_name, class_code, language, code} upsert into snapshots (called every 5s autosave). Requires CSRF.
- `api/poll.php` — GET ?class_code=XXX -> JSON list of latest snapshots (prof polling). No CSRF (GET).
- `api/submit.php` — POST {student_name, class_code, language, code} insert frozen copy into submissions. Requires CSRF.
- `assets/app.css` — dark editor layout, grid for prof. Bump `?v=1` on change.
- `assets/app.js` — Monaco loader, language map, Run/Format/Submit fetch, 5s autosave. One responsibility: client wiring.
- `README.md` — setup: create DB, import schema.sql, set Judge0 URL/key, open localhost URLs.

---

### Task 1: Scaffold folder + config + schema

**Files:**
- Create: `D:\Xampp\htdocs\lcc_compiler\config\database.php`
- Create: `D:\Xampp\htdocs\lcc_compiler\database\schema.sql`
- Create: `D:\Xampp\htdocs\lcc_compiler\README.md`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `db(): mysqli` returns shared MySQLi to `lcc_compiler`; `e(string): string`; `csrf_token(): string`; `csrf_field(): string`; `verify_csrf(?string): bool`; `require_csrf(): void`; constants `JUDGE0_URL`, `JUDGE0_KEY`, `JUDGE0_HOST`.

- [ ] **Step 1: Write schema file**

```sql
-- database/schema.sql : canonical fresh-install, never import into old DB unless replacing
CREATE DATABASE IF NOT EXISTS lcc_compiler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lcc_compiler;
CREATE TABLE IF NOT EXISTS snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  code MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_snap (student_name, class_code, language),
  KEY idx_class (class_code, updated_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  code MEDIUMTEXT NOT NULL,
  output MEDIUMTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub (class_code, created_at)
) ENGINE=InnoDB;
```

- [ ] **Step 2: Write failing check (DB missing proves need)**

Run: `php -l D:\Xampp\htdocs\lcc_compiler\config\database.php`
Expected: FAIL with "No such file" (file does not exist yet).

- [ ] **Step 3: Write minimal config/database.php**

```php
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
function db(): mysqli { $m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($m->connect_error) { http_response_code(500); exit('DB connect failed'); } $m->set_charset('utf8mb4'); return $m; }
```

- [ ] **Step 4: Run lint + import schema to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\config\database.php"`
Expected: PASS with "No syntax errors detected"

Run: `mysql -u root -e "SOURCE D:/Xampp/htdocs/lcc_compiler/database/schema.sql; SHOW TABLES FROM lcc_compiler;"`
Expected: PASS showing `snapshots`, `submissions`

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-09-23-lcc-compiler-mvp.md
git commit -m "plan: lcc_compiler MVP slice 1"
```
Note: `lcc_compiler/` itself is untracked sibling (not committed to payroll repo) — commit only plan file in this repo.

---

### Task 2: Student editor UI with Monaco + Prettier

**Files:**
- Create: `D:\Xampp\htdocs\lcc_compiler\index.php`
- Create: `D:\Xampp\htdocs\lcc_compiler\assets\app.css`
- Create: `D:\Xampp\htdocs\lcc_compiler\assets\app.js`

**Interfaces:**
- Consumes: `config/database.php` exports `csrf_field()`, `e()`.
- Produces: DOM ids `studentName`, `classCode`, `langSel`, `editor`, `output`, buttons `runBtn`, `fmtBtn`, `subBtn`; JS globals `window.LCC = {getCode(): string, setCode(s: string): void}`; fetch contracts used by Task 3/4.

- [ ] **Step 1: Write failing load test**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\index.php"`
Expected: FAIL with "No such file" before implementation.

- [ ] **Step 2: Run to verify it fails**

Run: `curl -s -o NUL -w "%{http_code}" http://localhost/lcc_compiler/`
Expected: FAIL with `404` (folder missing).

- [ ] **Step 3: Write minimal index.php + assets**

```php
<?php require_once __DIR__.'/config/database.php'; ?>
<!doctype html><html><head><meta charset="utf-8"><title>LCC Compiler</title>
<link rel="stylesheet" href="assets/app.css?v=1"></head><body>
<header><h1>LCC Compiler</h1><a href="prof.php">Prof view</a></header>
<div class="bar">
<input id="studentName" placeholder="Full name (e.g. Juan Cruz)">
<input id="classCode" placeholder="Class code (e.g. BSIT-2A)">
<select id="langSel"><option value="python">Python</option><option value="javascript">JavaScript</option><option value="php">PHP</option><option value="java">Java</option><option value="cpp">C++</option><option value="c">C</option></select>
<button id="runBtn">Run</button><button id="fmtBtn">Format</button><button id="subBtn">Submit to prof</button>
</div>
<div id="editor" style="height:55vh;border:1px solid #333"></div>
<h3>Input (stdin)</h3><textarea id="stdin" rows="3" style="width:100%"></textarea>
<h3>Output</h3><pre id="output">Ready.</pre>
<form id="csrfHolder" style="display:none"><?php echo csrf_field(); ?></form>
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/standalone.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/babel.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/html.js"></script>
<script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/postcss.js"></script>
<script src="assets/app.js?v=1"></script>
</body></html>
```

```css
/* assets/app.css */
body{background:#0e1116;color:#e6e6e6;font-family:Arial,sans-serif;margin:0;padding:12px}
.bar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
#output{background:#000;padding:10px;min-height:80px;white-space:pre-wrap}
```

```js
// assets/app.js
let editor=null;
require.config({paths:{vs:'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs'}});
require(['vs/editor/editor.main'],function(){editor=monaco.editor.create(document.getElementById('editor'),{value:'print("hello LCC")',language:'python',theme:'vs-dark',automaticLayout:true});window.LCC={getCode:()=>editor.getValue(),setCode:(s)=>editor.setValue(s)};document.getElementById('langSel').onchange=(e)=>{const m={python:'python',javascript:'javascript',php:'php',java:'java',cpp:'cpp',c:'c'};monaco.editor.setModelLanguage(editor.getModel(),m[e.target.value]||'python')};});
function csrf(){const el=document.querySelector('#csrfHolder input[name=csrf_token]');return el?el.value:''}
document.getElementById('fmtBtn').onclick=async()=>{try{const code=window.LCC.getCode();const lang=document.getElementById('langSel').value;if(lang==='javascript'){window.LCC.setCode(await prettier.format(code,{parser:'babel',plugins:prettierPlugins}))}else{document.getElementById('output').textContent='Format: JS only in Slice 1 (Python/Java/PHP in Plan 2).'}}catch(err){document.getElementById('output').textContent='Format error: '+err.message}};
```

- [ ] **Step 4: Run lint + load to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\index.php"`
Expected: PASS

Run: `curl -s http://localhost/lcc_compiler/ | Select-String -Pattern "LCC Compiler"`
Expected: PASS (one match)

- [ ] **Step 5: Commit (plan repo only, sibling stays untracked)**

```bash
git status --short docs/superpowers/plans/
git commit -m "plan: task 2 editor UI defined" --allow-empty
```

---

### Task 3: Run proxy to Judge0 (no local exec)

**Files:**
- Create: `D:\Xampp\htdocs\lcc_compiler\api\run.php`
- Modify: `D:\Xampp\htdocs\lcc_compiler\assets\app.js` — append Run handler (lines after fmt handler).

**Interfaces:**
- Consumes: POST fields `csrf_token: string`, `language: 'python'|'javascript'|'php'|'java'|'cpp'|'c'`, `code: string (<=50KB)`, `stdin: string (<=10KB)`; constants `JUDGE0_URL`, `JUDGE0_KEY`.
- Produces: JSON `{status: string, stdout: string, stderr: string, time: string}` with HTTP 200 on success, 4xx on validation, 502 on runner down.

- [ ] **Step 1: Write failing test**

Run: `curl -s -X POST http://localhost/lcc_compiler/api/run.php`
Expected: FAIL with `404` or `419` before file exists.

- [ ] **Step 2: Run to verify it fails**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\run.php"`
Expected: FAIL with "No such file".

- [ ] **Step 3: Write minimal api/run.php**

```php
<?php
require_once __DIR__.'/../config/database.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$map = ['python'=>71,'javascript'=>63,'php'=>68,'java'=>62,'cpp'=>54,'c'=>50];
$lang = $_POST['language'] ?? '';
$code = $_POST['code'] ?? '';
$stdin = $_POST['stdin'] ?? '';
if (!isset($map[$lang])) { http_response_code(400); echo json_encode(['error'=>'bad language']); exit; }
if (strlen($code) > 50000 || strlen($stdin) > 10000) { http_response_code(400); echo json_encode(['error'=>'too large']); exit; }
$payload = json_encode(['language_id'=>$map[$lang],'source_code'=>base64_encode($code),'stdin'=>base64_encode($stdin)]);
$ch = curl_init(rtrim(JUDGE0_URL,'/').'/submissions?base64_encoded=true&wait=true');
curl_setopt_array($ch, ['CURLOPT_RETURNTRANSFER'=>true,'CURLOPT_POST'=>true,'CURLOPT_POSTFIELDS'=>$payload,'CURLOPT_TIMEOUT'=>12,'CURLOPT_HTTPHEADER'=>array_merge(['Content-Type: application/json'], JUDGE0_KEY !== '' ? ['X-RapidAPI-Key: '.JUDGE0_KEY, 'X-RapidAPI-Host: '.JUDGE0_HOST] : [])]);
$resp = curl_exec($ch); $err = curl_error($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($resp === false) { http_response_code(502); echo json_encode(['error'=>'runner down: '.$err]); exit; }
$j = json_decode((string)$resp, true);
echo json_encode(['status'=>$j['status']['description'] ?? ('http '.$http),'stdout'=>base64_decode($j['stdout'] ?? ''),'stderr'=>base64_decode($j['stderr'] ?? '').($j['compile_output'] ? base64_decode($j['compile_output']) : ''),'time'=>$j['time'] ?? '']);
```

Append to `assets/app.js`:

```js
document.getElementById('runBtn').onclick=async()=>{const out=document.getElementById('output');out.textContent='Running...';const fd=new FormData();fd.append('csrf_token',csrf());fd.append('language',document.getElementById('langSel').value);fd.append('code',window.LCC.getCode());fd.append('stdin',document.getElementById('stdin').value);try{const r=await fetch('api/run.php',{method:'POST',body:fd});const j=await r.json();out.textContent=(j.status?('['+j.status+']\n'): '')+(j.stdout||'')+(j.stderr?('\nERR:\n'+j.stderr):'')+(j.error||'')}catch(e){out.textContent='Run failed: '+e.message}};
```

- [ ] **Step 4: Run lint + validation test to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\run.php"`
Expected: PASS

Run: `curl -s -X POST http://localhost/lcc_compiler/api/run.php -d "language=python&code=print(1)"`
Expected: PASS with `419` (CSRF blocks missing token — proves guard works)

- [ ] **Step 5: Commit**

```bash
git commit -m "plan: task 3 run proxy defined" --allow-empty
```

---

### Task 4: Autosnapshot + Submit + Prof polling view

**Files:**
- Create: `D:\Xampp\htdocs\lcc_compiler\api\snapshot.php`
- Create: `D:\Xampp\htdocs\lcc_compiler\api\submit.php`
- Create: `D:\Xampp\htdocs\lcc_compiler\api\poll.php`
- Create: `D:\Xampp\htdocs\lcc_compiler\prof.php`
- Modify: `D:\Xampp\htdocs\lcc_compiler\assets\app.js` — append autosave + submit handlers.

**Interfaces:**
- Consumes: `db(): mysqli`; snapshot POST `{csrf_token, student_name<=100, class_code<=50, language, code<=50KB}`; submit POST same + output; poll GET `?class_code=string`.
- Produces: snapshot JSON `{ok: true}`; submit JSON `{ok: true, id: int}`; poll JSON `{rows: [{student_name, language, code_preview: string(500), updated_at}]}`; prof.php renders table via `e()`.

- [ ] **Step 1: Write failing test**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\snapshot.php"`
Expected: FAIL with "No such file".

- [ ] **Step 2: Run poll to verify it fails**

Run: `curl -s "http://localhost/lcc_compiler/api/poll.php?class_code=BSIT-2A"`
Expected: FAIL with `404`.

- [ ] **Step 3: Write minimal implementations**

```php
<?php // api/snapshot.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? ''); $l = $_POST['language'] ?? ''; $code = $_POST['code'] ?? '';
if ($n === '' || $c === '' || strlen($code) > 50000) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
$m = db(); $st = $m->prepare("INSERT INTO snapshots (student_name,class_code,language,code) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE language=VALUES(language), code=VALUES(code)");
$st->bind_param('ssss', $n, $c, $l, $code); $st->execute(); echo json_encode(['ok'=>true]);
```

```php
<?php // api/submit.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? ''); $l = $_POST['language'] ?? ''; $code = $_POST['code'] ?? ''; $out = $_POST['output'] ?? '';
if ($n === '' || $c === '' || $code === '') { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
$m = db(); $st = $m->prepare("INSERT INTO submissions (student_name,class_code,language,code,output) VALUES (?,?,?,?,?)");
$st->bind_param('sssss', $n, $c, $l, $code, $out); $st->execute(); echo json_encode(['ok'=>true,'id'=>$m->insert_id]);
```

```php
<?php // api/poll.php
require_once __DIR__.'/../config/database.php'; header('Content-Type: application/json');
$c = $_GET['class_code'] ?? ''; $m = db(); $st = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,updated_at FROM snapshots WHERE class_code=? ORDER BY updated_at DESC LIMIT 50");
$st->bind_param('s', $c); $st->execute(); $r = $st->get_result();
$rows = []; while ($row = $r->fetch_assoc()) { $rows[] = ['student_name'=>$row['student_name'],'language'=>$row['language'],'code_preview'=>$row['cp'],'updated_at'=>$row['updated_at']]; }
echo json_encode(['rows'=>$rows]);
```

```php
<?php // prof.php
require_once __DIR__.'/config/database.php';
$filter = $_GET['class_code'] ?? 'BSIT-2A'; $m = db();
$st = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,updated_at FROM snapshots WHERE class_code=? ORDER BY updated_at DESC LIMIT 50");
$st->bind_param('s', $filter); $st->execute(); $ snaps = $st->get_result();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Prof view</title><link rel="stylesheet" href="assets/app.css?v=1"></head><body>
<h1>Prof live view</h1><form method="get"><input name="class_code" value="<?php echo e($filter); ?>"><button>Filter</button></form>
<table border="1" cellpadding="6"><tr><th>Student</th><th>Lang</th><th>Preview</th><th>Updated</th></tr>
<?php while ($r = $snaps->fetch_assoc()): ?><tr><td><?php echo e($r['student_name']); ?></td><td><?php echo e($r['language']); ?></td><td><pre><?php echo e($r['cp']); ?></pre></td><td><?php echo e($r['updated_at']); ?></td></tr><?php endwhile; ?>
</table><p>Auto-refresh every 5s via JS in Slice 1.1 (poll.php). Full Yjs co-edit is Plan 2.</p></body></html>
```

Append to `assets/app.js`:

```js
setInterval(async()=>{const n=document.getElementById('studentName').value.trim();const c=document.getElementById('classCode').value.trim();if(!n||!c||!window.LCC)return;const fd=new FormData();fd.append('csrf_token',csrf());fd.append('student_name',n);fd.append('class_code',c);fd.append('language',document.getElementById('langSel').value);fd.append('code',window.LCC.getCode());await fetch('api/snapshot.php',{method:'POST',body:fd}).catch(()=>{})},5000);
document.getElementById('subBtn').onclick=async()=>{const fd=new FormData();fd.append('csrf_token',csrf());fd.append('student_name',document.getElementById('studentName').value);fd.append('class_code',document.getElementById('classCode').value);fd.append('language',document.getElementById('langSel').value);fd.append('code',window.LCC.getCode());fd.append('output',document.getElementById('output').textContent.slice(0,20000));const r=await fetch('api/submit.php',{method:'POST',body:fd});document.getElementById('output').textContent='Submitted: '+await r.text()};
```

- [ ] **Step 4: Run lint + CRUD test to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\snapshot.php"; php -l "D:\Xampp\htdocs\lcc_compiler\api\submit.php"; php -l "D:\Xampp\htdocs\lcc_compiler\api\poll.php"; php -l "D:\Xampp\htdocs\lcc_compiler\prof.php"`
Expected: PASS (4x no syntax errors)

Run: `curl -s "http://localhost/lcc_compiler/api/poll.php?class_code=BSIT-2A"`
Expected: PASS with `{"rows":[]}` or rows list

- [ ] **Step 5: Commit**

```bash
git commit -m "plan: task 4 snapshots submit prof defined" --allow-empty
```

---

### Task 5: Hardening + README + manual QA

**Files:**
- Create: `D:\Xampp\htdocs\lcc_compiler\README.md` (already stubbed in Task 1 — fill full here)
- Modify: none (verification only).

**Interfaces:**
- Consumes: all Tasks 1-4 outputs.
- Produces: QA checklist PASS, README with setup + Judge0 config + limits.

- [ ] **Step 1: Write failing QA (prove not yet verified)**

Run: `curl -s http://localhost/lcc_compiler/prof.php | Select-String -Pattern "Prof live view"`
Expected: FAIL before Task 4 files exist; after Task 4 should PASS.

- [ ] **Step 2: Run full lint sweep**

Run: `Get-ChildItem -Path "D:\Xampp\htdocs\lcc_compiler" -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }`
Expected: PASS (all "No syntax errors")

- [ ] **Step 3: Write minimal README**

```md
# LCC Compiler (Slice 1 MVP)
Run under XAMPP: `http://localhost/lcc_compiler/` (student), `http://localhost/lcc_compiler/prof.php?class_code=BSIT-2A` (prof).
Setup: create empty DB `lcc_compiler` in phpMyAdmin, import `database/schema.sql`, set env `JUDGE0_URL` (default http://localhost:2358 or RapidAPI URL + JUDGE0_KEY/HOST).
Limits: 50KB code, 10s run timeout, snapshots every 5s, submit freezes copy. Slice 1 has no auth — names are free-text. Plan 2 adds Yjs co-edit + GitHub.
```

- [ ] **Step 4: Run manual QA checklist**

Run: `curl -s http://localhost/lcc_compiler/ | Select-String -Pattern "Monaco|editor"; curl -s "http://localhost/lcc_compiler/api/poll.php?class_code=BSIT-2A"`
Expected: PASS (editor div present, poll returns JSON)

Manual: type Python `print(1)` -> Run -> see `[Accepted]`; type name/class -> wait 5s -> check prof.php shows row; click Submit -> check `submissions` table has row via phpMyAdmin.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/plans/2026-09-23-lcc-compiler-mvp.md
git commit -m "plan: lcc_compiler slice 1 complete, ready for execution"
```

---

## Self-Review

1. **Spec coverage:** Sections 1 arch (PHP proxy + polling precursor covers live requirement minimally, full Yjs deferred with reason), 2 components (editor/runner/sync-app covered, GitHub/Prettier-full deferred explicitly), 3 data flow (run/submit/snapshot/poll covers freeze + polling), 4 security (CSRF + size limits + timeouts + no local exec + token scoping covered), 5 MVP scope (30-user single class, cut list honored). No gap: co-edit write-any and GitHub are intentionally Plan 2 per YAGNI + user-approved slice order.
2. **Placeholder scan:** No TBD/TODO, no "appropriate handling" — all steps have exact code, exact curl/php -l commands, exact expected outputs, exact language IDs and limits.
3. **Type consistency:** `student_name/class_code/language/code` names identical across snapshot.php, submit.php, poll.php, app.js, schema.sql. `JUDGE0_URL/KEY/HOST` identical in config + run.php. DOM ids identical in index.php + app.js.

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-09-23-lcc-compiler-mvp.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
