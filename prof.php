<?php // prof.php
require_once __DIR__ . '/config/database.php';
$filter = $_GET['class_code'] ?? 'BSIT-2A';
$m = db();
$tc_err = ''; $tc_msg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (isset($_POST['add_case'])) {
        $tcc = trim($_POST['tc_class'] ?? ''); $tcl = $_POST['tc_lang'] ?? '';
        $tcs = $_POST['tc_stdin'] ?? ''; $tce = $_POST['tc_expected'] ?? '';
        $known = ['python','javascript','php','java','csharp','cpp','c','html','css'];
        if ($tcc === '' || mb_strlen($tcc) > 50 || !in_array($tcl, $known, true)) { http_response_code(400); $tc_err = 'Bad class or language.'; }
        elseif (strlen($tcs) > 10240 || strlen($tce) > 10240) { http_response_code(400); $tc_err = 'Stdin/expected exceed 10KB.'; }
        else {
            $ins = $m->prepare("INSERT INTO test_cases (class_code,language,stdin,expected_stdout) VALUES (?,?,?,?)");
            if ($ins === false) { http_response_code(500); $tc_err = 'DB failed.'; }
            else {
                $ins->bind_param('ssss', $tcc, $tcl, $tcs, $tce);
                if ($ins->execute()) { $tc_msg = 'Case added.'; } else { http_response_code(500); $tc_err = 'DB failed.'; }
            }
        }
    } elseif (isset($_POST['del_case'])) {
        $id = (int)($_POST['del_case'] ?? 0);
        if ($id > 0) {
            $del = $m->prepare("DELETE FROM test_cases WHERE id=?");
            if ($del !== false) { $del->bind_param('i', $id); $del->execute(); $tc_msg = 'Case deleted.'; }
        }
    }
}
$tc_langs = ['python','javascript','php','java','csharp','cpp','c','html','css'];
$st = $m->prepare("SELECT student_name,filename,language,LEFT(code,500) AS cp,updated_at FROM snapshots WHERE class_code=? ORDER BY updated_at DESC LIMIT 50");
$st->bind_param('s', $filter);
$st->execute();
$snaps = $st->get_result();
$grouped = [];
while ($r = $snaps->fetch_assoc()) { $grouped[$r['student_name']][] = $r; }
$st2 = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,LEFT(output,500) AS op,created_at FROM submissions WHERE class_code=? ORDER BY created_at DESC LIMIT 50");
$st2->bind_param('s', $filter);
$st2->execute();
$subs = $st2->get_result();
$stc = $m->prepare("SELECT id,class_code,language,LEFT(stdin,200) AS si,LEFT(expected_stdout,200) AS eo FROM test_cases WHERE class_code=? ORDER BY id");
$stc->bind_param('s', $filter);
$stc->execute();
$cases = $stc->get_result();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Prof view — LCC Compiler</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css?v=5">
</head>

<body class="cursor-ide">
    <div class="titlebar"><span class="wordmark">LCC <em>Compiler</em></span><span class="badge-pill">Prof live view</span><span class="spacer"></span><a class="title-link" href="./">Student view</a></div>
    <div class="workbench">
        <nav class="activity card" aria-label="Activity">
            <button class="act active" title="Students"><svg viewBox="0 0 16 16" width="22" height="22" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6c0-2.2 2.2-3.5 5-3.5s5 1.3 5 3.5v1H3v-1z"/></svg></button>
        </nav>
        <aside class="sidebar card">
            <section class="side-section">
                <h2>CLASS</h2>
                <form method="get"><input name="class_code" value="<?php echo e($filter); ?>" autocomplete="off"><button class="block">Filter</button></form>
                <p class="hint">Server-rendered — refresh for updates. JSON feed: api/poll.php?class_code=XXX.</p>
            </section>
        </aside>
        <main class="main card">
            <div class="prof-wrap">
                <div class="tabs">
                    <div class="tab active"><span>Live snapshots</span></div>
                </div>
                <table>
                    <tr>
                        <th>Student</th>
                        <th>File</th>
                        <th>Lang</th>
                        <th>Preview</th>
                        <th>Updated</th>
                    </tr>
                    <?php foreach ($grouped as $sname => $files): ?><tr>
                            <td colspan="5"><strong><?php echo e($sname); ?></strong> <span class="hint"><?php echo count($files); ?> file(s)</span></td>
                        </tr><?php foreach ($files as $r): ?><tr>
                            <td></td>
                            <td><?php echo e($r['filename'] !== '' ? $r['filename'] : '(legacy)'); ?></td>
                            <td><?php echo e($r['language']); ?></td>
                            <td>
                                <pre><?php echo e($r['cp']); ?></pre>
                            </td>
                            <td><?php echo e($r['updated_at']); ?></td>
                        </tr><?php endforeach; ?><?php endforeach; ?>
                </table>
                <div class="tabs" style="margin-top:16px">
                    <div class="tab active"><span>Test cases</span></div>
                </div>
                <?php if ($tc_err !== ''): ?><p class="hint"><?php echo e($tc_err); ?></p><?php endif; ?>
                <?php if ($tc_msg !== ''): ?><p class="hint"><?php echo e($tc_msg); ?></p><?php endif; ?>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="add_case" value="1">
                    <input name="tc_class" value="<?php echo e($filter); ?>" autocomplete="off" placeholder="Class (e.g. BSIT-2A)">
                    <select name="tc_lang"><?php foreach ($tc_langs as $tl): ?><option value="<?php echo e($tl); ?>"><?php echo e($tl); ?></option><?php endforeach; ?></select>
                    <textarea name="tc_stdin" placeholder="stdin (max 10KB)"></textarea>
                    <textarea name="tc_expected" placeholder="expected stdout (max 10KB)"></textarea>
                    <button class="block">Add case</button>
                </form>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Class</th>
                        <th>Lang</th>
                        <th>Stdin</th>
                        <th>Expected</th>
                        <th></th>
                    </tr>
                    <?php while ($r = $cases->fetch_assoc()): ?><tr>
                            <td><?php echo (int)$r['id']; ?></td>
                            <td><?php echo e($r['class_code']); ?></td>
                            <td><?php echo e($r['language']); ?></td>
                            <td>
                                <pre><?php echo e($r['si']); ?></pre>
                            </td>
                            <td>
                                <pre><?php echo e($r['eo']); ?></pre>
                            </td>
                            <td>
                                <form method="post"><?php echo csrf_field(); ?><button name="del_case" value="<?php echo (int)$r['id']; ?>">Delete</button></form>
                            </td>
                        </tr><?php endwhile; ?>
                </table>
                <div class="tabs" style="margin-top:16px">
                    <div class="tab active"><span>Submissions</span></div>
                </div>
                <table>
                    <tr>
                        <th>Student</th>
                        <th>Lang</th>
                        <th>Code preview</th>
                        <th>Output preview</th>
                        <th>Created</th>
                    </tr>
                    <?php while ($r = $subs->fetch_assoc()): ?><tr>
                            <td><?php echo e($r['student_name']); ?></td>
                            <td><?php echo e($r['language']); ?></td>
                            <td>
                                <pre><?php echo e($r['cp']); ?></pre>
                            </td>
                            <td>
                                <pre><?php echo e($r['op']); ?></pre>
                            </td>
                            <td><?php echo e($r['created_at']); ?></td>
                        </tr><?php endwhile; ?>
                </table>
            </div>
        </main>
    </div>
    <footer class="statusbar card">
        <span class="st"><?php echo e($filter); ?></span>
        <span class="spacer"></span>
        <span class="st dim">refresh to update</span>
    </footer>
</body>

</html>
