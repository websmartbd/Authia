<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';
require_once 'config/smtp.php';

// Set timezone for consistent expiration checks
date_default_timezone_set('UTC');

// Generate CSRF token
CSRFProtection::generateToken();

// Get messages from session
$error_message = $_SESSION['reset_error'] ?? null;
$success_message = $_SESSION['reset_success'] ?? null;
$step = $_SESSION['reset_step'] ?? 'request'; // Steps: request, verify, reset, complete

// Clear messages from session immediately after reading
unset($_SESSION['reset_error'], $_SESSION['reset_success']);

// Connect to the database
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token using security class
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['reset_error'] = "Security validation failed. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        // Rate limiting for all reset actions
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::check($client_ip, 'reset_password')) {
            $_SESSION['reset_error'] = "Too many attempts. Please try again in 15 minutes.";
        } else {
            // STEP 1: Request Code
            if ($action === 'request_code') {
                $login_input = trim($_POST['login_input'] ?? '');
                
                if (empty($login_input)) {
                    $_SESSION['reset_error'] = "Please enter your username or email.";
                } else {
                    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE username = ? OR email = ?");
                    $stmt->bind_param("ss", $login_input, $login_input);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    if ($user) {
                        // Generate 6-digit OTP
                        $otp_code = sprintf("%06d", mt_rand(100000, 999999));
                        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        // Store in DB
                        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                        $update->bind_param("ssi", $otp_code, $expires, $user['id']);
                        
                        if ($update->execute()) {
                            if (!empty($user['email'])) {
                                $email_content = generate_otp_email($otp_code, $user['username']);
                                if (send_html_email($user['email'], "Verification Code: $otp_code", $email_content)) {
                                    $_SESSION['reset_user_id'] = $user['id'];
                                    $_SESSION['reset_step'] = 'verify';
                                } else {
                                    $_SESSION['reset_error'] = "Failed to send email. Please check your SMTP settings.";
                                }
                            } else {
                                $_SESSION['reset_error'] = "No email associated with this account. (Dev Code: $otp_code)";
                            }
                        }
                    } else {
                        $_SESSION['reset_error'] = "If an account exists, a code has been sent.";
                        $_SESSION['reset_step'] = 'verify'; 
                    }
                }
            }
            // STEP 2: Verify Code
            elseif ($action === 'verify_code') {
                $code = trim($_POST['otp_code'] ?? '');
                $user_id = $_SESSION['reset_user_id'] ?? 0;
                $now = date('Y-m-d H:i:s');
                
                $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND reset_token = ? AND reset_token_expires > ?");
                $stmt->bind_param("iss", $user_id, $code, $now);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $_SESSION['reset_step'] = 'reset';
                } else {
                    $_SESSION['reset_error'] = "Invalid or expired verification code.";
                }
            }
            // STEP 3: Reset Password
            elseif ($action === 'reset_password') {
                $new_pass = $_POST['new_password'] ?? '';
                $conf_pass = $_POST['confirm_password'] ?? '';
                $user_id = $_SESSION['reset_user_id'] ?? 0;
                
                $pwdCheck = InputValidator::validatePassword($new_pass);
                if (!$pwdCheck['valid']) {
                    $_SESSION['reset_error'] = $pwdCheck['message'];
                } elseif ($new_pass !== $conf_pass) {
                    $_SESSION['reset_error'] = "Passwords do not match.";
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_ARGON2ID);
                    $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                    $update->bind_param("si", $hashed, $user_id);
                    
                    if ($update->execute()) {
                        $_SESSION['reset_success'] = "Password updated successfully!";
                        $_SESSION['reset_step'] = 'complete';
                        unset($_SESSION['reset_user_id']);
                        RateLimiter::reset($client_ip, 'reset_password');
                    }
                }
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: reset-password.php");
    exit;
}

// Reset flow if requested
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_step'], $_SESSION['reset_error'], $_SESSION['reset_success']);
    header("Location: reset-password.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Authia - Recovery Protocol</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            }
        }
    }
  </script>
  <script>
    if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; transition: background-color 0.3s, color 0.3s; }
    .gradient-bg { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); }
    .otp-input:focus { letter-spacing: 0.5em; }
  </style>
    <link rel="icon" type="image/png" href="https://authia.hs.vc/security.png">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex items-center justify-center p-6 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-indigo-500/10 via-transparent to-transparent">

    <!-- Theme Toggle Fixed -->
    <button id="theme-toggle" class="fixed top-6 right-6 p-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:scale-110 transition-all z-50">
        <i class="fas fa-moon dark:hidden text-xs"></i>
        <i class="fas fa-sun hidden dark:block text-xs"></i>
    </button>

    <div class="w-full max-w-5xl flex flex-col lg:flex-row bg-white dark:bg-slate-900 rounded-[3rem] overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-800">
        
        <!-- Left Panel: Contextual Branding -->
        <div class="hidden lg:flex lg:w-1/2 gradient-bg p-16 flex-col justify-between relative overflow-hidden text-white">
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>

            <div class="z-10 space-y-6">
                <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center border border-white/10 backdrop-blur-sm">
                    <i class="fas fa-user-lock text-3xl"></i>
                </div>
                <div class="space-y-2">
                    <h1 class="text-4xl font-extrabold tracking-tight">Access Recovery</h1>
                    <p class="text-slate-400 text-lg">Authorization reset protocol for strategic assets.</p>
                </div>
            </div>

            <div class="z-10 space-y-8">
                <div class="flex flex-col space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black <?php echo ($step === 'request' ? 'bg-indigo-500 ring-4 ring-indigo-500/30' : 'opacity-50'); ?>">1</div>
                        <p class="text-xs font-bold uppercase tracking-widest <?php echo ($step === 'request' ? 'text-white' : 'text-slate-500'); ?>">Identify Vector</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black <?php echo ($step === 'verify' ? 'bg-indigo-500 ring-4 ring-indigo-500/30' : 'opacity-50'); ?>">2</div>
                        <p class="text-xs font-bold uppercase tracking-widest <?php echo ($step === 'verify' ? 'text-white' : 'text-slate-500'); ?>">Validate Token</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black <?php echo ($step === 'reset' ? 'bg-indigo-500 ring-4 ring-indigo-500/30' : 'opacity-50'); ?>">3</div>
                        <p class="text-xs font-bold uppercase tracking-widest <?php echo ($step === 'reset' ? 'text-white' : 'text-slate-500'); ?>">Re-Initialize Cipher</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-[10px] font-black <?php echo ($step === 'complete' ? 'bg-emerald-500 ring-4 ring-emerald-500/30' : 'opacity-50'); ?>"><i class="fas fa-check text-[10px]"></i></div>
                        <p class="text-xs font-bold uppercase tracking-widest <?php echo ($step === 'complete' ? 'text-white' : 'text-slate-500'); ?>">Integrity Restored</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2 text-slate-500 text-[10px] font-bold uppercase tracking-[0.3em]">
                    <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                    <span>Secure Synchronization Active</span>
                </div>
            </div>
        </div>

        <!-- Right Panel: Dynamic Interaction -->
        <div class="w-full lg:w-1/2 p-10 lg:p-16 flex flex-col justify-center bg-slate-50/50 dark:bg-slate-900/50">
            <div class="max-w-md mx-auto w-full space-y-10">
                
                <!-- Status Header -->
                <div class="space-y-2 text-center lg:text-left">
                    <h2 class="text-3xl font-extrabold tracking-tight">
                        <?php 
                        if ($step === 'request') echo "Vector Initialization";
                        elseif ($step === 'verify') echo "Token Validation";
                        elseif ($step === 'reset') echo "Cipher Propagation";
                        else echo "Session Restored";
                        ?>
                    </h2>
                    <p class="text-slate-500 dark:text-slate-400">
                        <?php 
                        if ($step === 'request') echo "Specify your unique identifier to trigger an authentication override.";
                        elseif ($step === 'verify') echo "Verify the high-security transmission sent to your primary node.";
                        elseif ($step === 'reset') echo "Declare a new operational cipher to secure your administrative enclave.";
                        else echo "Credential synchronization is complete. Strategic access is now available.";
                        ?>
                    </p>
                </div>

                <!-- Strategic Feedback -->
                <?php if ($error_message): ?>
                <div class="bg-red-500/10 border border-red-500/20 p-4 rounded-2xl flex items-center space-x-3 text-red-600 dark:text-red-400 animate-bounce">
                    <i class="fas fa-radiation text-sm"></i>
                    <p class="text-[10px] font-black uppercase tracking-widest"><?php echo InputValidator::escapeHtml($error_message); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 p-4 rounded-2xl flex items-center space-x-3 text-emerald-600 dark:text-emerald-400 animate-pulse">
                    <i class="fas fa-circle-check text-sm"></i>
                    <p class="text-[10px] font-black uppercase tracking-widest"><?php echo InputValidator::escapeHtml($success_message); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($step === 'complete'): ?>
                <div class="space-y-6 pt-6 animate-fadeIn">
                    <div class="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto text-emerald-500 shadow-xl shadow-emerald-500/20">
                        <i class="fas fa-lock-open text-3xl"></i>
                    </div>
                    <a href="login" class="w-full py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-3xl text-xs font-bold uppercase tracking-widest transition-all hover:scale-[1.02] shadow-xl text-center flex items-center justify-center space-x-2">
                        <span>Initiate Strategic Session</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php else: ?>
                <form method="POST" class="space-y-8">
                    <?php echo CSRFProtection::getTokenField(); ?>

                    <?php if ($step === 'request'): ?>
                        <input type="hidden" name="action" value="request_code">
                        <div class="space-y-2 group">
                            <label for="login_input" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Operational ID</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                    <i class="fas fa-id-badge text-xs"></i>
                                </div>
                                <input type="text" name="login_input" id="login_input" required 
                                    class="w-full pl-11 pr-4 py-4 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-3xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium"
                                    placeholder="Username or System Email">
                            </div>
                        </div>
                        <button type="submit" id="submitBtn" class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-3xl text-xs font-bold uppercase tracking-widest transition-all shadow-xl shadow-indigo-600/20 active:scale-[0.98] flex items-center justify-center space-x-3 group">
                            <span>Dispatch Recovery Token</span>
                            <i id="loadingIndicator" class="fas fa-circle-notch fa-spin hidden"></i>
                            <i class="fas fa-paper-plane text-[10px] group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform"></i>
                        </button>

                    <?php elseif ($step === 'verify'): ?>
                        <input type="hidden" name="action" value="verify_code">
                        <div class="space-y-6">
                            <div class="text-center space-y-2">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Temporal Token Verification</label>
                                <input type="text" name="otp_code" id="otp_code" required maxlength="6" 
                                    class="otp-input w-full text-center text-4xl font-black py-6 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-[2rem] focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition-all tracking-[0.3em] font-mono text-indigo-600"
                                    placeholder="000000">
                            </div>
                            <button type="submit" id="submitBtn" class="w-full py-4 bg-slate-900 dark:bg-white text-white dark:text-slate-900 rounded-3xl text-xs font-bold uppercase tracking-widest transition-all shadow-xl active:scale-[0.98] flex items-center justify-center space-x-3">
                                <span>Validate Protocol</span>
                                <i id="loadingIndicator" class="fas fa-circle-notch fa-spin hidden"></i>
                            </button>
                            <div class="text-center pt-4">
                                <a href="?restart=1" class="text-[10px] font-bold text-indigo-500 hover:text-indigo-400 uppercase tracking-widest transition-colors flex items-center justify-center space-x-2 group">
                                    <i class="fas fa-rotate-left group-hover:rotate-180 transition-transform"></i>
                                    <span>Signal Redispatch</span>
                                </a>
                            </div>
                        </div>

                    <?php elseif ($step === 'reset'): ?>
                        <input type="hidden" name="action" value="reset_password">
                        <div class="space-y-6">
                            <div class="space-y-2 group">
                                <label for="new_password" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">New Strategic Cipher</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                        <i class="fas fa-shield-halved text-xs"></i>
                                    </div>
                                    <input type="password" id="new_password" name="new_password" required minlength="6"
                                        class="w-full pl-11 pr-12 py-4 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium font-mono">
                                    <button type="button" onclick="toggleCipher('new_password', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-500 transition-colors">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-2 group">
                                <label for="confirm_password" class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Synchronize Verification</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                        <i class="fas fa-fingerprint text-xs"></i>
                                    </div>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                        class="w-full pl-11 pr-12 py-4 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all text-sm font-medium font-mono">
                                    <button type="button" onclick="toggleCipher('confirm_password', this)" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-indigo-500 transition-colors">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" id="submitBtn" class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-3xl text-xs font-bold uppercase tracking-widest transition-all shadow-xl shadow-indigo-600/20 active:scale-[0.98] flex items-center justify-center space-x-3">
                                <span>Commit Cipher Rotation</span>
                                <i id="loadingIndicator" class="fas fa-circle-notch fa-spin hidden"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

                <!-- Operational Termination -->
                <div class="mt-10 pt-10 border-t border-slate-200 dark:border-slate-800 text-center">
                    <a href="login" class="text-[10px] font-bold text-slate-400 hover:text-indigo-600 uppercase tracking-widest transition-colors flex items-center justify-center space-x-2 group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        <span>Return to Strategic Entry</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function toggleCipher(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash text-xs';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye text-xs';
            }
        }

        // Theme Toggle Support
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // Form Submission Feedback
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                const loader = document.getElementById('loadingIndicator');
                if (btn && loader) {
                    btn.classList.add('opacity-80', 'cursor-not-allowed');
                    loader.classList.remove('hidden');
                }
            });
        }

        // Auto-focus logic
        window.addEventListener('load', () => {
            const primaryInput = document.getElementById('login_input') || document.getElementById('otp_code') || document.getElementById('new_password');
            if (primaryInput) primaryInput.focus();
        });
    </script>
</body>
</html>