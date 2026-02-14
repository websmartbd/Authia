<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Redirect to login page if user is not authenticated
    header("Location: login");
    exit;
}

// Set page title and icon for mobile header
$page_title = 'All Domains';
$page_icon = '<i class="fas fa-globe text-indigo-600 text-2xl"></i>';

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

// Add search condition if search term is provided using prepared statement
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
    // Validate CSRF token
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

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domains - Authenticator</title>
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
    
    /* Card hover effects (might still be used by mobile view) */
    .domain-card {
      transition: all 0.2s ease-in-out;
    }
    
    .domain-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        <!-- Page header -->
        <div class="mb-8 hidden md:block">
          <h1 class="text-2xl font-bold text-gray-900 mb-2">All Domains</h1>
          <p class="text-sm text-gray-600">View and manage all your registered domains</p>
        </div>
        
        <!-- Search Bar -->
        <div class="mb-8">
          <form method="GET" class="flex items-center">
            <div class="flex-1">
              <div class="relative rounded-l-lg shadow-sm">
                <input type="text" 
                       name="search" 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                       class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-4 pr-4 py-3 text-base border border-gray-300 rounded-l-lg rounded-r-none" 
                       placeholder="Search domains, emails, or names...">
              </div>
            </div>
            <div class="flex">
              <button type="submit" 
                      class="inline-flex items-center px-4 py-3 border border-transparent text-sm font-medium rounded-r-lg shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
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
            <h2 class="text-lg font-semibold text-gray-900">Registered Domains</h2>
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
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">API Key</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License</th>
                          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
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
                            $is_inactive = ($row['active'] == 0);
                            $row_class = $is_inactive ? 'bg-gray-50' : 'bg-white';
                            
                            echo "<tr class='{$row_class} transition-colors duration-200 hover:bg-gray-50'>";
                            // Domain
                            echo   "<td class='px-6 py-4 text-sm font-medium text-gray-900'>" . htmlspecialchars($row["domain"]) . "</td>";
                            // API Key
                            echo   "<td class='px-6 py-4 text-sm text-gray-500'>";
                            echo     "<div class='flex items-center space-x-2'>";
                            echo       "<code class='bg-gray-100 px-2 py-1 rounded text-xs truncate max-w-[150px]'>" . htmlspecialchars($row["api_key"] ?? 'Not Generated') . "</code>";
                            echo       "<button onclick='copyToClipboard(\"" . htmlspecialchars($row["api_key"] ?? '') . "\", this)' class='text-indigo-600 hover:text-indigo-900 focus:outline-none flex-shrink-0 transition-colors' title='Copy API Key'>";
                            echo         "<i class='fas fa-copy'></i>";
                            echo       "</button>";
                            echo     "</div>";
                            echo   "</td>";
                            // License Type
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'><span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium " . ($row['license_type'] === 'lifetime' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800') . " capitalize'>" . htmlspecialchars($row["license_type"] ?? 'N/A') . "</span></td>";
                            // Expiry Date
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-500'>" . ($row["license_type"] === 'lifetime' ? 'Never' : ($row["expiry_date"] ? date('M d, Y', strtotime($row["expiry_date"])) : 'N/A')) . "</td>";
                            // Details
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-900'>";
                            echo     "<button onclick='showViewModal(\"" . $row['id'] . "\")' class='text-indigo-600 hover:text-indigo-900 text-sm font-medium transition-colors'>View</button>";
                            echo   "</td>";
                            // Actions
                            echo   "<td class='px-6 py-4 whitespace-nowrap text-right text-sm font-medium'>";
                            echo     "<div class='flex items-center justify-end space-x-3'>";
                            echo       "<button onclick='confirmAction(\"regenerate\", " . $row['id'] . ")' class='text-green-600 hover:text-green-900 focus:outline-none transition-colors' title='Generate New Key'><i class='fas fa-sync-alt'></i></button>";
                            echo       "<a href='edit-domain?id=" . $row['id'] . "' class='text-indigo-600 hover:text-indigo-900 transition-colors' title='Edit'><i class='fas fa-edit'></i></a>";
                            echo     "</div>";
                            echo   "</td>";
                            echo "</tr>";
                          }
                        } else {
                          echo "<tr><td colspan='6' class='px-6 py-4 whitespace-nowrap text-center text-gray-500'>No domains found</td></tr>";
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
                $is_inactive = ($row['active'] == 0);
            ?>
              <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 <?php echo $is_inactive ? 'opacity-75' : ''; ?>">
                <div class="flex justify-between items-start mb-3">
                  <div>
                    <h3 class="text-lg font-semibold text-gray-900 break-all"><?php echo htmlspecialchars($row["domain"]); ?></h3>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($row["email"] ?? 'No Email'); ?></p>
                  </div>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row['license_type'] === 'lifetime' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?> capitalize">
                    <?php echo htmlspecialchars($row["license_type"] ?? 'N/A'); ?>
                  </span>
                </div>
                
                <div class="space-y-3 mb-4">
                  <div class="flex justify-between text-sm border-b border-gray-50 pb-2">
                    <span class="text-gray-500">Expires:</span>
                    <span class="font-medium text-gray-900"><?php echo ($row["license_type"] === 'lifetime' ? 'Never' : ($row["expiry_date"] ? date('M d, Y', strtotime($row["expiry_date"])) : 'N/A')); ?></span>
                  </div>
                  
                  <div class="bg-gray-50 rounded p-2 text-xs">
                    <p class="text-gray-500 mb-1">API Key:</p>
                    <div class="flex items-center justify-between">
                      <code class="text-indigo-600 font-mono truncate mr-2 select-all"><?php echo htmlspecialchars($row["api_key"] ?? 'Not Generated'); ?></code>
                      <button onclick="copyToClipboard('<?php echo htmlspecialchars($row["api_key"] ?? ''); ?>', this)" class="text-gray-400 hover:text-indigo-600">
                        <i class="fas fa-copy"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-gray-100">
                  <button onclick="showViewModal('<?php echo $row['id']; ?>')" class="flex items-center justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-eye mr-2"></i> View
                  </button>
                  <button onclick="confirmAction('regenerate', <?php echo $row['id']; ?>)" class="flex items-center justify-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    <i class="fas fa-sync-alt mr-2"></i> Key
                  </button>
                  <a href="edit-domain?id=<?php echo $row['id']; ?>" class="flex items-center justify-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-edit mr-2"></i> Edit
                  </a>
                </div>
              </div>
            <?php
              }
            } else {
              echo "<div class='text-center py-8 text-gray-500'>No domains found matching your criteria.</div>";
            }
            ?>
          </div>

          <?php if ($result->num_rows == 0): ?>
             <!-- Fallback message for no domains visible on mobile if not already shown -->
              <div class='text-center py-12 text-gray-500 col-span-full bg-gray-50 rounded-lg border border-dashed border-gray-300 md:hidden'>
                  <svg class='mx-auto h-12 w-12 text-gray-400 mb-3' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                  <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' />
                  </svg>
                  <p class='text-lg font-medium'>No domains found</p>
                  <p class='mt-1'>Add your first domain to get started</p>
                  <a href='add-domain.php' class='mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700'>
                  <svg class='h-4 w-4 mr-2' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                  <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 4v16m8-8H4' />
                  </svg>
                  Add Domain</a>
              </div>
          <?php endif; ?>


        </div>
      </main>
    </div>
  </div>

  <script>
    const csrfToken = "<?php echo CSRFProtection::getToken(); ?>";
    function toggleDropdown(id) {
      const dropdown = document.getElementById(id);
      dropdown.classList.toggle('hidden');
    }

    function showViewModal(id) {
      const modal = document.getElementById('viewModal' + id);
      const container = modal.querySelector('.modal-container');
      
      // First make the modal visible but transparent
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      
      // Force a reflow to enable the transition
      void modal.offsetWidth;
      
      // Add opacity and transform the container
      modal.classList.add('bg-opacity-75');
      container.classList.add('sm:scale-100', 'opacity-100');
      container.classList.remove('opacity-0', 'sm:scale-95');
    }

    function hideViewModal(id) {
      const modal = document.getElementById('viewModal' + id);
      const container = modal.querySelector('.modal-container');
      
      // Start the fade out animation
      modal.classList.remove('bg-opacity-75');
      container.classList.add('opacity-0', 'sm:scale-95');
      container.classList.remove('sm:scale-100');
      
      // After animation completes, hide the modal
      setTimeout(() => {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
      }, 300);
    }

    // Close dropdowns when clicking outside
    window.onclick = function(event) {
      // Check if the click was outside of a dropdown or its toggle button
      if (!event.target.closest('.domain-actions-dropdown') && !event.target.closest('.domain-actions-toggle')) {
        const dropdowns = document.getElementsByClassName('domain-actions-dropdown');
        for (let i = 0; i < dropdowns.length; i++) {
          const openDropdown = dropdowns[i];
          if (!openDropdown.classList.contains('hidden')) {
            openDropdown.classList.add('hidden');
          }
        }
      }
    }

    let currentActionUrl = '';

    function confirmAction(type, id) {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmTitle');
        const desc = document.getElementById('confirmDescription');
        const confirmBtn = document.getElementById('confirmBtn');
        const iconContainer = document.getElementById('confirmIconContainer');
        const icon = document.getElementById('confirmIcon');

        if (type === 'regenerate') {
            title.innerText = 'Regenerate API Key?';
            desc.innerText = 'Are you sure you want to generate a new key? The current key will be deactivated immediately and any connected applications will lose access.';
            confirmBtn.innerText = 'Regenerate Key';
            confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm transition-all duration-200';
            iconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10';
            icon.className = 'fas fa-sync-alt text-indigo-600';
            currentActionUrl = 'domains.php?regenerate_key=' + id + '&csrf_token=' + csrfToken;
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
            // Success feedback
            const originalIcon = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
            setTimeout(() => {
                btn.innerHTML = originalIcon;
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            // Fallback for older browsers
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalIcon;
                }, 2000);
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }
            document.body.removeChild(textArea);
        });
    }

  </script>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 w-full">
      <!-- Overlay -->
      <div class="fixed inset-0 bg-gray-500 bg-opacity-0 transition-opacity duration-300 ease-out" aria-hidden="true" onclick="hideConfirmModal()"></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <!-- Modal panel -->
      <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300 ease-out">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div id="confirmIconContainer" class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10">
              <i id="confirmIcon" class="text-xl"></i>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
              <h3 class="text-xl leading-6 font-bold text-gray-900" id="confirmTitle">Confirm Action</h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500" id="confirmDescription">Are you sure you want to proceed with this action?</p>
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
  // Re-establish connection or ensure $conn is available if closed previously
  // This assumes a new connection might be needed if dashboard.php closed it.
  // A better approach might be to keep connection open or use a function.
  // For simplicity, we'll assume $conn is either open or can be re-opened if needed.
  
  // Need to reset result pointer BEFORE fetching again for modals
  if ($result->num_rows > 0) {
      $result->data_seek(0);
      while ($row = $result->fetch_assoc()) {
          include 'includes/view-modal.php';
      }
  }
  ?>
</body>
</html> 