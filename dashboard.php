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

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Handle search with prepared statements
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = InputValidator::sanitizeString($_GET['search']);
    $search_param = "%$search%";
    $sql = "SELECT id, name, email, domain, active, message, `delete`, license_type, expiry_date FROM domains 
            WHERE name LIKE ? 
            OR email LIKE ? 
            OR domain LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT id, name, email, domain, active, message, `delete`, license_type, expiry_date FROM domains";
    $result = $conn->query($sql);
}

// Handle delete action with CSRF protection
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !CSRFProtection::validateToken($_GET['csrf_token'])) {
        die('Security validation failed');
    }
    
    $delete_id = InputValidator::validateInt($_GET['delete'], 1);
    if ($delete_id !== false) {
        // Use DatabaseSecurity helper for safer execution
        DatabaseSecurity::execute($conn, "DELETE FROM licenses WHERE domain_id = ?", [$delete_id], "i");
        DatabaseSecurity::execute($conn, "DELETE FROM domains WHERE id = ?", [$delete_id], "i");
        header("Location: dashboard");
        exit;
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Domain Control Panel</title>
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
    
    /* Stats card styles */
    .stats-card {
      transition: all 0.3s ease;
    }
    
    .stats-card:hover {
      transform: translateY(-3px);
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
        <div class="mb-8">
          <h1 class="text-2xl font-bold text-gray-900 mb-2">Domain Dashboard</h1>
          <p class="text-sm text-gray-600">Manage and monitor all your registered domains</p>
        </div>
        
        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
          <div class="bg-white overflow-hidden shadow-md rounded-xl stats-card border border-gray-100">
            <div class="p-5 sm:p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-xl p-3">
                  <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"> <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path> </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Domains</dt>
                    <dd class="text-2xl font-bold text-gray-900"><?php echo $result->num_rows; ?></dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow-md rounded-xl stats-card border border-gray-100">
            <div class="p-5 sm:p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-green-500 rounded-xl p-3">
                  <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Active Domains</dt>
                    <dd class="text-2xl font-bold text-gray-900">
                      <?php
                      $active_count = 0;
                      $result->data_seek(0);
                      while ($row = $result->fetch_assoc()) {
                        if ($row["active"] == 1) $active_count++;
                      }
                      echo $active_count;
                      $result->data_seek(0);
                      ?>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <!-- Inactive Domains Card -->
          <div class="bg-white overflow-hidden shadow-md rounded-xl stats-card border border-gray-100">
            <div class="p-5 sm:p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-500 rounded-xl p-3">
                  <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Inactive Domains</dt>
                    <dd class="text-2xl font-bold text-gray-900">
                      <?php
                      $inactive_count = 0;
                      $result->data_seek(0);
                      while ($row = $result->fetch_assoc()) {
                        if ($row["active"] == 0) $inactive_count++;
                      }
                      echo $inactive_count;
                      $result->data_seek(0);
                      ?>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Expired Domains Card -->
          <a href="expires" class="bg-white overflow-hidden shadow-md rounded-xl stats-card border border-gray-100 hover:border-red-300 transition-all duration-300">
            <div class="p-5 sm:p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-red-600 rounded-xl p-3">
                  <i class="fas fa-calendar-times text-white text-xl"></i>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">Expired Domains</dt>
                    <dd class="text-2xl font-bold text-gray-900">
                      <?php
                      $expired_count = 0;
                      $result->data_seek(0);
                      $today = date('Y-m-d');
                      while ($row = $result->fetch_assoc()) {
                        if ($row["license_type"] !== 'lifetime' && !empty($row["expiry_date"]) && $row["expiry_date"] < $today && $row["delete"] !== 'yes') {
                          $expired_count++;
                        }
                      }
                      echo $expired_count;
                      $result->data_seek(0);
                      ?>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </a>
          
          <!-- Delete Flag Count Card -->
          <a href="delete" class="bg-gradient-to-r from-red-500 to-orange-600 overflow-hidden shadow-md rounded-xl stats-card hover:shadow-lg transition-all duration-300">
            <div class="p-5 sm:p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0 bg-white bg-opacity-20 rounded-xl p-3">
                  <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                  <dl>
                    <dt class="text-sm font-medium text-white truncate">Marked for Deletion</dt>
                    <dd class="text-2xl font-bold text-white">
                      <?php
                      $delete_count = 0;
                      $result->data_seek(0);
                      while ($row = $result->fetch_assoc()) {
                        if ($row["delete"] == 'yes') $delete_count++;
                      }
                      echo $delete_count;
                      $result->data_seek(0);
                      ?>
                    </dd>
                  </dl>
                </div>
              </div>
            </div>
          </a>
        </div>

      </main>
    </div>
  </div>
</body>
</html>
