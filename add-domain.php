<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Handle add domain form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        die('Security validation failed');
    }
    
    $email = InputValidator::sanitizeEmail($_POST['email'] ?? '');
    $domain = InputValidator::validateDomain($_POST['domain'] ?? '');
    $active = InputValidator::validateInt($_POST['active'] ?? 0, 0, 1);
    $delete = in_array($_POST['delete'] ?? 'no', ['yes', 'no']) ? $_POST['delete'] : 'no';
    $message = InputValidator::sanitizeString($_POST['message'] ?? '');
    $name = InputValidator::sanitizeString($_POST['name'] ?? '');
    $license_type = in_array($_POST['license_type'] ?? '', ['monthly', 'yearly', 'lifetime']) ? $_POST['license_type'] : 'monthly';

    if ($email === false) $error_message = "Invalid email address.";
    elseif ($domain === false) $error_message = "Invalid domain name.";
    else {
        // Calculate expiry
        $expiry_date = null;
        if ($license_type === 'monthly') $expiry_date = date('Y-m-d', strtotime('+30 days'));
        elseif ($license_type === 'yearly') $expiry_date = date('Y-m-d', strtotime('+1 year'));

        $is_expired = ($license_type !== 'lifetime' && !empty($expiry_date) && $expiry_date < date('Y-m-d'));
        if ($delete === 'yes' && $active == 1 && !$is_expired) {
            $error_message = "A domain can only be flagged for deletion if it is either Inactive or Expired.";
        } else {
            $sql = "INSERT INTO domains (email, domain, active, `delete`, message, name, license_type, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssss", $email, $domain, $active, $delete, $message, $name, $license_type, $expiry_date);

            if ($stmt->execute()) {
                $domain_id = $stmt->insert_id;
                $new_api_key = 'bm-' . substr(bin2hex(random_bytes(15)), 0, 28);
                $api_stmt = $conn->prepare("INSERT INTO licenses (api_key, domain_id) VALUES (?, ?)");
                $api_stmt->bind_param("si", $new_api_key, $domain_id);
                $api_stmt->execute();
                $api_stmt->close();

                header("Location: domains.php");
                exit;
            } else {
                $error_message = "Error adding record. Please try again.";
            }
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Domain - Authia</title>
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
        <div class="w-full max-w-3xl">
            
            <div class="hidden md:flex items-center justify-between mb-8">
                <div>
                   <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Add New Domain</h1> 
                   <p class="text-sm text-slate-500 dark:text-slate-400">Register a new license in the system.</p>
                </div>
                <a href="domains.php" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition"><i class="fas fa-times text-xl"></i></a>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-sm text-red-700 dark:text-red-400 font-medium"><?php echo InputValidator::escapeHtml($error_message); ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" class="bg-white dark:bg-slate-900 shadow-sm rounded-2xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 space-y-6">
              <?php echo CSRFProtection::getTokenField(); ?>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Client Name</label>
                  <input type="text" name="name" required 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                    placeholder="Enter Client Name">
                </div>
                
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email Address</label>
                  <input type="email" name="email" required 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400" 
                    placeholder="contact@example.com">
                </div>
              </div>
              
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Domain Name</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400"><i class="fas fa-globe"></i></span>
                    <input type="text" name="domain" required 
                        class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 font-mono" 
                        placeholder="example.com">
                </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">License Type</label>
                  <select name="license_type" required 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                      <option value="monthly">Monthly</option>
                      <option value="yearly">Yearly</option>
                      <option value="lifetime">Lifetime</option>
                  </select>
                </div>
                
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</label>
                  <select name="active" 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                  </select>
                </div>

                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deletion</label>
                  <select name="delete" 
                    class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                      <option value="no">None</option>
                      <option value="yes">Flagged</option>
                  </select>
                </div>
              </div>
              
              <div class="space-y-2">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Notes (Internal)</label>
                <textarea name="message" rows="4"
                  class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all placeholder-slate-400 resize-none" 
                  placeholder="Additional context..."></textarea>
              </div>
              
              <div class="flex flex-col sm:flex-row justify-end pt-4 border-t border-slate-100 dark:border-slate-800 gap-3">
                <a href="domains.php" class="px-6 py-3 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 rounded-xl font-bold text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition text-center">
                  Cancel
                </a>
                <button type="submit" name="add" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-900/20 transition-all transform hover:-translate-y-0.5 active:translate-y-0 text-center">
                  Create License
                </button>
              </div>
            </form>
        </div>
      </main>
    </div>
  </div>
</body>
</html>