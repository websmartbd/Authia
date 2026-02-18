<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

require_once 'config/config.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed. Please try again later.");
}

// Stats Queries
$total_domains = $conn->query("SELECT COUNT(*) as count FROM domains")->fetch_assoc()['count'];
$active_domains = $conn->query("SELECT COUNT(*) as count FROM domains WHERE active = 1")->fetch_assoc()['count'];
$expired_domains = $conn->query("SELECT COUNT(*) as count FROM domains WHERE license_type != 'lifetime' AND expiry_date < CURDATE() AND `delete` != 'yes'")->fetch_assoc()['count'];
$deleted_domains = $conn->query("SELECT COUNT(*) as count FROM domains WHERE `delete` = 'yes'")->fetch_assoc()['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Authia</title>
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
    <link rel="icon" type="image/png" href="https://authia.hs.vc/security.png">
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 transition-colors duration-300">
    
    <div class="flex h-screen overflow-hidden">
        
        <?php include 'includes/sidebar-modal.php'; ?>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 pt-20 md:p-8">
                
                <!-- Page Title -->
                <div class="hidden md:flex mb-8 flex-col md:flex-row md:items-end justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Dashboard</h1>
                        <p class="text-slate-500 dark:text-slate-400 mt-2">Overview of your licensing ecosystem.</p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card 1: Total Domains -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i class="fas fa-server text-6xl text-indigo-500"></i>
                        </div>
                        <div class="flex items-center space-x-4 relative z-10">
                            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 rounded-lg">
                                <i class="fas fa-layer-group text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total Domains</p>
                                <h3 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo $total_domains; ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Active -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i class="fas fa-heartbeat text-6xl text-emerald-500"></i>
                        </div>
                        <div class="flex items-center space-x-4 relative z-10">
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded-lg">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Active</p>
                                <h3 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo $active_domains; ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Expired -->
                    <a href="expires" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm relative overflow-hidden group hover:border-amber-400 dark:hover:border-amber-600 transition-colors">
                        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                            <i class="fas fa-clock text-6xl text-amber-500"></i>
                        </div>
                        <div class="flex items-center space-x-4 relative z-10">
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-lg">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Expired</p>
                                <h3 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo $expired_domains; ?></h3>
                            </div>
                        </div>
                    </a>

                    <!-- Card 4: Deletion Queue -->
                    <a href="delete" class="bg-gradient-to-br from-red-600 to-red-700 rounded-xl p-6 shadow-lg shadow-red-600/20 relative overflow-hidden group hover:shadow-red-600/30 transition-all">
                        <div class="absolute top-0 right-0 p-4 opacity-20 group-hover:opacity-30 transition-opacity">
                            <i class="fas fa-trash text-6xl text-white"></i>
                        </div>
                        <div class="flex items-center space-x-4 relative z-10">
                            <div class="p-3 bg-white/20 rounded-lg backdrop-blur-sm">
                                <i class="fas fa-flag text-xl text-white"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-red-100 uppercase tracking-wide">Pending Deletion</p>
                                <h3 class="text-2xl font-black text-white"><?php echo $deleted_domains; ?></h3>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Actions & System Status -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                     
                     <!-- Quick Actions -->
                     <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                        <h3 class="font-bold text-lg text-slate-900 dark:text-white mb-6">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="add-domain" class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-indigo-500 hover:shadow-md transition group">
                                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition">
                                    <i class="fas fa-plus text-indigo-600 dark:text-indigo-400 text-lg"></i>
                                </div>
                                <span class="text-sm font-bold text-slate-600 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">New License</span>
                            </a>
                            <a href="mail" class="flex flex-col items-center justify-center p-6 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-emerald-500 hover:shadow-md transition group">
                                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition">
                                    <i class="fas fa-envelope text-emerald-600 dark:text-emerald-400 text-lg"></i>
                                </div>
                                <span class="text-sm font-bold text-slate-600 dark:text-slate-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">SMTP Config</span>
                            </a>
                        </div>
                     </div>

                     <!-- System Status -->
                     <div class="bg-indigo-600 rounded-xl p-8 shadow-xl shadow-indigo-900/20 text-white relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute -right-10 -bottom-10 opacity-10">
                            <i class="fab fa-php text-9xl transform rotate-12"></i>
                        </div>
                        
                        <div>
                            <div class="flex items-center space-x-2 mb-2">
                                <div class="w-2.5 h-2.5 bg-green-400 rounded-full animate-pulse"></div>
                                <span class="text-indigo-200 text-xs font-bold uppercase tracking-widest">System Operational</span>
                            </div>
                            <h3 class="font-bold text-2xl mb-2 relative z-10">System Status</h3>
                            <p class="text-indigo-100/80 text-sm mb-6 relative z-10 max-w-sm">
                                All services are running optimally. secure connection established.
                            </p>
                        </div>
                        
                        <div class="space-y-4 relative z-10 bg-indigo-700/30 p-4 rounded-xl backdrop-blur-sm border border-indigo-500/30">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-indigo-200 font-medium"><i class="fab fa-php mr-2"></i>PHP Version</span>
                                <span class="font-mono bg-indigo-500/40 px-3 py-1 rounded text-white text-xs"><?php echo phpversion(); ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-indigo-200 font-medium"><i class="fas fa-server mr-2"></i>Server IP</span>
                                <span class="font-mono bg-indigo-500/40 px-3 py-1 rounded text-white text-xs"><?php echo $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-indigo-200 font-medium"><i class="fas fa-database mr-2"></i>Database</span>
                                <span class="font-mono bg-indigo-500/40 px-3 py-1 rounded text-white text-xs">Connected</span>
                            </div>
                        </div>
                     </div>
                </div>

            </main>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
