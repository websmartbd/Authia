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
$page_title = 'Edit Domain';
$page_icon = '<i class="fas fa-edit text-indigo-600 text-2xl"></i>';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

$domain_data = null;
$domain_id = null;

// Fetch domain data if ID is provided in GET request
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $domain_id = InputValidator::validateInt($_GET['id'], 1);
    if ($domain_id === false) {
        header("Location: domains.php");
        exit;
    }
    $sql = "SELECT id, email, domain, active, message, `delete`, name, license_type, expiry_date FROM domains WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $domain_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $domain_data = $result->fetch_assoc();
    } else {
        // Redirect or show error if domain not found
        header("Location: domains.php");
        exit;
    }
}

// Handle edit form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit'])) {
    // Validate CSRF token
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        die('Security validation failed');
    }
    
    // Validate and sanitize inputs
    $id = InputValidator::validateInt($_POST['id'] ?? 0, 1);
    if ($id === false) {
        $error_message = "Invalid domain ID.";
    } else {
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
                $expiry_date = $_POST['expiry_date'] ?? null;

    // Validation: Only allow flagging for deletion if inactive or expired
    $is_expired = ($license_type !== 'lifetime' && !empty($expiry_date) && $expiry_date < date('Y-m-d'));
    if ($delete === 'yes' && $active == 1 && !$is_expired) {
        $error_message = "A domain can only be flagged for deletion if it is either Inactive or Expired.";
    } else {
        // Update domain data in the database
        $update_sql = "UPDATE domains SET email=?, domain=?, active=?, `delete`=?, message=?, name=?, license_type=?, expiry_date=? WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssisssssi", $email, $domain, $active, $delete, $message, $name, $license_type, $expiry_date, $id);

        if ($stmt->execute()) {
            header("Location: domains.php");
            exit;
        } else {
            $error_message = "Error updating record. Please try again.";
            error_log("Error updating domain: " . $stmt->error);
        }
    }
            }
        }
    }
}

$conn->close();

// Redirect if no ID was provided to fetch data and not a POST request
if ($domain_data === null && $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: domains.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Domain - Authenticator</title>
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
        <!-- Content for the Edit Domain page -->
        <div class="w-full max-w-3xl bg-white shadow-lg rounded-xl p-6 sm:p-8 border border-gray-200 card-shadow">
          <?php if ($domain_data): ?>
            <!-- Page header with icon -->
            <div class="text-center mb-8 hidden md:block">
              <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 text-indigo-600 mb-4">
                <i class="fas fa-edit text-2xl"></i>
              </div>
              <h2 class="text-2xl font-bold text-gray-900">Edit Domain</h2>
              <p class="mt-2 text-gray-600">Update domain information in the authentication system</p>
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
              <input type="hidden" name="id" value="<?php echo InputValidator::escapeHtml($domain_data['id']); ?>">
              
              <!-- Name and Email fields in a grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name field -->
                <div class="space-y-2">
                  <label for="name" class="block text-sm font-medium text-gray-700">Client Name</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-tag text-gray-400"></i>
                    </div>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($domain_data['name']); ?>" 
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
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($domain_data['email']); ?>" required 
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
                  <input type="text" name="domain" id="domain" value="<?php echo htmlspecialchars($domain_data['domain']); ?>" required 
                    class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                    placeholder="example.com">
                </div>
              </div>
              
              <!-- Academic & Status details in a 4-column grid -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- License Type field -->
                <div class="space-y-2">
                  <label for="license_type" class="block text-sm font-medium text-gray-700">License Type</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-certificate text-gray-400"></i>
                    </div>
                    <select name="license_type" id="license_type" required 
                      class="form-select block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="monthly" <?php echo ($domain_data['license_type'] == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo ($domain_data['license_type'] == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
                        <option value="lifetime" <?php echo ($domain_data['license_type'] == 'lifetime') ? 'selected' : ''; ?>>Lifetime</option>
                    </select>
                  </div>
                </div>
                
                <!-- Expiry Date field -->
                <div class="space-y-2">
                  <label for="expiry_date" class="block text-sm font-medium text-gray-700">Expiry Date</label>
                  <div class="relative rounded-md shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <i class="fas fa-calendar-alt text-gray-400"></i>
                    </div>
                    <input type="date" name="expiry_date" id="expiry_date" value="<?php echo htmlspecialchars($domain_data['expiry_date'] ?? ''); ?>" 
                      class="form-input block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
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
                        <option value="1" <?php echo ($domain_data['active'] == 1) ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($domain_data['active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
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
                        <option value="yes" <?php echo ($domain_data['delete'] == 'yes') ? 'selected' : ''; ?>>Yes</option>
                        <option value="no" <?php echo ($domain_data['delete'] == 'no') ? 'selected' : ''; ?>>No</option>
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
                    placeholder="Additional information about this domain"><?php echo htmlspecialchars($domain_data['message']); ?></textarea>
                </div>
              </div>
              
              <!-- Form buttons with enhanced styling -->
              <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-100">
                <a href="domains.php" 
                  class="inline-flex justify-center items-center py-2.5 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                  <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" name="edit" 
                  class="btn-primary inline-flex justify-center items-center py-2.5 px-4 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                  <i class="fas fa-save mr-2"></i> Save Changes
                </button>
              </div>
            </form>
          <?php else: ?>
            <div class="text-center py-12">
              <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-red-100 text-red-600 mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
              </div>
              <h3 class="text-xl font-medium text-red-600 mb-2">Domain Not Found</h3>
              <p class="text-gray-500 mb-6">The domain you are trying to edit could not be found.</p>
              <a href="dashboard" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
              </a>
            </div>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>
  </div>
  
  <script>
    // Form validation enhancement
    const form = document.querySelector('form');
    if (form) {
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
    }
  </script>
</body>
</html>