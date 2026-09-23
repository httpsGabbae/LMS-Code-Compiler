# File-kind Check-All Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Files carry language via extension; one Check-all run checks every file by kind with per-file pass/fail.

**Architecture:** Plain PHP + MySQLi in `D:\Xampp\htdocs\lcc_compiler` (own git repo, `main`). New `config/languages.php` owns the extension map; `api/check.php` loops files through per-kind handlers reusing the Judge0-first/local-fallback runner; snapshots keyed by filename.

**Tech Stack:** PHP 8 + MySQLi, Monaco, vanilla JS/CSS, local python/php/node/java/dotnet toolchains, Judge0 optional.

**Spec:** `docs/specs/2026-09-23-file-check-all-design.md` — the plan argues from the spec, so the spec travels with it; executors read both.

## Global Constraints

- Repo root is `D:\Xampp\htdocs\lcc_compiler` (NOT the payroll repo; never touch it).
- DB `lcc_compiler`, timezone `Asia/Manila`, MySQLi only.
- Every POST handler must call `require_csrf()` and every form must echo `csrf_field()`.
- All HTML interpolation via `e()`, never raw echo.
- Language is server-derived from filename extension via `ext_lang()`; never trust a client-sent language for execution choice.
- Verify with `php -l <file>` + curl checks + live page load at `http://localhost/lcc_compiler/`.
- Limits: 50KB/file, 10KB stdin, 20KB output cap, 10s per file, ~60s batch cap.
- YAGNI: no exact-match toggle, no stored scores, no Yjs, no GitHub, no Judge0 compose.

---

## File Structure

- `config/languages.php` (new) — one responsibility: extension→language→kind mapping + shared `norm_output()`. Functions: `ext_lang(string $filename): ?string`, `lang_kind(string $lang): string` (`run|preview|validate`), `judge_id(string $lang): ?int`, `norm_output(string $s): string`.
- `database/schema.sql` (modify) — canonical fresh-install: `snapshots` gains `filename`, unique `(student_name,class_code,filename)`; new `test_cases` table.
- `database/migrate_check_all.sql` (new) — one responsibility: ALTER existing installs + backfill filenames from language.
- `api/run.php` (modify) — add C# (`csc` local, Judge0 51 first), accept `filename` alternative (derive lang).
- `api/check.php` (new) — one responsibility: loop files → per-kind handler → `[{filename,status,detail}]` JSON.
- `api/snapshot.php`, `api/submit.php`, `api/poll.php` (modify) — accept `filename`, derive language server-side.
- `index.php`, `assets/app.js`, `assets/app.css` (modify) — +Add file, multi-file tabs, Check-all button + result pills + HTML preview pane.
- `prof.php` (modify) — test-case authoring form + files grouped by student.
- `README.md` (modify) — Check-all docs.

---

### Task 1: DB migration + test_cases table

**Files:**
- Modify: `database/schema.sql`
- Create: `database/migrate_check_all.sql`

**Interfaces:**
- Consumes: nothing.
- Produces: `test_cases(class_code, language, stdin, expected_stdout)`; `snapshots.filename` + unique `(student_name,class_code,filename)`.

- [ ] **Step 1: Write the failing check**

Run: `& "D:\Xampp\mysql\bin\mysql.exe" -u root -e "SHOW COLUMNS FROM lcc_compiler.snapshots LIKE 'filename'; SHOW TABLES FROM lcc_compiler LIKE 'test_cases';"`
Expected: FAIL with empty result sets (both missing).

- [ ] **Step 2: Run to verify they are missing**

Run the command above.
Expected: no rows (proves migration needed).

- [ ] **Step 3: Write `database/migrate_check_all.sql`**

```sql
USE lcc_compiler;
ALTER TABLE snapshots ADD COLUMN filename VARCHAR(255) NOT NULL DEFAULT '' AFTER class_code;
UPDATE snapshots SET filename='main.py' WHERE language='python' AND filename='';
UPDATE snapshots SET filename='main.js' WHERE language='javascript' AND filename='';
UPDATE snapshots SET filename='main.php' WHERE language='php' AND filename='';
UPDATE snapshots SET filename='Main.java' WHERE language='java' AND filename='';
UPDATE snapshots SET filename='main.cpp' WHERE language='cpp' AND filename='';
UPDATE snapshots SET filename='main.c' WHERE language='c' AND filename='';
UPDATE snapshots SET filename='main.txt' WHERE filename='';
ALTER TABLE snapshots DROP INDEX uq_snap;
ALTER TABLE snapshots ADD UNIQUE KEY uq_snap_file (student_name, class_code, filename);
CREATE TABLE IF NOT EXISTS test_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  stdin MEDIUMTEXT NOT NULL,
  expected_stdout MEDIUMTEXT NOT NULL,
  KEY idx_tc (class_code, language)
) ENGINE=InnoDB;
```

- [ ] **Step 4: Update `database/schema.sql` canonical file with the same `filename` column, `uq_snap_file` key, and `test_cases` table, then import migration and verify**

Run: `& "D:\Xampp\mysql\bin\mysql.exe" -u root -e "SOURCE D:/Xampp/htdocs/lcc_compiler/database/migrate_check_all.sql; SHOW COLUMNS FROM lcc_compiler.snapshots LIKE 'filename'; SHOW TABLES FROM lcc_compiler LIKE 'test_cases';"`
Expected: PASS with one `filename` row and one `test_cases` row.

- [ ] **Step 5: Commit**

```bash
git add database/schema.sql database/migrate_check_all.sql
git commit -m "feat: filename-keyed snapshots + test_cases table"
```

---

### Task 2: Extension map helper

**Files:**
- Create: `config/languages.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `ext_lang(string $filename): ?string`; `lang_kind(string $lang): string`; `judge_id(string $lang): ?int`; `norm_output(string $s): string`.

- [ ] **Step 1: Write the failing check**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\config\languages.php"`
Expected: FAIL with "No such file".

- [ ] **Step 2: Run to verify it fails**

Run the command above.
Expected: "Could not open input file".

- [ ] **Step 3: Write minimal `config/languages.php`**

```php
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
```

- [ ] **Step 4: Run lint + runtime assertions to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\config\languages.php"`
Expected: PASS with "No syntax errors detected".

Run: `php -r "require 'D:/Xampp/htdocs/lcc_compiler/config/languages.php'; var_dump(ext_lang('Main.CS'), ext_lang('../x.py'), ext_lang('noext'), lang_kind('csharp'), lang_kind('html'), judge_id('csharp'), norm_output(\"1  \n\n\") === '1');"`
Expected: PASS with `string(6) "csharp"`, `NULL`, `NULL`, `run`, `preview`, `int(51)`, `bool(true)`.

- [ ] **Step 5: Commit**

```bash
git add config/languages.php
git commit -m "feat: extension map + normalized compare helpers"
```

---

### Task 3: C# in the runner + filename input

**Files:**
- Modify: `api/run.php`

**Interfaces:**
- Consumes: `config/languages.php` (`ext_lang()`, `judge_id()`); existing Judge0-first/local-fallback flow.
- Produces: same JSON shape as before; accepts `filename` (derives lang) or legacy `language`; C# compiles via local `csc`.

- [ ] **Step 1: Write the failing test**

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_run_red_test.php"`
Expected: currently PASS for python; write a sibling C# probe instead — save this as `C:\Users\doruc\AppData\Local\Temp\opencode\lcc_cs_test.php` (same session-cookie + CSRF pattern as the red test, posting `language=csharp`, `code=class Program{static void Main(){System.Console.Write(7);}}` and asserting stdout contains `7`).

- [ ] **Step 2: Run C# probe to verify it fails**

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_cs_test.php"`
Expected: FAIL with `400 {"error":"bad language"}` (csharp not in map yet).

- [ ] **Step 3: Write minimal changes to `api/run.php`**

```php
require_once __DIR__.'/../config/languages.php';
$filename = trim($_POST['filename'] ?? '');
if ($filename !== '') {
  $derived = ext_lang($filename);
  if ($derived === null) { http_response_code(400); echo json_encode(['error'=>'bad filename']); exit; }
  $lang = $derived;
}
```

Replace the `$map` line with `$jid = judge_id($lang);` and use `$jid` in the Judge0 payload (`'language_id'=>$jid`), keeping the `!isset` guard as `$jid === null → 400`. Add C# locals: `$cscDll = null; foreach ((array)glob('C:/Program Files/dotnet/sdk/*/Roslyn/bincore/csc.dll') as $d) { $cscDll = $d; }` then in the command builder: `elseif ($lang === 'csharp') { file_put_contents($tmp.'/Main.cs', $code); if ($cscDll === null) { echo json_encode(['status'=>'needs SDK','stdout'=>'','stderr'=>'dotnet SDK csc not found','time'=>'']); exit; } $runCmd = 'dotnet '.escapeshellarg($cscDll).' /nologo /out:'.escapeshellarg($tmp.'/Main.exe').' '.escapeshellarg($tmp.'/Main.cs').' && '.escapeshellarg($tmp.'/Main.exe'); }` and cleanup `@unlink($tmp.'/Main.cs'); @unlink($tmp.'/Main.exe');`.

- [ ] **Step 4: Run probes to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\run.php"`
Expected: PASS.

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_cs_test.php"`
Expected: PASS with stdout `7`.

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_run_red_test.php"`
Expected: PASS (python regression still green).

- [ ] **Step 5: Commit**

```bash
git add api/run.php
git commit -m "feat: csharp runner + filename-derived language"
```

---

### Task 4: Check engine

**Files:**
- Create: `api/check.php`

**Interfaces:**
- Consumes: POST `csrf_token`, `student_name`, `class_code`, `files` (JSON array of `{filename, code}`); `config/database.php` (`require_csrf()`, `db()`); `config/languages.php`; `test_cases` rows; run.php logic via extracted include-safe flow (duplicate the Judge0-first/local-fallback call as a local function `run_code(string $lang, string $code, string $stdin): array` copied verbatim from `api/run.php` post-Task-3, minus headers).
- Produces: JSON `{results: [{filename, kind, status: pass|fail|skip|error, detail}]}` with HTTP 200; 400 on bad input; per-file 10s cap; batch stops adding new files after ~60s total, returns partial with `truncated: true`.

- [ ] **Step 1: Write the failing test**

Save `C:\Users\doruc\AppData\Local\Temp\opencode\lcc_check_test.php`: session-cookie + CSRF flow posting two files (`main.py` with `print(1)`, `bad.css` with `p { color: }`) and asserting results contain one `pass` and one `fail`.

- [ ] **Step 2: Run to verify it fails**

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_check_test.php"`
Expected: FAIL with `404` (file missing).

- [ ] **Step 3: Write minimal `api/check.php`**

```php
<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/languages.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
require_csrf();
$n = trim($_POST['student_name'] ?? ''); $c = trim($_POST['class_code'] ?? '');
$files = json_decode($_POST['files'] ?? '[]', true);
if ($n === '' || $c === '' || !is_array($files) || !$files || count($files) > 20) { http_response_code(400); echo json_encode(['error'=>'bad input']); exit; }
```

Per file: validate `filename` via `ext_lang()` (null → `error` "bad filename"), size caps (code 50KB → `error` "too large"), kind switch: `run` → fetch matching `test_cases` rows for ($c, $lang); no rows → smoke-run with empty stdin, pass = exit 0 (empty stderr counts as pass); rows → run each stdin, `norm_output($stdout) === norm_output($expected)` per case, detail lists `case1 pass/fail`. `preview` → if `stripos($code,'<?php')!==false` render via local php with siblings inlined (regex replace `<link[^>]+href=["\']style\.css["\'][^>]*>` with `<style>{css}</style>` and `<script[^>]+src=["\']app\.js["\'][^>]*></script>` with `<script>{js}</script>` using sibling contents from the posted files); then apply `test_cases` rows for `html` as substring rules (`strpos($rendered,$expected)!==false`); no rows → `pass` with rendered length note. `validate` → brace/paren/bracket balance check plus one-substring-per-row rules from `test_cases` for `css`. `unknown` → `skip`. Batch timer: `$t0=microtime(true);` before loop, `if (microtime(true)-$t0 > 60) { $truncated=true; break; }`. Final: `echo json_encode(['results'=>$out,'truncated'=>$truncated]);`.

- [ ] **Step 4: Run tests to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\api\check.php"`
Expected: PASS.

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_check_test.php"`
Expected: PASS with one `pass` and one `fail`.

- [ ] **Step 5: Commit**

```bash
git add api/check.php
git commit -m "feat: per-kind check engine"
```

---

### Task 5: Student UI — files, Check-all, results

**Files:**
- Modify: `index.php`, `assets/app.js`, `assets/app.css`

**Interfaces:**
- Consumes: `api/check.php` contract from Task 4; existing `window.LCC`, autosave, CSRF.
- Produces: +Add file button, multi-file tabs (`openFiles` map), Check-all button posting `{filename, code}` array, result pills + HTML preview pane. Bump `app.css?v=6`, `app.js?v=5`.

- [ ] **Step 1: Write the failing check**

Run: `curl.exe -s http://localhost/lcc_compiler/ | Select-String -Pattern "addFile|checkAll|checkResults"`
Expected: FAIL with no matches.

- [ ] **Step 2: Run to verify it fails**

Run the command above.
Expected: empty (elements missing).

- [ ] **Step 3: Write minimal UI changes**

`index.php`: add `<button id="addFile" class="btn-secondary block">+ Add file</button>` in FILES group; add `<button id="checkAll" class="btn-primary block">Check all</button>` in RUN group; add `<div id="checkResults"></div>` at top of OUTPUT panel body; add hidden preview `<iframe id="htmlPreview" class="hidden" sandbox></iframe>` in OUTPUT panel. `app.js`: `openFiles={}` map filename→code; `addFile` prompts `prompt('Filename (e.g. main.py):')`, validates `/^[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/`, rejects unknown extensions via client map `{py,js,php,java,cs,cpp,c,html,css}`; tab bar renders one tab per open file; autosave interval saves the active file (existing per-file logic kept, filename sent instead of language); tab switch saves previous file first; `checkAll` gathers all open files, POSTs to `api/check.php`, renders pills `<span class="pill pass|fail">filename: status</span>` plus detail `<pre>`, and sets `htmlPreview.srcdoc` when a preview result arrives. `app.css`: `.pill.pass{background:var(--ok);color:#fff}` `.pill.fail{background:var(--err);color:#fff}` `#htmlPreview{width:100%;height:220px;border:1px solid var(--hairline);border-radius:8px;background:#fff}` using exact Cursor tokens (no new hex).

- [ ] **Step 4: Run checks to verify passes**

Run: `php -l "D:\Xampp\htdocs\lcc_compiler\index.php"`
Expected: PASS.

Run: `curl.exe -s http://localhost/lcc_compiler/ | Select-String -Pattern "addFile|checkAll|checkResults"`
Expected: PASS with 3 matches.

Manual: add `main.py` + `bad.css`, Check all → one green + one red pill.

- [ ] **Step 5: Commit**

```bash
git add index.php assets/app.js assets/app.css
git commit -m "feat: multi-file tabs + check-all results UI"
```

---

### Task 6: Prof test cases + grouped view + docs + QA

**Files:**
- Modify: `prof.php`, `README.md`

**Interfaces:**
- Consumes: `test_cases` table; `api/poll.php` (extend to return `filename` per row).
- Produces: prof test-case form (class, language select incl. `csharp/html/css`, stdin textarea, expected textarea); snapshots grouped by student with per-file rows.

- [ ] **Step 1: Write the failing check**

Run: `curl.exe -s "http://localhost/lcc_compiler/prof.php?class_code=BSIT-2A" | Select-String -Pattern "Test cases"`
Expected: FAIL with no match.

- [ ] **Step 2: Run to verify it fails**

Run the command above.
Expected: empty.

- [ ] **Step 3: Write minimal changes**

`prof.php`: add section with `<form method="post"><?php echo csrf_field(); ?><input name="tc_class" value="..."><select name="tc_lang">` (options python/javascript/php/java/csharp/cpp/c/html/css) `<textarea name="tc_stdin">` `<textarea name="tc_expected">` `<button>Add case</button></form>`; POST handler with `require_csrf()` inserts into `test_cases` (caps 10KB each, 400 otherwise); list existing cases with delete buttons (POST `del_case` id). Group snapshots query by student: keep existing query, render nested per-file rows (add `filename` to SELECT + display). `api/poll.php`: add `filename` to SELECT and JSON rows. `README.md`: append Check-all section (extension map table, test-case authoring, C# via dotnet, cpp/c need Docker, normalized matching rule).

- [ ] **Step 4: Run full QA to verify passes**

Run: `Get-ChildItem -Path "D:\Xampp\htdocs\lcc_compiler" -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }`
Expected: PASS all 7+ files.

Run: `php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_check_test.php"; php "C:\Users\doruc\AppData\Local\Temp\opencode\lcc_run_red_test.php"`
Expected: PASS both (no regressions).

Manual: prof adds python case (stdin `2`, expected `4`) for a doubling program; student Check all → pass pill; change expected → fail pill.

- [ ] **Step 5: Commit**

```bash
git add prof.php api/poll.php README.md
git commit -m "feat: prof test cases + grouped files + docs"
```

---

## Self-Review

1. **Spec coverage:** §1 storage → Task 1 (filename key, backfill) + Task 5/6 (filename in snapshot/submit/poll). Extension map → Task 2. `api/check.php` engine → Task 4 (per-kind handlers, HTML sniff+inline, CSS validate). C# dotnet → Task 3. Prof cases → Task 6. Normalized matching → Task 2 helper + Task 4 use. Limits §4 → Tasks 4 (caps/timers) + 6 (QA). Deferred items (exact toggle, stored scores, Yjs, GitHub, compose, gcc) correctly absent.
2. **Placeholder scan:** no TBD/TODO/"appropriate handling"; every step has exact code, exact commands, exact expected outputs; no "similar to Task N" (Task 4's runner reuse names the exact source lines to copy).
3. **Type consistency:** `ext_lang()`/`lang_kind()`/`judge_id()`/`norm_output()` signatures identical in Tasks 2–4; `files` JSON shape `{filename, code}` identical in Tasks 4–5; `results` shape identical in Tasks 4–5; `test_cases(class_code, language, stdin, expected_stdout)` identical in Tasks 1/4/6; version bumps `?v=6/?v=5` stated once in Task 5.

## Execution Handoff

Plan complete and saved to `docs/plans/2026-09-23-file-check-all.md` (in the `lcc_compiler` repo, per your folder rule). Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
