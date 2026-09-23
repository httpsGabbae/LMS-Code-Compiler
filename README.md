# LCC Compiler (Slice 1 MVP)

XAMPP-native classroom IDE precursor: Monaco editor + Judge0 run + 5s autosnapshot polling + frozen submit. No auth, no Yjs, no GitHub in Slice 1.

## URLs

- Student: `http://localhost/lcc_compiler/`
- Prof: `http://localhost/lcc_compiler/prof.php?class_code=BSIT-2A`

## Setup

1. Create empty DB `lcc_compiler` in phpMyAdmin (no tables).
2. Import `database/schema.sql` (creates `snapshots`, `submissions`). Never import into an old DB unless replacing it.
3. Place folder as `D:\Xampp\htdocs\lcc_compiler` (sibling of payroll repo, no shared includes/assets).
4. Open student URL above.

## Judge0 config

- Default `JUDGE0_URL=http://localhost:2358` (self-hosted Judge0 CE).
- Free local runner: `docker run -p 2358:2358 judge0/judge0`
- Local demo: leave `JUDGE0_KEY` and `JUDGE0_HOST` empty (defaults in `config/database.php`).
- RapidAPI alternative: set env `JUDGE0_URL=https://judge0-ce.p.rapidapi.com`, `JUDGE0_KEY=<key>`, `JUDGE0_HOST=judge0-ce.p.rapidapi.com`.
- Demo note: Run tries Judge0 first; if unreachable it falls back to local demo exec (no Docker needed) for Python/PHP/JS/Java with 10s timeout. C/C++ need gcc or Judge0 Docker and return `needs Docker` message. Only if both fail does it return 502.

## Limits

- Code 50KB max, stdin 10KB max (server 400 `too large`, client pre-check blocks).
- Output capped 20KB (stdout/stderr truncated server-side).
- Run curl timeout ~12s (plan target 10s server / 15s client).
- Snapshots: student page POSTs to `api/snapshot.php` every 5s (upsert per student+class+filename, language server-derived from filename); prof JSON feed `api/poll.php?class_code=XXX` returns `{rows}` with `filename` per row.
- Submit: `api/submit.php` inserts frozen copy into `submissions` (never updated).
- Slice 1 has no auth — student name/class are free-text inputs.

## Language IDs (Judge0)

Python=71, JS Node=63, PHP=68, Java=62, C#=51, C++=54, C=50.

## Check-all (multi-file)

Migration `database/migrate_check_all.sql` is ONE-SHOT: run once; fresh installs use `database/schema.sql` — do not re-run.

Every file carries its language via its extension. The server derives the language with `ext_lang()` in `config/languages.php` — a client-sent language is never trusted for execution choice.

| Extension | Language | Kind | How Check-all handles it |
|---|---|---|---|
| `.py` | python | run | Run against matching `test_cases` rows |
| `.js` | javascript | run | Run against matching `test_cases` rows |
| `.php` | php | run | Run against matching `test_cases` rows |
| `.java` | java | run | Run against matching `test_cases` rows |
| `.cs` | csharp | run | Run against matching `test_cases` rows (dotnet SDK `csc` locally, Judge0 51 first) |
| `.cpp` | cpp | run | Needs gcc or Judge0 Docker, else `needs Docker` status |
| `.c` | c | run | Needs gcc or Judge0 Docker, else `needs Docker` status |
| `.html` | html | preview | Rendered (PHP sniffed+executed, sibling `style.css`/`app.js` inlined); `test_cases` rows act as substring rules |
| `.css` | css | validate | Brace/paren/bracket balance + one-substring-per-row rules from `test_cases` |

Test-case authoring: prof page "Test cases" form (class, language incl. csharp/html/css, stdin textarea, expected textarea; 10KB caps each; delete button per row). Cases are stored in `test_cases(class_code, language, stdin, expected_stdout)` and apply to every student file whose derived language matches.

Matching rule (normalized): outputs compare with `norm_output()` — leading/trailing blank lines stripped, trailing spaces stripped per line. HTML preview uses substring match instead of exact equality.

Limits: 50KB/file, 10KB stdin, 20KB output cap, 10s per file, ~60s batch cap (`truncated: true` when hit).
