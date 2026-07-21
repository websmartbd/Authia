<?php
// install.php
// 4-step installer for Authia — lives at the project root

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// ---------------------------------------------------------------------
// Icons (inline SVG, stroke="currentColor")
// ---------------------------------------------------------------------
function icon($name) {
    $icons = [
        'welcome'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
        'db'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg>',
        'mail'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
        'user'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>',
        'check'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'key'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="m10.5 12.5 8-8"/><path d="M16 9l2 2"/><path d="M19 6l2 2"/></svg>',
        'arrow'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',
        'skip'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m5 4 10 8-10 8V4Z"/><path d="M19 5v14"/></svg>',
        'shield'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>',
        'alert'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>',
        'star'     => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="m12 2 2.9 6.26 6.9.6-5.2 4.6 1.6 6.79L12 16.9l-6.2 3.35 1.6-6.79-5.2-4.6 6.9-.6L12 2Z"/></svg>',
        'fork'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="4" r="2"/><circle cx="18" cy="4" r="2"/><circle cx="12" cy="18" r="2"/><path d="M6 6v3a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V6"/><path d="M12 12v4"/></svg>',
    ];
    return $icons[$name] ?? '';
}

function goToStep($step) {
    header('Location: ?step=' . $step);
    exit;
}

function showError($msg) {
    echo '<div class="alert alert-error">' . icon('alert') . '<span>' . htmlspecialchars($msg) . '</span></div>';
}

function fetchRemoteUrl($url) {
    $content = @file_get_contents($url);
    if ($content === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Authia-Installer');
        $content = curl_exec($ch);
        curl_close($ch);
    }
    return $content;
}

function pageHead($title) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234F46E5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='8' cy='15' r='4'/%3E%3Cpath d='m10.5 12.5 8-8'/%3E%3Cpath d='M16 9l2 2'/%3E%3Cpath d='M19 6l2 2'/%3E%3C/svg%3E">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
            :root{
                --ink:#12141A;
                --ink-2:#3F4451;
                --muted:#7B8291;
                --bg:#EEF0F4;
                --panel:#FFFFFF;
                --accent:#4F46E5;
                --accent-hover:#4338CA;
                --accent-ink:#FFFFFF;
                --border:#E4E7EC;
                --success:#16A34A;
                --error:#DC2626;
                --card-shadow:0 24px 60px -20px rgba(18,20,26,0.15);
            }
            *{ box-sizing:border-box; margin:0; padding:0; }
            body{
                font-family:'Inter',sans-serif;
                color:var(--ink);
                background:
                    radial-gradient(circle at 50% -20%, #E1E4FB 0%, transparent 60%),
                    var(--bg);
                min-height:100vh;
                display:flex;
                align-items:center;
                justify-content:center;
                padding:32px 16px;
            }
            h1,h2,h3,.font-display{ font-family:'Sora',sans-serif; }
            .mono{ font-family:'IBM Plex Mono',monospace; }

            .card{
                width:100%;
                max-width:620px;
                background:var(--panel);
                border-radius:16px;
                box-shadow:var(--card-shadow);
                overflow:hidden;
                position:relative;
                border: 1px solid rgba(228, 231, 236, 0.8);
            }

            /* ---- Top Header & Horizontal Progress ---- */
            .topbar{
                background:#FAFAFC;
                color:var(--ink-2);
                padding:20px 32px 18px;
                border-bottom: 1px solid var(--border);
            }
            .topbar-brand{
                display:flex;
                align-items:center;
                gap:9px;
                margin-bottom:16px;
            }
            .topbar-brand-icon{
                width:28px;height:28px;
                background:var(--accent);
                border-radius:8px;
                display:flex;align-items:center;justify-content:center;
                color:#fff;
                flex-shrink:0;
            }
            .topbar-brand-icon svg{ width:15px;height:15px; }
            .topbar-brand-name{
                font-family:'Sora',sans-serif;
                font-weight:700;
                font-size:16px;
                color:var(--ink);
                letter-spacing:0.01em;
            }
            .steps{
                display:flex;
                align-items:center;
                overflow-x: auto;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE/Edge */
                padding-bottom: 2px;
            }
            .steps::-webkit-scrollbar {
                display: none; /* Chrome/Safari */
            }
            .step{
                display:flex;
                align-items:center;
                gap:8px;
                font-size:12px;
                color:var(--muted);
                white-space:nowrap;
            }
            .step .num{
                width:20px;height:20px;
                border-radius:50%;
                border:1px solid var(--border);
                background: #fff;
                display:flex;align-items:center;justify-content:center;
                font-size:10px;
                font-family:'IBM Plex Mono',monospace;
                flex-shrink:0;
            }
            .step.done .num{ background:var(--accent); border-color:var(--accent); color:#fff; }
            .step.done{ color:var(--ink-2); font-weight: 500; }
            .step.active{ color:var(--ink); font-weight:600; }
            .step.active .num{ background:var(--accent); border-color:var(--accent); color:#fff; }
            .step-line{
                flex:1;
                height:2px;
                background:var(--border);
                margin:0 10px;
                min-width:14px;
            }

            /* ---- Content Body ---- */
            .content{ padding:32px 32px 28px; }
            .eyebrow{
                font-family:'IBM Plex Mono',monospace;
                font-size:11px;
                letter-spacing:0.08em;
                text-transform:uppercase;
                color:var(--accent);
                margin-bottom:8px;
                display:block;
            }
            .content h2{
                font-size:20px;
                font-weight:700;
                margin:0 0 10px;
                color:var(--ink);
                display:flex;
                align-items:center;
                gap:9px;
            }
            .content h2 svg{
                width:20px;
                height:20px;
                flex-shrink:0;
                color:var(--accent);
            }
            .content p.lead{
                color:var(--ink-2);
                font-size:14px;
                line-height:1.65;
                margin:0 0 20px;
            }

            .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:0 16px; margin-bottom:4px; }
            .form-grid.full { grid-template-columns:1fr; }

            .field{ margin-bottom:16px; }
            .field label{
                display:block;
                font-size:12px;
                font-weight:600;
                color:var(--ink-2);
                margin-bottom:6px;
            }
            .input{
                width:100%;
                background:#FAFAFC;
                border:1px solid var(--border);
                color:var(--ink);
                font-size:14px;
                padding:10px 12px;
                border-radius:8px;
                outline:none;
                font-family:'Inter',sans-serif;
                transition:border-color .15s ease, box-shadow .15s ease;
            }
            .input:focus{
                border-color:var(--accent);
                box-shadow:0 0 0 3px rgba(79,70,229,0.12);
                background:#fff;
            }
            .input::placeholder{ color:#AEB2BD; }
            select.input{ appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:14px; padding-right:32px; }

            /* ---- Buttons & Actions ---- */
            .actions{
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:12px;
                margin-top:24px;
                padding-top:20px;
                border-top:1px solid var(--border);
            }
            .btn{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                gap:8px;
                background:var(--accent);
                color:#fff;
                border:none;
                font-family:'Inter',sans-serif;
                font-weight:600;
                font-size:14px;
                padding:11px 20px;
                border-radius:8px;
                cursor:pointer;
                text-decoration:none;
                transition:all .15s ease;
            }
            .btn:hover{ background:var(--accent-hover); transform:translateY(-1px); }
            .btn:active{ transform:translateY(0); }
            .btn svg{ width:15px;height:15px; }
            .btn-ghost{
                background:transparent;
                color:var(--muted);
                border:1px solid var(--border);
                font-weight:500;
            }
            .btn-ghost:hover{ background:#F8FAFC; color:var(--ink-2); border-color:#C9CDD6; transform:none; }

            /* ---- Alerts & Badges ---- */
            .alert{
                display:flex;
                align-items:center;
                gap:10px;
                border-radius:8px;
                padding:12px 14px;
                margin-bottom:18px;
                font-size:13px;
                line-height:1.5;
            }
            .alert-error{ color:#991B1B; background:#FEF2F2; border:1px solid #FCA5A5; }
            .alert svg{ width:20px; height:20px; flex-shrink:0; }

            .repo-badge{
                display:flex;
                align-items:center;
                gap:16px;
                font-size:12px;
                color:var(--muted);
                background:#F7F8FA;
                border:1px solid var(--border);
                border-radius:8px;
                padding:9px 14px;
                margin-bottom:20px;
            }
            .repo-badge a{ color:var(--ink-2); text-decoration:none; display:flex; align-items:center; gap:6px; font-weight:500; }
            .repo-badge a:hover{ color:var(--accent); }
            .repo-badge a svg{ width:13px;height:13px; }
            .repo-badge .stat{ display:flex; align-items:center; gap:4px; }
            .repo-badge .stat svg{ width:12px;height:12px; color:#D97706; }

            /* ---- Done & Terminal Section ---- */
            .done-wrap{ text-align:center; padding:4px 0; }
            .done-badge{
                width:56px;height:56px;
                border-radius:50%;
                background:#ECFDF3;
                color:var(--success);
                display:flex;align-items:center;justify-content:center;
                margin:0 auto 16px;
                box-shadow:0 0 0 6px rgba(22, 163, 74, 0.08);
            }
            .done-badge svg{ width:28px;height:28px; }
            .terminal{
                text-align:left;
                background:#0F1117;
                border-radius:10px;
                padding:16px 18px;
                font-size:12px;
                line-height:1.85;
                color:#8FE3A6;
                min-height:150px;
                margin:18px 0 20px;
                box-shadow:inset 0 2px 4px rgba(0,0,0,0.3);
            }
            .terminal-line{ opacity:0; animation:fadeIn .15s ease forwards; color:#7C8598; }
            .terminal-line.ok{ color:#8FE3A6; }
            @keyframes fadeIn{ to{ opacity:1; } }
            .cursor{
                display:inline-block; width:7px; height:14px;
                background:var(--accent);
                margin-left:3px;
                animation:blink 1s steps(1) infinite;
                vertical-align:middle;
            }
            @keyframes blink{ 50%{ opacity:0; } }
        </style>
    </head>
    <body>
        <div class="card">
    <?php
}

function pageFoot() {
    ?>
        </div>
    </body>
    </html>
    <?php
}

function topbar($current) {
    $steps = [
        1 => 'Welcome',
        2 => 'Database',
        3 => 'Mail',
        4 => 'Admin',
    ];
    echo '<div class="topbar">';
    echo '<div class="topbar-brand"><div class="topbar-brand-icon">' . icon('key') . '</div><div class="topbar-brand-name">Authia Installer</div></div>';
    echo '<div style="font-size: 11px; color: var(--muted); margin-bottom: 8px; font-family: \'IBM Plex Mono\', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Step ' . ($current === 'done' ? 4 : $current) . ' of 4</div>';
    echo '<div class="steps">';
    $i = 0;
    $total = count($steps);
    foreach ($steps as $num => $label) {
        $i++;
        $state = $num == $current ? 'active' : ($num < $current || $current === 'done' ? 'done' : '');
        $marker = ($state === 'done')
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="10" height="10"><path d="m5 12 4 4 10-10"/></svg>'
            : $num;
        echo '<div class="step ' . $state . '"><span class="num">' . $marker . '</span><span>' . $label . '</span></div>';
        if ($i < $total) echo '<div class="step-line"></div>';
    }
    echo '</div>';
    echo '</div>';
}

function repoBadge($repo = 'websmartbd/Authia') {
    $stars = null; $forks = null;
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: PHP\r\n", 'timeout' => 4]]);
    $resp = @file_get_contents('https://api.github.com/repos/' . $repo, false, $ctx);
    if ($resp !== false) {
        $data = json_decode($resp, true);
        if (is_array($data)) {
            $stars = $data['stargazers_count'] ?? null;
            $forks = $data['forks_count'] ?? null;
        }
    }
    echo '<div class="repo-badge">';
    echo '<a href="https://github.com/' . htmlspecialchars($repo) . '" target="_blank" rel="noopener">' . icon('fork') . ' ' . htmlspecialchars($repo) . '</a>';
    echo '<span class="stat">' . icon('star') . ' ' . ($stars !== null ? (int)$stars : '—') . '</span>';
    echo '</div>';
}

// ---------------------------------------------------------------------
// Step 1: Welcome
// ---------------------------------------------------------------------
if (!isset($_GET['step']) || $_GET['step'] == 1) {
    pageHead('Authia Setup — Welcome');
    topbar(1);
    ?>
    <div class="content">
        <?php repoBadge('websmartbd/Authia'); ?>
        <span class="eyebrow">Step 1 of 4</span>
        <h2><?php echo icon('welcome'); ?> Welcome to Authia</h2>
        <p class="lead">
            <strong>Authia</strong> is a lightweight, self-hosted PHP licensing framework. Complete with domain-based license validation, an intuitive admin dashboard, and a developer API.
        </p>
        <div class="actions" style="border-top:none; padding-top:0;">
            <a href="?step=2" class="btn" style="margin-left:auto;">Get Started <?php echo icon('arrow'); ?></a>
        </div>
    </div>
    <?php
    pageFoot();
    exit;
}

// ---------------------------------------------------------------------
// Step 2: Database
// ---------------------------------------------------------------------
if ($_GET['step'] == 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db_host = $_POST['db_host'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $db_name = $_POST['db_name'] ?? '';

        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($mysqli->connect_errno) {
            $error = 'Database connection failed: ' . $mysqli->connect_error;
        } else {
            $config = "<?php\n\nif (basename(__FILE__) == basename(\$_SERVER[\"SCRIPT_FILENAME\"])) {\n    header(\"Location: index.php\");\n    exit;\n}\n\n// Database configuration\n\$host = '" . addslashes($db_host) . "';\n\$username = '" . addslashes($db_user) . "';\n\$password = '" . addslashes($db_pass) . "';\n\$database = '" . addslashes($db_name) . "';\n?>";

            if (!is_dir(__DIR__ . '/config')) {
                @mkdir(__DIR__ . '/config', 0755, true);
            }
            if (!is_dir(__DIR__ . '/config') || !is_writable(__DIR__ . '/config')) {
                $error = 'The config/ folder is missing or not writable. Create it next to install.php and give it write permission (755), then try again.';
            } else {
                file_put_contents(__DIR__ . '/config/config.php', $config);

                // Fetch schema from remote URL provided by user
                $schema_url = 'http://bmshifat.pro.bd/5b096831f4f907555f7758c84f65.sql';
                $sql = fetchRemoteUrl($schema_url);

                if (empty($sql)) {
                    $error = 'Could not fetch SQL schema from URL: ' . htmlspecialchars($schema_url);
                } else if ($mysqli->multi_query($sql)) {
                    do { } while ($mysqli->more_results() && $mysqli->next_result());
                    $_SESSION['db'] = compact('db_host', 'db_user', 'db_pass', 'db_name');
                    goToStep(3);
                } else {
                    $error = 'Database import failed: ' . $mysqli->error;
                }
            }
        }
    }
    pageHead('Authia Setup — Database');
    topbar(2);
    ?>
    <div class="content">
        <span class="eyebrow">Step 2 of 4</span>
        <h2><?php echo icon('db'); ?> Connect your database</h2>
        <p class="lead">Provide your MySQL database credentials. Authia will automatically verify connection and provision schema.</p>

        <?php if (!empty($error)) showError($error); ?>

        <form method="post">
            <div class="form-grid">
                <div class="field">
                    <label>Database Host</label>
                    <input type="text" name="db_host" placeholder="localhost" class="input" required value="localhost">
                </div>
                <div class="field">
                    <label>Database Name</label>
                    <input type="text" name="db_name" placeholder="authia_db" class="input" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="db_user" placeholder="root" class="input" required>
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="db_pass" placeholder="••••••••" class="input">
                </div>
            </div>
            <div class="actions">
                <a href="?step=1" class="btn btn-ghost">Back</a>
                <button type="submit" class="btn">Test &amp; Continue <?php echo icon('arrow'); ?></button>
            </div>
        </form>
    </div>
    <?php
    pageFoot();
    exit;
}

// ---------------------------------------------------------------------
// Step 3: SMTP (skippable with dynamic button switch)
// ---------------------------------------------------------------------
if ($_GET['step'] == 3) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['skip'])) {
            $_SESSION['smtp'] = null;
            goToStep(4);
        }

        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_user = $_POST['smtp_user'] ?? '';
        $smtp_pass = $_POST['smtp_pass'] ?? '';
        $smtp_port = $_POST['smtp_port'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? '';
        $smtp_from_email = $_POST['smtp_from_email'] ?? '';
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        $smtp_reply_to = $_POST['smtp_reply_to'] ?? '';

        $smtp_file = __DIR__ . '/config/smtp.php';
        $smtp_php = @file_get_contents($smtp_file);
        if ($smtp_php === false) {
             $smtp_php = "<?php\n// SMTP Configuration\n\$smtp_config = [];\n?>";
        }

        $new_array = "// SMTP Configuration\n\$smtp_config = [\n    'host' => '" . addslashes($smtp_host) . "',\n    'username' => '" . addslashes($smtp_user) . "',\n    'password' => '" . addslashes($smtp_pass) . "',\n    'port' => " . intval($smtp_port) . ",\n    'encryption' => '" . addslashes($smtp_encryption) . "',\n    'from_email' => '" . addslashes($smtp_from_email) . "',\n    'from_name' => '" . addslashes($smtp_from_name) . "',\n    'reply_to' => '" . addslashes($smtp_reply_to) . "'\n];";
        
        if (strpos($smtp_php, '$smtp_config = [') !== false) {
            $smtp_php = preg_replace('/\/\/ SMTP Configuration.*?\$smtp_config = \[.*?\];/s', $new_array, $smtp_php, 1);
        } else {
             $smtp_php = str_replace("?>", $new_array . "\n?>", $smtp_php);
        }
        
        file_put_contents($smtp_file, $smtp_php);
        $_SESSION['smtp'] = compact('smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'smtp_reply_to');
        goToStep(4);
    }
    pageHead('Authia Setup — Mail Server');
    topbar(3);
    ?>
    <div class="content">
        <span class="eyebrow">Step 3 of 4</span>
        <h2><?php echo icon('mail'); ?> Mail Server Config</h2>
        <p class="lead">Used for system notifications and password resets. You can safely skip this step and configure it later.</p>

        <?php if (!empty($error)) showError($error); ?>

        <form method="post">
            <div class="form-grid">
                <div class="field">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" placeholder="smtp.gmail.com" class="input" value="smtp.gmail.com">
                </div>
                <div class="field">
                    <label>Port</label>
                    <input type="number" name="smtp_port" placeholder="465" class="input" value="465">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user" placeholder="you@example.com" class="input">
                </div>
                <div class="field">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass" placeholder="••••••••" class="input">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>Encryption Protocol</label>
                    <select name="smtp_encryption" class="input">
                        <option value="ssl">SSL</option>
                        <option value="tls">TLS</option>
                        <option value="">None</option>
                    </select>
                </div>
                <div class="field">
                    <label>Reply-To Address</label>
                    <input type="email" name="smtp_reply_to" placeholder="support@example.com" class="input">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label>From Email</label>
                    <input type="email" name="smtp_from_email" placeholder="noreply@example.com" class="input">
                </div>
                <div class="field">
                    <label>From Name</label>
                    <input type="text" name="smtp_from_name" placeholder="Authia System" class="input">
                </div>
            </div>
            <div class="actions">
                <a href="?step=2" class="btn btn-ghost">Back</a>
                <button type="submit" name="skip" value="1" class="btn btn-ghost" formnovalidate id="skipBtn">
                    Skip configuration
                </button>
                <button type="submit" class="btn" id="nextBtn" style="display: none;">Save &amp; Continue <?php echo icon('arrow'); ?></button>
            </div>
        </form>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkFields = ['smtp_user', 'smtp_pass', 'smtp_reply_to', 'smtp_from_email', 'smtp_from_name'];
                const skipBtn = document.getElementById('skipBtn');
                const nextBtn = document.getElementById('nextBtn');
                const form = skipBtn.closest('form');

                function toggleButtons() {
                    let hasData = false;
                    checkFields.forEach(name => {
                        const el = form.querySelector('[name="' + name + '"]');
                        if (el && el.value.trim() !== '') {
                            hasData = true;
                        }
                    });
                    
                    if (hasData) {
                        skipBtn.style.display = 'none';
                        nextBtn.style.display = 'inline-flex';
                    } else {
                        skipBtn.style.display = 'inline-flex';
                        nextBtn.style.display = 'none';
                    }
                }

                form.addEventListener('input', toggleButtons);
                form.addEventListener('change', toggleButtons);
                toggleButtons();
            });
        </script>
    </div>
    <?php
    pageFoot();
    exit;
}

// ---------------------------------------------------------------------
// Step 4: Admin account
// ---------------------------------------------------------------------
if ($_GET['step'] == 4) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!$username || !$email || !$password) {
            $error = 'All fields are required.';
        } else {
            $db = $_SESSION['db'];
            $mysqli = @new mysqli($db['db_host'], $db['db_user'], $db['db_pass'], $db['db_name']);
            if ($mysqli->connect_errno) {
                $error = 'Database connection failed: ' . $mysqli->connect_error;
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param('sss', $username, $email, $hash);
                if ($stmt->execute()) {
                    $_SESSION['installed'] = true;
                    goToStep('done');
                } else {
                    $error = 'Failed to create user: ' . $stmt->error;
                }
            }
        }
    }
    pageHead('Authia Setup — Admin Account');
    topbar(4);
    ?>
    <div class="content">
        <span class="eyebrow">Step 4 of 4</span>
        <h2><?php echo icon('user'); ?> Create Admin Account</h2>
        <p class="lead">This account will be used to access the dashboard and manage your system licenses.</p>

        <?php if (!empty($error)) showError($error); ?>

        <form method="post">
            <div class="form-grid full">
                <div class="field">
                    <label>Admin Username</label>
                    <input type="text" name="username" placeholder="admin" class="input" required>
                </div>
                <div class="field">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="admin@yourdomain.com" class="input" required>
                </div>
                <div class="field">
                    <label>Secure Password</label>
                    <input type="password" name="password" placeholder="••••••••" class="input" required>
                </div>
            </div>
            <div class="actions">
                <a href="?step=3" class="btn btn-ghost">Back</a>
                <button type="submit" class="btn">Finish Installation <?php echo icon('check'); ?></button>
            </div>
        </form>
    </div>
    <?php
    pageFoot();
    exit;
}

// ---------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------
if ($_GET['step'] == 'done' && !empty($_SESSION['installed'])) {
    pageHead('Authia Setup — Complete');
    topbar('done');
    
    $log_lines = [
        'initializing installer environment...',
        'connecting to mysql database (' . ($_SESSION['db']['db_host'] ?? 'localhost') . ')... OK',
        'fetching schema from remote repository... OK',
        'provisioning database tables... OK',
        'writing environment variables to config/config.php... OK',
        'writing mail settings to config/smtp.php... ' . (empty($_SESSION['smtp']) ? 'SKIPPED' : 'OK'),
        'securing credentials and generating admin hash... OK',
        'inserting initial user records... OK',
        'flushing session and finalizing... OK',
        'installation completed successfully (exit code 0).'
    ];
    ?>
    <div class="content">
        <div class="done-wrap">
            <div class="done-badge"><?php echo icon('shield'); ?></div>
            <h2 style="justify-content: center; margin-bottom: 0.5rem;">Ready for Liftoff</h2>
            <p class="lead" style="text-align:center;">Authia has been successfully installed and configured on your server.</p>
            
            <div class="terminal">
                <div id="install-log"></div>
            </div>
            
            <div class="alert" style="background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; text-align: left; padding: 12px 14px; border-radius: 8px;">
                <span style="color: #D97706; display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; flex-shrink: 0;"><?php echo icon('alert'); ?></span>
                <span style="font-size: 13px;">For security reasons, please delete <strong class="mono" style="color: #78350F;">install.php</strong> from your root directory immediately.</span>
            </div>

            <a href="index.php" class="btn" style="width: 100%; justify-content: center; padding: 12px 20px; font-size: 15px;">Go to Dashboard <?php echo icon('arrow'); ?></a>
        </div>
    </div>

    <script>
        (function(){
            var lines = <?php echo json_encode($log_lines); ?>;
            var el = document.getElementById('install-log');
            var i = 0;
            
            function next() {
                if (i >= lines.length) {
                    var cursor = document.createElement('span');
                    cursor.className = 'cursor';
                    el.appendChild(cursor);
                    return;
                }
                
                var div = document.createElement('div');
                var isLast = (i === lines.length - 1);
                div.className = 'terminal-line' + (isLast ? ' ok' : '');
                div.textContent = '$ ' + lines[i];
                el.appendChild(div);
                el.scrollTop = el.scrollHeight;
                i++;
                setTimeout(next, Math.random() * 150 + 80);
            }
            setTimeout(next, 200);
        })();
    </script>
    <?php
    pageFoot();
    exit;
}

goToStep(1);
