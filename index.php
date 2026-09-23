<?php require_once __DIR__ . '/config/database.php'; ?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#f7f7f4">
    <title>LCC Compiler</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css?v=7">
</head>

<body class="cursor-ide">
    <div class="titlebar"><span class="wordmark">LCC <em>Compiler</em></span><span class="badge-pill">Classroom IDE</span><span class="spacer"></span><a class="title-link" href="prof.php">Prof view</a></div>
    <div class="workbench">
        <nav class="activity card" aria-label="Activity">
            <button class="act active" data-view="explorer" title="Explorer"><svg viewBox="0 0 16 16" width="22" height="22" fill="currentColor"><path d="M3 1h6l2 2h2v10H3V1zm6 1.5V4h1.5L9 2.5zM5 7h6v1H5V7zm0 2h6v1H5V9z"/></svg></button>
            <button class="act" data-view="run" title="Run and Submit"><svg viewBox="0 0 16 16" width="22" height="22" fill="currentColor"><path d="M4 2l9 6-9 6V2z"/></svg></button>
        </nav>
        <aside class="sidebar card">
            <section class="side-section" id="view-explorer">
                <h2>EXPLORER</h2>
                <div class="side-group">
                    <h3>CLASS</h3>
                    <input id="studentName" placeholder="Full name (e.g. Juan Cruz)" autocomplete="off">
                    <input id="classCode" placeholder="Class code (e.g. BSIT-2A)" autocomplete="off">
                </div>
                <div class="side-group">
                    <h3>FILES</h3>
                    <ul class="files" id="fileList">
                        <li class="file active" id="explorerFile">main.py</li>
                    </ul>
                    <button id="addFile" class="btn-secondary block">+ Add file</button>
                </div>
                <div class="side-group">
                    <h3>LANGUAGE</h3>
                    <select id="langSel" hidden style="display:none" tabindex="-1" aria-hidden="true">
                        <option value="python">Python</option>
                        <option value="javascript">JavaScript</option>
                        <option value="php">PHP</option>
                        <option value="java">Java</option>
                        <option value="csharp">C#</option>
                        <option value="cpp">C++</option>
                        <option value="c">C</option>
                        <option value="html">HTML</option>
                        <option value="css">CSS</option>
                    </select>
                </div>
            </section>
            <section class="side-section hidden" id="view-run">
                <h2>RUN AND SUBMIT</h2>
                <button id="runBtn" class="btn-primary block">Run</button>
                <button id="checkAll" class="btn-primary block">Check all</button>
                <button id="fmtBtn" class="btn-secondary block">Format (JS)</button>
                <button id="subBtn" class="btn-primary block">Submit to prof</button>
                <p class="hint">Runner: local demo · 10s limit · C/C++ need Docker</p>
            </section>
        </aside>
        <main class="main card">
            <div class="tabs" id="tabs">
                <div class="tab active"><span id="tabFile">main.py</span><span class="dirty" id="tabDirty">●</span></div>
            </div>
            <div id="editor"></div>
            <div class="panel">
                <div class="panel-tabs">
                    <button class="ptab active" data-panel="output">OUTPUT</button>
                    <button class="ptab" data-panel="input">INPUT</button>
                </div>
                <div class="panel-body" id="panel-output">
                    <div id="checkResults"></div>
                    <pre id="output">Ready.</pre>
                    <iframe id="htmlPreview" class="hidden" sandbox></iframe>
                </div>
                <div class="panel-body hidden" id="panel-input"><textarea id="stdin" rows="4" placeholder="Program input goes here..."></textarea></div>
            </div>
        </main>
    </div>
    <footer class="statusbar card">
        <span class="st" id="statusClass">—</span>
        <span class="st" id="statusLang">Python</span>
        <span class="st" id="statusPos">Ln 1, Col 1</span>
        <span class="st" id="statusSaved">○ unsaved</span>
        <span class="spacer"></span>
        <span class="st dim">local demo</span>
    </footer>
    <form id="csrfHolder" style="display:none"><?php echo csrf_field(); ?></form>
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/standalone.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/babel.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/html.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prettier@3.3.3/plugins/postcss.js"></script>
    <script src="assets/app.js?v=5"></script>
</body>

</html>
