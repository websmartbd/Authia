<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();
require_once 'config/config.php';

$error_message = null;
CSRFProtection::generateToken();

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: dashboard");
    exit;
}

// Remember Me Logic
if (!isset($_SESSION['authenticated']) && isset($_COOKIE['remember_me'])) {
    $remember_token = $_COOKIE['remember_me'];
    $conn = new mysqli($host, $username, $password, $database);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE remember_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $remember_token);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['id'];
                
                $new_token = bin2hex(random_bytes(32));
                $up_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $up_stmt->bind_param("si", $new_token, $user['id']);
                $up_stmt->execute();
                setcookie('remember_me', $new_token, time() + (86400 * 30), "/", "", false, true);
                
                header("Location: dashboard");
                exit;
            } else {
                setcookie('remember_me', '', time() - 3600, "/");
            }
        }
        $conn->close();
    }
}

// Login Handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Security validation failed.";
    } else {
        $login_input = InputValidator::sanitizeString($_POST['login_input'] ?? '');
        $password_input = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!RateLimiter::check($client_ip, 'login')) {
            $error_message = "Too many attempts. Try again in 15 minutes.";
        } else {
            $conn = new mysqli($host, $username, $password, $database);
            if ($conn->connect_error) {
                $error_message = "Connection error.";
            } else {
                $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $login_input, $login_input);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user && password_verify($password_input, $user['password'])) {
                    RateLimiter::reset($client_ip, 'login');
                    session_regenerate_id(true);
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $user['id'];

                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $up_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                        $up_stmt->bind_param("si", $token, $user['id']);
                        $up_stmt->execute();
                        setcookie('remember_me', $token, time() + (86400 * 30), "/", "", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
                    } else {
                        $up_stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                        $up_stmt->bind_param("i", $user['id']);
                        $up_stmt->execute();
                        if (isset($_COOKIE['remember_me'])) setcookie('remember_me', '', time() - 3600, "/");
                    }
                    header("Location: dashboard");
                    exit;
                } else {
                    $error_message = "Invalid credentials.";
                }
                $conn->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - Authia</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        // Check system preference for dark mode
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <link rel="icon" type="image/png" href="https://authia.hs.vc/security.png">
</head>
<body class="bg-slate-50 dark:bg-slate-950 transition-colors duration-300">

<div class="flex min-h-screen">
    <!-- Left Panel (Visual) -->
    <div class="hidden lg:flex w-1/2 bg-slate-900 relative overflow-hidden items-center justify-center">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/20 to-purple-900/40 z-10"></div>
        <div class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 z-0"></div>
        
        <div class="relative z-20 text-center px-12">
            <div class="flex items-center justify-center mx-auto mb-8">
                <img src="https://authia.hs.vc/security.png" alt="Authia Security" class="w-20 h-20 object-contain drop-shadow-lg">
            </div>
            <h1 class="text-4xl font-bold text-white mb-4 tracking-tight">Authia Admin</h1>
            <p class="text-indigo-200 text-lg max-w-md mx-auto leading-relaxed">Secure, scalable, and modern license management for your applications.</p>
        </div>

        <!-- Decorative Circles -->
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>
    </div>

    <!-- Right Panel (Login Form) -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
        <div class="w-full max-w-md space-y-8">
            <div class="text-center lg:text-left">
                <div class="lg:hidden flex items-center justify-center mx-auto mb-6">
                    <img src="https://authia.hs.vc/security.png" alt="Authia Security" class="w-16 h-16 object-contain drop-shadow-md">
                </div>
                <h2 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Welcome back</h2>
                <p class="mt-2 text-slate-500 dark:text-slate-400">Please enter your details to sign in.</p>
            </div>

            <?php if ($error_message): ?>
            <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-center animate-pulse">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-sm text-red-700 dark:text-red-400 font-bold"><?php echo InputValidator::escapeHtml($error_message); ?></p>
            </div>
            <?php endif; ?>

            <form class="space-y-6" action="login" method="POST">
                <?php echo CSRFProtection::getTokenField(); ?>
                
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Username or Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"><i class="fas fa-user"></i></span>
                        <input type="text" name="login_input" required 
                           class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 shadow-sm" 
                           placeholder="admin">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" required 
                           class="w-full pl-10 pr-10 py-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 shadow-sm" 
                           placeholder="••••••••">
                        <button type="button" onclick="togglePass()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-slate-600 dark:text-slate-400">Remember me</label>
                    </div>
                    <div class="text-sm">
                        <a href="reset-password" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-xl shadow-lg shadow-indigo-600/20 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all transform hover:-translate-y-0.5">
                    Sign in
                </button>
            </form>
            
            <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-8">
                &copy; <?php echo date("Y"); ?> Authia System. All rights reserved.
            </p>
        </div>
    </div>
</div>

<script>
    function togglePass() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

</body>
</html>
