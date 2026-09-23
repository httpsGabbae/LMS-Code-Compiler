<?php // prof.php
require_once __DIR__ . '/config/database.php';
$filter = $_GET['class_code'] ?? 'BSIT-2A';
$m = db();
$st = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,updated_at FROM snapshots WHERE class_code=? ORDER BY updated_at DESC LIMIT 50");
$st->bind_param('s', $filter);
$st->execute();
$snaps = $st->get_result();
$st2 = $m->prepare("SELECT student_name,language,LEFT(code,500) AS cp,LEFT(output,500) AS op,created_at FROM submissions WHERE class_code=? ORDER BY created_at DESC LIMIT 50");
$st2->bind_param('s', $filter);
$st2->execute();
$subs = $st2->get_result();
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
                        <th>Lang</th>
                        <th>Preview</th>
                        <th>Updated</th>
                    </tr>
                    <?php while ($r = $snaps->fetch_assoc()): ?><tr>
                            <td><?php echo e($r['student_name']); ?></td>
                            <td><?php echo e($r['language']); ?></td>
                            <td>
                                <pre><?php echo e($r['cp']); ?></pre>
                            </td>
                            <td><?php echo e($r['updated_at']); ?></td>
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
