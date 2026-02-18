<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include config
require_once 'config/config.php';

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_smtp'])) {
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        die('Security validation failed');
    }

    $smtp_host = InputValidator::sanitizeString($_POST['host'] ?? '');
    $smtp_user = InputValidator::sanitizeString($_POST['username'] ?? '');
    $smtp_pass = $_POST['password'] ?? ''; 
    $smtp_port = InputValidator::validateInt($_POST['port'] ?? 465, 1, 65535);
    $smtp_encryption = in_array($_POST['encryption'] ?? 'ssl', ['ssl', 'tls', 'none']) ? $_POST['encryption'] : 'ssl';
    $from_email = InputValidator::sanitizeEmail($_POST['from_email'] ?? '');
    $from_name = InputValidator::sanitizeString($_POST['from_name'] ?? '');
    $reply_to = InputValidator::sanitizeEmail($_POST['reply_to'] ?? '');

    if (!$from_email || !$reply_to) {
        $error_message = "Invalid email format detected.";
    } else {
        $sql = "UPDATE smtp_settings SET host=?, username=?, password=?, port=?, encryption=?, from_email=?, from_name=?, reply_to=? WHERE id=1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissss", $smtp_host, $smtp_user, $smtp_pass, $smtp_port, $smtp_encryption, $from_email, $from_name, $reply_to);
        
        if ($stmt->execute()) {
            $success_message = "SMTP settings updated successfully!";
        } else {
            $error_message = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch current settings
$result = $conn->query("SELECT * FROM smtp_settings WHERE id = 1");
$smtp = $result->fetch_assoc();

if (!$smtp) {
    $conn->query("INSERT INTO smtp_settings (id, host, username, password, port, encryption, from_email, from_name, reply_to) 
                 VALUES (1, 'smtp.example.com', 'user@example.com', '', 465, 'ssl', 'noreply@example.com', 'Authia System', 'support@example.com')");
    $result = $conn->query("SELECT * FROM smtp_settings WHERE id = 1");
    $smtp = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Settings - Authia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        slate: {
                            850: '#152033',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/sidebar-modal.php'; ?>

    <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 pt-20 md:p-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="hidden md:block mb-8">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white mb-2">Email Configuration</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Securely manage your SMTP credentials.</p>
            </div>

            <?php if ($success_message): ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800 p-4 rounded-xl flex items-center">
                <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
                <p class="text-sm text-emerald-700 dark:text-emerald-400 font-medium"><?php echo $success_message; ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-sm text-red-700 dark:text-red-400 font-medium"><?php echo $error_message; ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" action="mail" class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                <?php echo CSRFProtection::getTokenField(); ?>
                
                <div class="p-6 sm:p-8 space-y-8">
                    <!-- SMTP Server Section -->
                    <div>
                        <div class="flex items-center mb-6">
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-3">
                                <i class="fas fa-server text-sm"></i>
                            </span>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Server Connection</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">SMTP Host</label>
                                <input type="text" name="host" value="<?php echo InputValidator::escapeHtml($smtp['host']); ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-mono" 
                                       placeholder="e.g., smtp.gmail.com" required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Encryption</label>
                                <select name="encryption" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                                    <option value="ssl" <?php echo $smtp['encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="tls" <?php echo $smtp['encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="none" <?php echo $smtp['encryption'] == 'none' ? 'selected' : ''; ?>>None</option>
                                </select>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Port</label>
                                <input type="number" name="port" value="<?php echo (int)$smtp['port']; ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-mono" 
                                       placeholder="e.g., 465" required>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800"></div>

                    <!-- Authentication Section -->
                    <div>
                        <div class="flex items-center mb-6">
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-3">
                                <i class="fas fa-key text-sm"></i>
                            </span>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Authentication</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Username</label>
                                <input type="text" name="username" value="<?php echo InputValidator::escapeHtml($smtp['username']); ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" 
                                       placeholder="Email address" required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Password</label>
                                <div class="relative">
                                    <input type="password" name="password" id="smtp_pass" value="<?php echo InputValidator::escapeHtml($smtp['password']); ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all font-mono" 
                                           placeholder="SMTP Password">
                                    <button type="button" onclick="togglePass()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                                        <i class="fas fa-eye" id="eye_icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 dark:border-slate-800"></div>

                    <!-- Sender Info Section -->
                    <div>
                        <div class="flex items-center mb-6">
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mr-3">
                                <i class="fas fa-paper-plane text-sm"></i>
                            </span>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Sender Details</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">From Name</label>
                                <input type="text" name="from_name" value="<?php echo InputValidator::escapeHtml($smtp['from_name']); ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" 
                                       placeholder="System Display Name" required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">From Email</label>
                                <input type="email" name="from_email" value="<?php echo InputValidator::escapeHtml($smtp['from_email']); ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" 
                                       placeholder="noreply@example.com" required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Reply-To Email</label>
                                <input type="email" name="reply_to" value="<?php echo InputValidator::escapeHtml($smtp['reply_to']); ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all" 
                                       placeholder="support@example.com" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-950/50 px-8 py-6 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                    <button type="submit" name="update_smtp" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-900/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                        <i class="fas fa-save mr-2"></i>Update Configuration
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    function togglePass() {
        const pass = document.getElementById('smtp_pass');
        const icon = document.getElementById('eye_icon');
        if (pass.type === 'password') {
            pass.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            pass.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
<?php $conn->close(); ?>
