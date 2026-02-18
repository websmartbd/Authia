<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Handle search with input validation
$search = InputValidator::sanitizeString($_GET['search'] ?? '');

// Base SQL with expiration filter
$sql = "SELECT d.id, d.name, d.email, d.domain, d.active, d.message, d.`delete`, d.license_type, d.expiry_date, a.api_key 
        FROM domains d 
        LEFT JOIN licenses a ON d.id = a.domain_id 
        WHERE (d.license_type = 'lifetime' OR d.expiry_date >= CURDATE())
        AND d.`delete` != 'yes'";

if (!empty($search)) {
    $sql .= " AND (d.name LIKE ? OR d.email LIKE ? OR d.domain LIKE ?)";
    $search_param = "%{$search}%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}

// Handle regenerate key action with CSRF protection
if (isset($_GET['regenerate_key'])) {
    if (!isset($_GET['csrf_token']) || !CSRFProtection::validateToken($_GET['csrf_token'])) {
        die('Security validation failed');
    }
    
    $domain_id = InputValidator::validateInt($_GET['regenerate_key'], 1);
    if ($domain_id === false) {
        die('Invalid domain ID');
    }
    $new_api_key = 'bm-' . substr(bin2hex(random_bytes(15)), 0, 28);
    
    $check_sql = "SELECT id FROM licenses WHERE domain_id = $domain_id";
    $result_check = $conn->query($check_sql);
    
    if ($result_check->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE licenses SET api_key = ? WHERE domain_id = ?");
        $stmt->bind_param("si", $new_api_key, $domain_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO licenses (api_key, domain_id) VALUES (?, ?)");
        $stmt->bind_param("si", $new_api_key, $domain_id);
    }
    $stmt->execute();
    $stmt->close();
    
    header("Location: domains.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domains - Authia</title>
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

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 pt-20 md:p-8">
        
        <!-- Header -->
        <div class="flex mb-8 flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="hidden md:block">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Active Domains</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Manage your active software licenses.</p>
          </div>
          
          <!-- Search -->
          <form method="GET" class="relative w-full md:max-w-xs">
            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block pl-10 p-2.5 outline-none shadow-sm transition-all" 
                   placeholder="Search domains...">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-slate-400"></i>
            </div>
          </form>
        </div>

        <!-- Domain Table -->
        <div class="bg-white dark:bg-slate-900 shadow-sm rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
          
          <!-- Desktop Table -->
          <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
              <thead class="bg-slate-50 dark:bg-slate-950">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Domain</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">API Key</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">License</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expires</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php
                if ($result->num_rows > 0) {
                  $result->data_seek(0);
                  while ($row = $result->fetch_assoc()) {
                    $is_inactive = ($row['active'] == 0);
                    $opacity_class = $is_inactive ? 'opacity-60' : '';
                    ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors <?php echo $opacity_class; ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                                    <i class="fas fa-globe text-xs"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center space-x-2 group cursor-pointer" onclick="copyToClipboard('<?php echo htmlspecialchars($row["api_key"] ?? ''); ?>', this)">
                                <code class="text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-1 rounded border border-slate-200 dark:border-slate-700 max-w-[120px] truncate">
                                    <?php echo htmlspecialchars($row["api_key"] ?? 'Not Generated'); ?>
                                </code>
                                <i class="fas fa-copy text-slate-400 group-hover:text-indigo-500 transition-colors text-xs"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['license_type'] === 'lifetime' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'; ?> capitalize">
                                <?php echo htmlspecialchars($row["license_type"] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            <?php echo ($row["license_type"] === 'lifetime' ? '<span class="text-emerald-600 dark:text-emerald-400 font-medium">Never</span>' : ($row["expiry_date"] ? date('M d, Y', strtotime($row["expiry_date"])) : 'N/A')); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-3">
                                <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"><i class="fas fa-eye"></i></button>
                                <button onclick="confirmAction('regenerate', <?php echo $row['id']; ?>)" class="text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition-colors"><i class="fas fa-sync-alt"></i></button>
                                <a href="edit-domain?id=<?php echo $row['id']; ?>" class="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"><i class="fas fa-pen"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php
                  }
                } else {
                  echo '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No domains found matching your criteria.</td></tr>';
                }
                ?>
              </tbody>
            </table>
          </div>

          <!-- Mobile Cards -->
          <div class="md:hidden p-4 space-y-4">
            <?php
            if ($result->num_rows > 0) {
              $result->data_seek(0);
              while ($row = $result->fetch_assoc()) {
                ?>
                <div class="bg-slate-50 dark:bg-slate-950 rounded-lg p-4 border border-slate-200 dark:border-slate-800">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></h3>
                        </div>
                        <span class="text-[10px] uppercase font-bold px-2 py-1 rounded bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($row["license_type"]); ?></span>
                    </div>
                    
                    <div class="space-y-2 mb-4">
                        <div class="bg-white dark:bg-slate-900 rounded-lg p-3 border border-slate-200 dark:border-slate-800 mb-2">
                            <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Key:</div>
                            <div class="flex items-center justify-between cursor-pointer group" onclick="copyToClipboard('<?php echo htmlspecialchars($row["api_key"] ?? ''); ?>', this)">
                                <code class="font-mono text-sm text-indigo-600 dark:text-indigo-400 truncate mr-2"><?php echo htmlspecialchars($row["api_key"] ?? ''); ?></code>
                                <i class="fas fa-copy text-slate-400 group-hover:text-indigo-500 transition-colors"></i>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Expires:</span>
                            <span class="text-slate-700 dark:text-slate-300"><?php echo ($row["license_type"] === 'lifetime' ? 'Never' : ($row["expiry_date"] ? date('M d, Y', strtotime($row["expiry_date"])) : 'N/A')); ?></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="py-2 text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded hover:bg-slate-50 dark:hover:bg-slate-800">View</button>
                        <button onclick="confirmAction('regenerate', <?php echo $row['id']; ?>)" class="py-2 text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-green-600 dark:text-green-400 rounded hover:bg-slate-50 dark:hover:bg-slate-800">Key</button>
                        <a href="edit-domain?id=<?php echo $row['id']; ?>" class="block text-center py-2 text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-blue-600 dark:text-blue-400 rounded hover:bg-slate-50 dark:hover:bg-slate-800">Edit</a>
                    </div>
                </div>
                <?php
              }
            } else {
                echo '<div class="text-center text-slate-500 py-4">No domains found.</div>';
            }
            ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <?php
    // Modals
    if ($result->num_rows > 0) {
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            include 'includes/view-modal.php';
        }
    }
  ?>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity" onclick="hideConfirmModal()"></div>
    <div class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-sm w-full p-6 border border-slate-200 dark:border-slate-800 transform shadow-xl transition-all">
        <div class="text-center">
            <div id="confirmIconContainer" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 mb-4">
                <i id="confirmIcon" class="text-2xl text-indigo-600 dark:text-indigo-400"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="confirmTitle">Confirm Action</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2" id="confirmDescription">Are you sure?</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="hideConfirmModal()" class="flex-1 py-2.5 px-4 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Cancel</button>
            <button id="confirmBtn" onclick="executeConfirmAction()" class="flex-1 py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-500/20 transition">Confirm</button>
        </div>
    </div>
  </div>

  <script>
    const csrfToken = "<?php echo CSRFProtection::getToken(); ?>";
    
    // Copy Clipboard Logic
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-emerald-500';
            setTimeout(() => { icon.className = originalClass; }, 2000);
        });
    }

    // Modal Logic
    function showViewModal(id) {
        const modal = document.getElementById('viewModal' + id);
        modal.classList.remove('hidden');
        setTimeout(() => modal.querySelector('.modal-container').classList.remove('opacity-0', 'scale-95'), 10);
    }
    
    function hideViewModal(id) {
        const modal = document.getElementById('viewModal' + id);
        modal.querySelector('.modal-container').classList.add('opacity-0', 'scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    // Confirm Modal Logic
    let currentActionUrl = '';
    
    function confirmAction(type, id) {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmTitle');
        const desc = document.getElementById('confirmDescription');
        const icon = document.getElementById('confirmIcon');
        
        if (type === 'regenerate') {
            title.innerText = 'Regenerate API Key';
            desc.innerText = 'This will invalidate the current key immediately.';
            icon.className = 'fas fa-sync-alt text-indigo-600 dark:text-indigo-400';
            currentActionUrl = 'domains.php?regenerate_key=' + id + '&csrf_token=' + csrfToken;
        }

        modal.classList.remove('hidden');
    }

    function hideConfirmModal() {
        document.getElementById('confirmModal').classList.add('hidden');
    }

    function executeConfirmAction() {
        if (currentActionUrl) window.location.href = currentActionUrl;
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>