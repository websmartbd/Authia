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
    // Redirect to login page if user is not authenticated
    header("Location: login");
    exit;
}

// Set page title and icon for mobile header
$page_title = 'Expired Domains';
$page_icon = '<i class="fas fa-calendar-times text-red-600 text-2xl"></i>';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Handle search with prepared statements
$search = InputValidator::sanitizeString($_GET['search'] ?? '');

// Base SQL for expired domains
$sql = "SELECT d.id, d.name, d.email, d.domain, d.active, d.message, d.`delete`, d.license_type, d.expiry_date, a.api_key 
        FROM domains d 
        LEFT JOIN licenses a ON d.id = a.domain_id 
        WHERE d.license_type != 'lifetime' 
        AND d.expiry_date < CURDATE()
        AND d.`delete` != 'yes'";

// Add search condition if search term is provided
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

// Handle flag for delete action with CSRF protection
if (isset($_GET['flag_delete'])) {
    if (!isset($_GET['csrf_token']) || !CSRFProtection::validateToken($_GET['csrf_token'])) {
        die('Security validation failed');
    }
    
    $flag_id = InputValidator::validateInt($_GET['flag_delete'], 1);
    if ($flag_id !== false) {
        DatabaseSecurity::execute($conn, "UPDATE domains SET `delete` = 'yes' WHERE id = ?", [$flag_id], "i");
        $_SESSION['success_message'] = "Domain flagged for deletion.";
        header("Location: expires.php");
        exit;
    }
}

// Handle reminder action with CSRF protection
if (isset($_GET['send_reminder'])) {
    if (!isset($_GET['csrf_token']) || !CSRFProtection::validateToken($_GET['csrf_token'])) {
        die('Security validation failed');
    }
    
    $remind_id = InputValidator::validateInt($_GET['send_reminder'], 1);
    if ($remind_id !== false) {
        $stmt = $conn->prepare("SELECT domain, name, email, expiry_date FROM domains WHERE id = ?");
        $stmt->bind_param("i", $remind_id);
        $stmt->execute();
        $rem_res = $stmt->get_result();
        
        if ($row = $rem_res->fetch_assoc()) {
            if (!empty($row['email'])) {
                $email_content = generate_reminder_email($row['domain'], $row['name'], $row['expiry_date']);
                $subject = "Action Required: License Expired for " . $row['domain'];
                
                if (send_html_email($row['email'], $subject, $email_content)) {
                    $_SESSION['success_message'] = "Reminder email successfully sent to " . htmlspecialchars($row['email']);
                } else {
                    $_SESSION['error_message'] = "Failed to send email. Please check your SMTP settings.";
                }
            } else {
                $_SESSION['error_message'] = "No email address found for this domain.";
            }
        }
        $stmt->close();
    }
    
    header("Location: expires.php");
    exit;
}

// Handle renewal action with CSRF protection
if (isset($_GET['renew'])) {
    if (!isset($_GET['csrf_token']) || !CSRFProtection::validateToken($_GET['csrf_token'])) {
        die('Security validation failed');
    }
    
    $renew_id = InputValidator::validateInt($_GET['renew'], 1);
    
    if ($renew_id !== false) {
        // Fetch current license type using prepared statement
        $stmt = $conn->prepare("SELECT license_type FROM domains WHERE id = ?");
        $stmt->bind_param("i", $renew_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $type = $row['license_type'];
            $new_expiry = '';
            
            if ($type === 'monthly') {
                $new_expiry = date('Y-m-d', strtotime('+30 days'));
            } elseif ($type === 'yearly') {
                $new_expiry = date('Y-m-d', strtotime('+1 year'));
            }
            
            if (!empty($new_expiry)) {
                DatabaseSecurity::execute($conn, "UPDATE domains SET expiry_date = ?, active = 1 WHERE id = ?", [$new_expiry, $renew_id], "si");
                $_SESSION['success_message'] = "License successfully renewed until $new_expiry.";
            } else {
                $_SESSION['error_message'] = "Cannot renew this license type automatically.";
            }
        }
        $stmt->close();
    }
    
    header("Location: expires.php");
    exit;
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expired Domains - Authenticator</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f3f4f6;
    }
    
    /* Sidebar styles */
    .nav-item.active .sub-menu-link.active {
      font-weight: 600;
      color: #ffffff;
    }
    
    .nav-item .sub-menu-link {
      padding-left: 2.5rem;
    }
    
    .clickable-menu-item {
      cursor: pointer;
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #c5c5c5;
      border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #a3a3a3;
    }

    /* Ensure consistent table cell padding */
    .domain-table td {
      padding-top: 1rem;
      padding-bottom: 1rem;
    }
  </style>
</head>
<body class="bg-gray-50">

  <div class="flex h-screen bg-gray-50 overflow-hidden">
    <?php include 'includes/sidebar-modal.php'; ?>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Main content body -->
      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-4 sm:p-6 md:p-8">
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm animate-fadeIn" role="alert">
            <div class="flex items-center">
              <i class="fas fa-check-circle mr-3"></i>
              <p class="font-medium"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
            </div>
          </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm animate-fadeIn" role="alert">
            <div class="flex items-center">
              <i class="fas fa-exclamation-circle mr-3"></i>
              <p class="font-medium"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
            </div>
          </div>
        <?php endif; ?>
        <!-- Page header -->
        <div class="mb-8 hidden md:block">
          <div class="flex items-center space-x-3 mb-2">
            <div class="bg-red-100 p-2 rounded-lg">
              <i class="fas fa-calendar-times text-red-600 text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Expired Domains</h1>
          </div>
          <p class="text-sm text-gray-600">Review all domains whose licenses have expired.</p>
        </div>
        
        <!-- Search Bar -->
        <div class="mb-8">
          <form method="GET" class="flex items-center">
            <div class="flex-1">
              <div class="relative rounded-l-lg shadow-sm">
                <input type="text" 
                       name="search" 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       class="focus:ring-red-500 focus:border-red-500 block w-full pl-4 pr-4 py-3 text-base border border-gray-300 rounded-l-lg rounded-r-none" 
                       placeholder="Search expired domains...">
              </div>
            </div>
            <div class="flex">
              <button type="submit" 
                      class="inline-flex items-center px-4 py-3 border border-transparent text-sm font-medium rounded-r-lg shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </form>
        </div>

        <!-- Domain List -->
        <div class="bg-white shadow-md rounded-xl p-6 border border-gray-100">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-gray-900">Expired Licenses (<?php echo $result->num_rows; ?>)</h2>
          </div>
          
          <!-- Desktop Table View -->
          <div class="hidden md:block">
            <div class="flex flex-col">
              <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                  <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 domain-table">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expired On</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                          <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        if ($result->num_rows > 0) {
                          $result->data_seek(0);
                          while ($row = $result->fetch_assoc()) {
                            echo "<tr class='hover:bg-red-50 transition-colors duration-200'>";
                            // Domain
                            echo   "<td class='px-6 py-4 text-sm font-medium text-gray-900 break-all'>" . htmlspecialchars($row["domain"]) . "</td>";
                            // License Type
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'><span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 capitalize'>" . htmlspecialchars($row["license_type"] ?? 'N/A') . "</span></td>";
                            // Expiry Date
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold'>" . htmlspecialchars($row["expiry_date"] ?? 'N/A') . "</td>";
                            // Details
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>";
                            echo     "<button onclick='showViewModal(\"" . $row['id'] . "\")' class='text-indigo-600 hover:text-indigo-900 text-sm font-medium'>View</button>";
                            echo   "</td>";
                            // Actions
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-right text-sm font-medium'>";
                            echo     "<div class='flex items-center justify-end space-x-2'>";
                            echo       "<button onclick='confirmAction(\"renew\", " . $row['id'] . ")' class='inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none transition-colors' title='Renew License'>";
                            echo         "<i class='fas fa-sync-alt mr-1.5'></i> Renew";
                            echo       "</button>";
                            echo       "<button onclick='confirmAction(\"remind\", " . $row['id'] . ")' class='inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-amber-700 bg-amber-50 hover:bg-amber-100 focus:outline-none transition-colors' title='Send Expiry Reminder'>";
                            echo         "<i class='fas fa-bell mr-1.5'></i> Remind";
                            echo       "</button>";
                            echo       "<button onclick='confirmAction(\"flag_delete\", " . $row['id'] . ")' class='inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none transition-colors' title='Flag for Deletion'>";
                            echo         "<i class='fas fa-trash-alt mr-1.5'></i> Flag";
                            echo       "</button>";
                            echo     "</div>";
                            echo   "</td>";
                            echo "</tr>";
                          }
                        } else {
                          echo "<tr><td colspan='5' class='px-6 py-4 whitespace-nowrap text-center text-gray-500'>No expired domains found</td></tr>";
                        }
                        ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Mobile Card View -->
          <div class="md:hidden space-y-4">
            <?php
            if ($result->num_rows > 0) {
              $result->data_seek(0);
              while ($row = $result->fetch_assoc()) {
            ?>
              <div class="bg-white rounded-lg shadow-sm border border-red-200 p-4">
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900 break-all"><?php echo htmlspecialchars($row["domain"]); ?></h3>
                    <p class="text-sm text-gray-500">Expired: <span class="text-red-600 font-medium"><?php echo htmlspecialchars($row["expiry_date"] ?? 'N/A'); ?></span></p>
                  </div>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 capitalize">
                    <?php echo htmlspecialchars($row["license_type"] ?? 'N/A'); ?>
                  </span>
                </div>
                
                <div class="grid grid-cols-4 gap-2 mt-4 pt-3 border-t border-gray-100">
                  <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="flex items-center justify-center px-2 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50" title="View Details">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button onclick="confirmAction('renew', <?php echo $row['id']; ?>)" class="flex items-center justify-center px-2 py-2 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-green-600 hover:bg-green-700" title="Renew License">
                    <i class="fas fa-sync-alt"></i>
                  </button>
                   <button onclick="confirmAction('remind', <?php echo $row['id']; ?>)" class="flex items-center justify-center px-2 py-2 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-amber-500 hover:bg-amber-600" title="Send Reminder">
                    <i class="fas fa-bell"></i>
                  </button>
                  <button onclick="confirmAction('flag_delete', <?php echo $row['id']; ?>)" class="flex items-center justify-center px-2 py-2 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-red-600 hover:bg-red-700" title="Flag for Deletion">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </div>
              </div>
            <?php
              }
            } else {
              echo "<div class='text-center py-8 text-gray-500'>No expired domains found.</div>";
            }
            ?>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
    function showViewModal(id) {
      const modal = document.getElementById('viewModal' + id);
      const container = modal.querySelector('.modal-container');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      void modal.offsetWidth;
      modal.classList.add('bg-opacity-75');
      container.classList.add('sm:scale-100', 'opacity-100');
      container.classList.remove('opacity-0', 'sm:scale-95');
    }

    function hideViewModal(id) {
      const modal = document.getElementById('viewModal' + id);
      const container = modal.querySelector('.modal-container');
      modal.classList.remove('bg-opacity-75');
      container.classList.add('opacity-0', 'sm:scale-95');
      container.classList.remove('sm:scale-100');
      setTimeout(() => {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
      }, 300);
    }

    const csrfToken = "<?php echo CSRFProtection::getToken(); ?>";
    let currentActionUrl = '';

    function confirmAction(type, id) {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmTitle');
        const desc = document.getElementById('confirmDescription');
        const confirmBtn = document.getElementById('confirmBtn');
        const iconContainer = document.getElementById('confirmIconContainer');
        const icon = document.getElementById('confirmIcon');

        if (type === 'flag_delete') {
            title.innerText = 'Flag for Deletion?';
            desc.innerText = 'Are you sure you want to mark this domain for deletion? It will be moved to the pending delete list.';
            confirmBtn.innerText = 'Flag Now';
            confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-orange-600 text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200';
            iconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 sm:mx-0 sm:h-10 sm:w-10';
            icon.className = 'fas fa-flag text-orange-600';
            currentActionUrl = 'expires.php?flag_delete=' + id + '&csrf_token=' + csrfToken;
        } else if (type === 'remind') {
            title.innerText = 'Send Reminder?';
            desc.innerText = 'This will send an expiry notification email to the client linked with this domain.';
            confirmBtn.innerText = 'Send Reminder';
            confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-500 text-base font-medium text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200';
            iconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10';
            icon.className = 'fas fa-bell text-amber-600';
            currentActionUrl = 'expires.php?send_reminder=' + id + '&csrf_token=' + csrfToken;
        } else if (type === 'renew') {
            title.innerText = 'Renew License?';
            desc.innerText = 'Are you sure you want to renew this license? This will automatically extend the expiry date based on the current license type (Monthly/Yearly) and reactivate the domain.';
            confirmBtn.innerText = 'Renew Now';
            confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200';
            iconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10';
            icon.className = 'fas fa-sync-alt text-green-600';
            currentActionUrl = 'expires.php?renew=' + id + '&csrf_token=' + csrfToken;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            modal.querySelector('.bg-gray-500').classList.add('opacity-75');
            const container = modal.querySelector('.transform');
            container.classList.remove('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
            container.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
        }, 10);
    }

    function hideConfirmModal() {
        const modal = document.getElementById('confirmModal');
        const container = modal.querySelector('.transform');
        modal.querySelector('.bg-gray-500').classList.remove('opacity-75');
        container.classList.add('opacity-0', 'translate-y-4', 'sm:translate-y-0', 'sm:scale-95');
        container.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }

    function executeConfirmAction() {
        if (currentActionUrl) {
            window.location.href = currentActionUrl;
        }
    }

    function copyToClipboard(text, btn) {
        if (!text || text === 'Not Generated') return;
        navigator.clipboard.writeText(text).then(() => {
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
            setTimeout(() => { btn.innerHTML = originalIcon; }, 2000);
        });
    }

  </script>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 w-full">
      <div class="fixed inset-0 bg-gray-500 bg-opacity-0 transition-opacity duration-300 ease-out" aria-hidden="true" onclick="hideConfirmModal()"></div>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300 ease-out">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div id="confirmIconContainer" class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10">
              <i id="confirmIcon" class="text-xl"></i>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
              <h3 class="text-xl leading-6 font-bold text-gray-900" id="confirmTitle">Confirm Action</h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500" id="confirmDescription">Are you sure you want to proceed?</p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-4 sm:px-6 sm:flex sm:flex-row-reverse">
          <button type="button" id="confirmBtn" onclick="executeConfirmAction()" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200">
            Confirm
          </button>
          <button type="button" onclick="hideConfirmModal()" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Modals -->
  <?php
  if ($result->num_rows > 0) {
      $result->data_seek(0);
      while ($row = $result->fetch_assoc()) {
          include 'includes/view-modal.php';
      }
  }
  ?>
</body>
</html>
