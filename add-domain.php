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

// Set page title and icon for mobile header
$page_title = 'Add New Domain';
$page_icon = '<i class="fas fa-plus text-indigo-600 text-2xl"></i>';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Handle add domain form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    // Validate CSRF token
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        die('Security validation failed');
    }
    
    // Validate and sanitize inputs
    $email = InputValidator::sanitizeEmail($_POST['email'] ?? '');
    if ($email === false) {
        $error_message = "Invalid email address.";
    } else {
        $domain = InputValidator::validateDomain($_POST['domain'] ?? '');
        if ($domain === false) {
            $error_message = "Invalid domain name.";
        } else {
            $active = InputValidator::validateInt($_POST['active'] ?? 0, 0, 1);
            $delete = in_array($_POST['delete'] ?? 'no', ['yes', 'no']) ? $_POST['delete'] : 'no';
            $message = InputValidator::sanitizeString($_POST['message'] ?? '');
            $name = InputValidator::sanitizeString($_POST['name'] ?? '');
            $license_type = in_array($_POST['license_type'] ?? '', ['monthly', 'yearly', 'lifetime']) ? $_POST['license_type'] : 'monthly';

    // Calculate expiry date
    $expiry_date = null;
    if ($license_type === 'monthly') {
        $expiry_date = date('Y-m-d', strtotime('+30 days'));
    } elseif ($license_type === 'yearly') {
        $expiry_date = date('Y-m-d', strtotime('+1 year'));
    } else {
        // Lifetime - set to null or a far future date if you prefer
        $expiry_date = null; 
    }

    // Validation: Only allow flagging for deletion if inactive or expired
    $is_expired = ($license_type !== 'lifetime' && !empty($expiry_date) && $expiry_date < date('Y-m-d'));
    if ($delete === 'yes' && $active == 1 && !$is_expired) {
        $error_message = "A domain can only be flagged for deletion if it is either Inactive or Expired.";
    } else {
        // Insert new domain data into the database
        $sql = "INSERT INTO domains (email, domain, active, `delete`, message, name, license_type, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssss", $email, $domain, $active, $delete, $message, $name, $license_type, $expiry_date);

        if ($stmt->execute()) {
            $domain_id = $stmt->insert_id;
            
            // Auto-generate API Key
            $new_api_key = 'bm-' . substr(bin2hex(random_bytes(15)), 0, 28);
            
            // Insert into licenses
            $api_sql = "INSERT INTO licenses (api_key, domain_id) VALUES (?, ?)";
            $api_stmt = $conn->prepare($api_sql);
            $api_stmt->bind_param("si", $new_api_key, $domain_id);
            $api_stmt->execute();
            $api_stmt->close();

            // Redirect back to domains page after successful insertion
            header("Location: domains.php");
            exit;
        } else {
            $error_message = "Error adding record. Please try again.";
            error_log("Error adding domain: " . $stmt->error);
        }
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
  <title>Add New Domain - Authenticator</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fafb;
    }
    
    .form-input:focus, .form-select:focus {
      border-color: #4f46e5;
      box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
    }
    
    .card-shadow {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    /* Form input animations */
    .form-input, .form-select {
      transition: all 0.2s ease-in-out;
    }
    
    .form-input:hover, .form-select:hover {
      border-color: #6366f1;
    }
    
    /* Button animations */
    .btn-primary {
      transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
      transform: translateY(-1px);
    }
    
    .btn-primary:active {
      transform: translateY(0);
    }
  </style>
</head>
<body>
  <div class="flex h-screen bg-gray-50 overflow-hidden">
    <?php include 'includes/sidebar-modal.php'; ?>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Main content body -->
      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6 md:p-8 flex justify-center items-start">
        <!-- Content for the Add Domain page -->
        <div class="w-full max-w-3xl bg-white shadow-lg rounded-xl p-6 sm:p-8 border border-gray-200 card-shadow">
          <!-- Page header with icon -->
          <div class="text-center mb-8 hidden md:block">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 text-indigo-600 mb-4">
              <i class="fas fa-globe text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Add New Domain</h2>
            <p class="mt-2 text-gray-600">Register a new domain in the authentication system</p>
          </div>
          
          <?php if (isset($error_message)): ?>
          <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg">
            <div class="flex">
              <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-500"></i>
              </div>
              <div class="ml-3">
                <p class="text-sm text-red-700 font-medium"><?php echo InputValidator::escapeHtml($error_message); ?></p>
              </div>
            </div>
          </div>
          <?php endif; ?>
          
          <!-- Form with enhanced styling -->
          <form method="POST" class="space-y-6">
            <!-- CSRF Protection -->
            <?php echo CSRFProtection::getTokenField(); ?>
            <!-- Name and Email fields in a grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Name field -->
              <div class="space-y-2">
                <label for="name" class="block text-sm font-medium text-gray-700">Client Name</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-tag text-gray-400"></i>
                  </div>
                  <input type="text" name="name" id="name" required 
                    class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                    placeholder="Enter Client Name">
                </div>
              </div>
              
              <!-- Email field -->
              <div class="space-y-2">
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-envelope text-gray-400"></i>
                  </div>
                  <input type="email" name="email" id="email" required 
                    class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                    placeholder="contact@example.com">
                </div>
              </div>
            </div>
            
            <!-- Domain field -->
            <div class="space-y-2">
              <label for="domain" class="block text-sm font-medium text-gray-700">Domain Name</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-globe text-gray-400"></i>
                </div>
                <input type="text" name="domain" id="domain" required 
                  class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                  placeholder="example.com">
              </div>
            </div>
            
            <!-- License details, Status, and Delete Flag in a 3-column grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <!-- License Type field -->
              <div class="space-y-2">
                <label for="license_type" class="block text-sm font-medium text-gray-700">License Type</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-certificate text-gray-400"></i>
                  </div>
                  <select name="license_type" id="license_type" required
                    class="form-select block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <option value="monthly">Monthly</option>
                      <option value="yearly">Yearly</option>
                      <option value="lifetime">Lifetime</option>
                  </select>
                </div>
              </div>
              
              <!-- Status field -->
              <div class="space-y-2">
                <label for="active" class="block text-sm font-medium text-gray-700">Status</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-toggle-on text-gray-400"></i>
                  </div>
                  <select name="active" id="active" 
                    class="form-select block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <option value="1">Active</option>
                      <option value="0">Inactive</option>
                  </select>
                </div>
              </div>

              <!-- Delete Flag field -->
              <div class="space-y-2">
                <label for="delete" class="block text-sm font-medium text-gray-700">Delete Flag</label>
                <div class="relative rounded-md shadow-sm">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-trash-alt text-gray-400"></i>
                  </div>
                  <select name="delete" id="delete" 
                    class="form-select block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                      <option value="no">No</option>
                      <option value="yes">Yes</option>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Message field -->
            <div class="space-y-2">
              <label for="message" class="block text-sm font-medium text-gray-700">Message (Optional)</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute top-3 left-3 flex items-start pointer-events-none">
                  <i class="fas fa-comment text-gray-400"></i>
                </div>
                <textarea name="message" id="message" rows="5"
                  class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm resize-none" 
                  placeholder="Additional information about this domain"></textarea>
              </div>
            </div>
            
            <!-- Form buttons with enhanced styling -->
            <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-100">
              <a href="domains.php" 
                class="inline-flex justify-center items-center py-2.5 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-times mr-2"></i> Cancel
              </a>
              <button type="submit" name="add" 
                class="btn-primary inline-flex justify-center items-center py-2.5 px-4 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-plus mr-2"></i> Add Domain
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
  </div>
  
  <script>
    // Form validation enhancement
    const form = document.querySelector('form');
    const inputs = form.querySelectorAll('input[required]');
    
    inputs.forEach(input => {
      input.addEventListener('invalid', () => {
        input.classList.add('border-red-500');
      });
      
      input.addEventListener('input', () => {
        if (input.validity.valid) {
          input.classList.remove('border-red-500');
        }
      });
    });
  </script>
</body>
</html>