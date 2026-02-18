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
    die("Connection failed: " . $conn->connect_error);
}

// Handle search
$search = InputValidator::sanitizeString($_GET['search'] ?? '');

// Base SQL for domains marked for deletion
$sql = "SELECT d.id, d.name, d.email, d.domain, d.active, d.message, d.`delete`, d.license_type, d.expiry_date, a.api_key 
        FROM domains d 
        LEFT JOIN licenses a ON d.id = a.domain_id 
        WHERE d.`delete` = 'yes'";

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

// Handle delete/restore
if (isset($_GET['permanent_delete'])) {
    if (!CSRFProtection::validateToken($_GET['csrf_token'] ?? '')) die('Security validation failed');
    $delete_id = InputValidator::validateInt($_GET['permanent_delete'], 1);
    if ($delete_id) {
        DatabaseSecurity::execute($conn, "DELETE FROM licenses WHERE domain_id = ?", [$delete_id], "i");
        DatabaseSecurity::execute($conn, "DELETE FROM domains WHERE id = ?", [$delete_id], "i");
        header("Location: delete.php");
        exit;
    }
}

if (isset($_GET['restore'])) {
    if (!CSRFProtection::validateToken($_GET['csrf_token'] ?? '')) die('Security validation failed');
    $restore_id = InputValidator::validateInt($_GET['restore'], 1);
    if ($restore_id) {
        DatabaseSecurity::execute($conn, "UPDATE domains SET `delete` = 'no' WHERE id = ?", [$restore_id], "i");
        header("Location: delete.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deletion Queue - Authia</title>
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

    <div class="flex-1 flex flex-col overflow-hidden">
      <main class="flex-1 overflow-x-hidden overflow-y-auto p-4 pt-20 md:p-8">
        
        <!-- Header -->
        <div class="flex mb-8 flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="hidden md:block">
             <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Deletion Queue</h1>
             <p class="text-sm text-slate-500 dark:text-slate-400">Restore or permanently remove domains.</p>
          </div>
          <form method="GET" class="relative w-full md:max-w-xs">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-orange-500 focus:border-orange-500 block pl-10 p-2.5 outline-none shadow-sm transition-all" 
                   placeholder="Search queue...">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-search text-slate-400"></i>
            </div>
          </form>
        </div>

        <div class="bg-white dark:bg-slate-900 shadow-sm rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
          <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
              <thead class="bg-slate-50 dark:bg-slate-950">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Domain</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">API Key</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">License</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expires</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php if ($result->num_rows > 0): $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-orange-50 dark:hover:bg-orange-900/10 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                         <div class="flex items-center space-x-2 group cursor-pointer" onclick="copyToClipboard('<?php echo htmlspecialchars($row["api_key"] ?? ''); ?>', this)">
                            <code class="text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2 py-1 rounded border border-slate-200 dark:border-slate-700 max-w-[120px] truncate">
                                <?php echo htmlspecialchars($row["api_key"] ?? 'Not Generated'); ?>
                            </code>
                            <i class="fas fa-copy text-slate-400 group-hover:text-orange-500 transition-colors text-xs"></i>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 uppercase tracking-wider">
                            <?php echo htmlspecialchars($row["license_type"] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                        <?php echo ($row["license_type"] === 'lifetime' ? '<span class="text-emerald-600 dark:text-emerald-400">Never</span>' : htmlspecialchars($row["expiry_date"] ?? 'N/A')); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-3">
                            <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="View"><i class="fas fa-eye"></i></button>
                            <a href="delete.php?restore=<?php echo $row['id'] . "&csrf_token=" . CSRFProtection::getToken(); ?>" class="text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition" title="Restore"><i class="fas fa-undo"></i></a>
                            <button onclick="confirmAction('permanent_delete', <?php echo $row['id']; ?>)" class="text-slate-400 hover:text-red-600 dark:hover:text-red-400 transition" title="Delete Forever"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No domains pending deletion.</td></tr>
                <?php endif; ?>
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
             <div class="bg-slate-50 dark:bg-slate-950 rounded-lg p-4 border border-slate-200 dark:border-slate-800 relative overflow-hidden">
                 <div class="flex justify-between items-start mb-2">
                     <h3 class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></h3>
                     <span class="text-xs font-bold text-orange-500">Deleted</span>
                 </div>
                 <div class="grid grid-cols-3 gap-2 mt-4">
                     <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800"><i class="fas fa-eye"></i> View</button>
                     <a href="delete.php?restore=<?php echo $row['id'] . "&csrf_token=" . CSRFProtection::getToken(); ?>" class="block text-center py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20"><i class="fas fa-undo"></i> Restore</a>
                     <button onclick="confirmAction('permanent_delete', <?php echo $row['id']; ?>)" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i class="fas fa-trash"></i> Delete</button>
                 </div>
             </div>
             <?php } } else { echo '<div class="text-center text-slate-500 py-4">No domains pending deletion.</div>'; } ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <?php if ($result->num_rows > 0) { $result->data_seek(0); while ($row = $result->fetch_assoc()) { include 'includes/view-modal.php'; } } ?>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity" onclick="hideConfirmModal()"></div>
    <div class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-sm w-full p-6 border border-slate-200 dark:border-slate-800 transform transition-all">
        <div class="text-center">
            <div id="confirmIconContainer" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                <i id="confirmIcon" class="text-2xl text-red-600 dark:text-red-400 fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="confirmTitle">Permanent Delete</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2" id="confirmDescription">This cannot be undone.</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="hideConfirmModal()" class="flex-1 py-2.5 px-4 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Cancel</button>
            <button id="confirmBtn" onclick="executeConfirmAction()" class="flex-1 py-2.5 px-4 bg-red-600 hover:bg-red-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-red-500/20 transition">Delete</button>
        </div>
    </div>
  </div>

  <script>
    const csrfToken = "<?php echo CSRFProtection::getToken(); ?>";
    let currentActionUrl = '';
    
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

    function confirmAction(type, id) {
        if (type === 'permanent_delete') {
            currentActionUrl = 'delete.php?permanent_delete=' + id + '&csrf_token=' + csrfToken;
        }
        document.getElementById('confirmModal').classList.remove('hidden');
    }

    function hideConfirmModal() { document.getElementById('confirmModal').classList.add('hidden'); }
    function executeConfirmAction() { if (currentActionUrl) window.location.href = currentActionUrl; }
    
    function copyToClipboard(text, btn) {
        if (!text || text === 'Not Generated') return;
        navigator.clipboard.writeText(text).then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-green-500';
            setTimeout(() => { icon.className = originalClass; }, 2000);
        });
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>
