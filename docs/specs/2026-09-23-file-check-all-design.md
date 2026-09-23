# File-kind Check-All — Design Spec

**Status:** Approved section-by-section in chat, 2026-09-23.
**Scope:** `D:\Xampp\htdocs\lcc_compiler` (sibling of payroll repo, untracked). Spec lives in payroll repo docs only as record.

## Goal

Students add named files; language comes from the file extension, never a dropdown. One **Check all** run checks every file the way its kind demands and returns per-file pass/fail. Prof authors stdin/stdout test cases per class+language.

## 1. Files & storage

- Sidebar **+ Add file**; filenames `name.ext`. Extension map (server-side, authoritative):
  `.py→python`, `.js→javascript`, `.php→php`, `.java→java`, `.cs→csharp`,
  `.cpp→c++`, `.c→c`, `.html→preview`, `.css→validate`. Unknown → soft reject.
- `snapshots` gains `filename`; unique key becomes `(student_name, class_code, filename)`.
  Language column stays but is server-derived, never trusted from POST.
- Prof tables group by student, expandable to files.

## 2. Checker engine (`api/check.php`)

- Input: all files for (student, class). Output: `[{filename, kind, status: pass|fail|skip|error, detail}]`.
- Runnable kinds execute via existing runner (Judge0 first, local fallback); prof test cases when defined, smoke-run when not (smoke pass = exit code 0).
- `.html`: server render; `<?php` sniffed → local PHP render; sibling `style.css`/`app.js` auto-inlined into preview; `<script>` reported, not executed server-side.
- `.css`: balanced braces/brackets + parse sanity + prof-required selectors (one selector per line textarea).
- C#: local dotnet SDK `csc` → exe (no project/restore). `.cpp/.c` still need gcc/Docker → `skip` with message until toolchain exists.
- Batch is atomic per file: one failure never aborts the rest.

## 3. Cases + student flow

- New `test_cases` table: `(class_code, language, stdin, expected_stdout)`, prof-authored via textarea form on prof page.
- Student **Check all** button (topbar + Run panel). Results render as green/red pills + detail; HTML preview pane appears when `.html` checked.
- Matching: **normalized** (trailing whitespace/blank lines ignored). Exact-match toggle deferred.
- Check results computed live, not stored. Score-with-Submit deferred.

## 4. Limits, errors, testing

- Per file: 10s timeout, 20KB output, 50KB file cap. Batch cap ~60s, partial results returned.
- Compiler/runtime errors → `fail` with message. Empty file → `skip`. Oversize → 400.
- Demo warning: PHP-in-HTML executes on host (same as Run) — LAN-only.
- Tests: fixture per kind (py pass/fail, html+php, broken css, unknown ext, oversize), `php -l` sweep, curl contracts on `api/check.php`, live click-through, existing Run regression re-run.

## Explicitly deferred

Exact-match toggle, stored scores/gradebook, Yjs co-edit, GitHub push/pull, Judge0 compose, gcc toolchain.
