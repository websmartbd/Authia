<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';
require_once 'config/smtp.php';

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

// Base SQL for expired domains
$sql = "SELECT d.id, d.name, d.email, d.domain, d.active, d.message, d.`delete`, d.license_type, d.expiry_date, a.api_key 
        FROM domains d 
        LEFT JOIN licenses a ON d.id = a.domain_id 
        WHERE d.license_type != 'lifetime' 
        AND d.expiry_date < CURDATE()
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

// Handle Actions (Flag, Remind, Renew)
if (isset($_GET['flag_delete'])) {
    if (!CSRFProtection::validateToken($_GET['csrf_token'] ?? '')) die('Security validation failed');
    $flag_id = InputValidator::validateInt($_GET['flag_delete'], 1);
    if ($flag_id) {
        DatabaseSecurity::execute($conn, "UPDATE domains SET `delete` = 'yes' WHERE id = ?", [$flag_id], "i");
        $_SESSION['success_message'] = "Domain flagged for deletion.";
        header("Location: expires.php");
        exit;
    }
}

if (isset($_GET['send_reminder'])) {
    if (!CSRFProtection::validateToken($_GET['csrf_token'] ?? '')) die('Security validation failed');
    $remind_id = InputValidator::validateInt($_GET['send_reminder'], 1);
    if ($remind_id) {
        $stmt = $conn->prepare("SELECT domain, name, email, expiry_date FROM domains WHERE id = ?");
        $stmt->bind_param("i", $remind_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['email'])) {
                // Mock email sending if function doesn't exist, or use existing
                $email_content = "Hello " . htmlspecialchars($row['name']) . ",<br>Your license for " . htmlspecialchars($row['domain']) . " expired on " . htmlspecialchars($row['expiry_date']) . ". Please renew.";
                if (function_exists('send_html_email') && send_html_email($row['email'], "License Expired: " . $row['domain'], $email_content)) {
                     $_SESSION['success_message'] = "Reminder sent to " . htmlspecialchars($row['email']);
                } else {
                     $_SESSION['error_message'] = "Failed to send email (SMTP error).";
                }
            } else {
                $_SESSION['error_message'] = "No email found for domain.";
            }
        }
        header("Location: expires.php");
        exit;
    }
}

if (isset($_GET['renew'])) {
    if (!CSRFProtection::validateToken($_GET['csrf_token'] ?? '')) die('Security validation failed');
    $renew_id = InputValidator::validateInt($_GET['renew'], 1);
    if ($renew_id) {
        $stmt = $conn->prepare("SELECT license_type FROM domains WHERE id = ?");
        $stmt->bind_param("i", $renew_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $new_expiry = ($row['license_type'] === 'monthly') ? date('Y-m-d', strtotime('+30 days')) : (($row['license_type'] === 'yearly') ? date('Y-m-d', strtotime('+1 year')) : '');
            if ($new_expiry) {
                DatabaseSecurity::execute($conn, "UPDATE domains SET expiry_date = ?, active = 1 WHERE id = ?", [$new_expiry, $renew_id], "si");
                $_SESSION['success_message'] = "Renewed until $new_expiry.";
            }
        }
        header("Location: expires.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expired Domains - Authia</title>
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
        
        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800 p-4 rounded-xl flex items-center animate-fadeIn">
                <i class="fas fa-check-circle text-emerald-500 mr-3"></i>
                <p class="text-sm text-emerald-700 dark:text-emerald-400 font-bold"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 p-4 rounded-xl flex items-center animate-fadeIn">
                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                <p class="text-sm text-red-700 dark:text-red-400 font-bold"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="flex mb-8 flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="hidden md:block">
             <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Expired Domains</h1>
             <p class="text-sm text-slate-500 dark:text-slate-400">Manage expired licenses and renewals.</p>
          </div>
          <form method="GET" class="relative w-full md:max-w-xs">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block pl-10 p-2.5 outline-none shadow-sm transition-all" 
                   placeholder="Search expired...">
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
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">License</th>
                  <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expired On</th>
                  <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                <?php if ($result->num_rows > 0): $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($row["email"] ?? ''); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 uppercase tracking-wider">
                            <?php echo htmlspecialchars($row["license_type"] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-500 dark:text-red-400">
                        <?php echo htmlspecialchars($row["expiry_date"] ?? 'N/A'); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-3">
                            <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="View"><i class="fas fa-eye"></i></button>
                            <button onclick="confirmAction('renew', <?php echo $row['id']; ?>)" class="text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition" title="Renew"><i class="fas fa-sync-alt"></i></button>
                            <button onclick="confirmAction('remind', <?php echo $row['id']; ?>)" class="text-slate-400 hover:text-amber-500 dark:hover:text-amber-400 transition" title="Remind"><i class="fas fa-bell"></i></button>
                            <button onclick="confirmAction('flag_delete', <?php echo $row['id']; ?>)" class="text-slate-400 hover:text-red-500 dark:hover:text-red-400 transition" title="Flag"><i class="fas fa-flag"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No expired domains found.</td></tr>
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
                 <div class="absolute top-0 right-0 p-2 opacity-10"><i class="fas fa-history text-6xl"></i></div>
                 <div class="relative z-10">
                    <div class="flex justify-between items-center mb-2">
                        <h3 class="font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row["domain"]); ?></h3>
                        <span class="text-xs font-bold text-red-500"><?php echo htmlspecialchars($row["expiry_date"]); ?></span>
                    </div>
                    <div class="grid grid-cols-4 gap-2 mt-4">
                        <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800"><i class="fas fa-eye"></i></button>
                        <button onclick="confirmAction('renew', <?php echo $row['id']; ?>)" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20"><i class="fas fa-sync-alt"></i></button>
                        <button onclick="confirmAction('remind', <?php echo $row['id']; ?>)" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-amber-500 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20"><i class="fas fa-bell"></i></button>
                        <button onclick="confirmAction('flag_delete', <?php echo $row['id']; ?>)" class="py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i class="fas fa-flag"></i></button>
                    </div>
                 </div>
             </div>
             <?php } } else { echo '<div class="text-center text-slate-500 py-4">No expired domains.</div>'; } ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <?php if ($result->num_rows > 0) { $result->data_seek(0); while ($row = $result->fetch_assoc()) { include 'includes/view-modal.php'; } } ?>

  <!-- Confirmation Modal (Simplified for brevity, reusing styles) -->
  <div id="confirmModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-sm transition-opacity" onclick="hideConfirmModal()"></div>
    <div class="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-sm w-full p-6 border border-slate-200 dark:border-slate-800 transform transition-all">
        <div class="text-center">
            <div id="confirmIconContainer" class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 mb-4">
                <i id="confirmIcon" class="text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="confirmTitle">Confirm</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2" id="confirmDescription">Proceed?</p>
        </div>
        <div class="mt-6 flex gap-3">
            <button onclick="hideConfirmModal()" class="flex-1 py-2.5 px-4 border border-slate-200 dark:border-slate-700 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">Cancel</button>
            <button id="confirmBtn" onclick="executeConfirmAction()" class="flex-1 py-2.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-500/20 transition">Confirm</button>
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
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmTitle');
        const desc = document.getElementById('confirmDescription');
        const icon = document.getElementById('confirmIcon');
        const iconContainer = document.getElementById('confirmIconContainer');
        
        if (type === 'flag_delete') {
            title.innerText = 'Flag for Deletion?';
            desc.innerText = 'Mark this domain for deletion queue.';
            icon.className = 'fas fa-flag text-red-500';
            iconContainer.className = 'mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 mb-4';
            currentActionUrl = 'expires.php?flag_delete=' + id + '&csrf_token=' + csrfToken;
        } else if (type === 'remind') {
            title.innerText = 'Send Reminder?';
            desc.innerText = 'Email the client about expiry.';
            icon.className = 'fas fa-bell text-amber-500';
            iconContainer.className = 'mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30 mb-4';
            currentActionUrl = 'expires.php?send_reminder=' + id + '&csrf_token=' + csrfToken;
        } else if (type === 'renew') {
            title.innerText = 'Renew License?';
            desc.innerText = 'Extend license validity.';
            icon.className = 'fas fa-sync-alt text-green-500';
            iconContainer.className = 'mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 mb-4';
            currentActionUrl = 'expires.php?renew=' + id + '&csrf_token=' + csrfToken;
        }
        modal.classList.remove('hidden');
    }

    function hideConfirmModal() { document.getElementById('confirmModal').classList.add('hidden'); }
    function executeConfirmAction() { if (currentActionUrl) window.location.href = currentActionUrl; }
  </script>
</body>
</html>
<?php $conn->close(); ?>
