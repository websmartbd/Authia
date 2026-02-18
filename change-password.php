<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration files
require_once 'config/config.php';
require_once 'config/smtp.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

// Generate CSRF token
CSRFProtection::generateToken();

$success_message = null;
$error_message = null;
$user_email = '';

// Get current user email
$conn = new mysqli($host, $username, $password, $database);
if (!$conn->connect_error) {
    $admin_username = 'admin';
    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $admin_username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_email = $row['email'];
        }
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Security validation failed. Please try again.";
        header("Location: change-password.php");
        exit;
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RateLimiter::check($client_ip, 'change_password')) {
        $_SESSION['error_message'] = "Too many attempts. Please wait 15 minutes.";
        header("Location: change-password.php");
        exit;
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = InputValidator::sanitizeEmail($_POST['email'] ?? '');
    
    if ($email === false) {
        $_SESSION['error_message'] = "Invalid email address.";
        header("Location: change-password.php");
        exit;
    }

    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        $_SESSION['error_message'] = "Connection failed.";
        header("Location: change-password.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $_SESSION['error_message'] = "Current password is incorrect.";
        $conn->close();
        header("Location: change-password.php");
        exit;
    }

    $updates = [];
    $params = [];
    $types = "";

    if (!empty($new_password) || !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "New passwords do not match.";
            $conn->close();
            header("Location: change-password.php");
            exit;
        }
        
        $pwdCheck = InputValidator::validatePassword($new_password);
        if (!$pwdCheck['valid']) {
            $_SESSION['error_message'] = $pwdCheck['message'];
            $conn->close();
            header("Location: change-password.php");
            exit;
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        $updates[] = "password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }

    if (!empty($email)) {
        $updates[] = "email = ?";
        $params[] = $email;
        $types .= "s";
    }

    if (!empty($updates)) {
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $user['id'];
        $types .= "i";
        $update_stmt = $conn->prepare($sql);
        $update_stmt->bind_param($types, ...$params);

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Account updated successfully.";
        } else {
            $_SESSION['error_message'] = "Update failed.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "No changes made.";
    }

    $conn->close();
    header("Location: change-password.php");
    exit;
}

$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Settings - Authia</title>
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
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark')
    } else {
        document.documentElement.classList.remove('dark')
    }
  </script>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">
  <div class="flex h-screen overflow-hidden">
    <?php include 'includes/sidebar-modal.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
      <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 pt-20 md:p-8 flex justify-center items-start">
        <div class="w-full max-w-2xl text-center md:text-left">
          
          <div class="hidden md:block mb-8">
             <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Account Security</h1>
             <p class="text-sm text-slate-500 dark:text-slate-400">Manage your credentials and recovery email.</p>
          </div>
          
          <?php if ($success_message): ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800 p-4 rounded-xl flex items-center">
              <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
              <p class="text-sm text-emerald-700 dark:text-emerald-400 font-bold"><?php echo InputValidator::escapeHtml($success_message); ?></p>
            </div>
          <?php endif; ?>

          <?php if ($error_message): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-center">
              <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
              <p class="text-sm text-red-700 dark:text-red-400 font-bold"><?php echo InputValidator::escapeHtml($error_message); ?></p>
            </div>
          <?php endif; ?>
          
          <form method="POST" class="bg-white dark:bg-slate-900 shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 space-y-6 text-left">
            <?php echo CSRFProtection::getTokenField(); ?>
            
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center"><i class="fas fa-user-lock mr-2 text-indigo-500"></i> Verification</h3>
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Current Password</label>
                    <div class="relative">
                        <input type="password" name="current_password" id="current_password" required 
                           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                           placeholder="Confirm identity">
                        <button type="button" onclick="togglePass('current_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center"><i class="fas fa-key mr-2 text-indigo-500"></i> New Credentials</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">New Password</label>
                        <div class="relative">
                            <input type="password" name="new_password" id="new_password" 
                               class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                               placeholder="Optional">
                            <button type="button" onclick="togglePass('new_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Confirm Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" 
                               class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                               placeholder="Optional">
                            <button type="button" onclick="togglePass('confirm_password', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Recovery Email</label>
                  <input type="email" name="email" required 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                    placeholder="Enter email address" value="<?php echo InputValidator::escapeHtml($user_email); ?>">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-3">
              <a href="dashboard" class="px-6 py-3 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Cancel
              </a>
              <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-900/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                Update Account
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
  
  <script>
    function togglePass(id, btn) {
        const input = document.getElementById(id);
        const icon = btn.querySelector('i');
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