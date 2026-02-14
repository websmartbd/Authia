<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration files
require_once 'config/config.php';
require_once 'config/smtp.php';

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}

// Set page title and icon for mobile header
$page_title = 'Change Password';
$page_icon = '<i class="fas fa-lock text-indigo-600 text-2xl"></i>';

// Generate CSRF token
CSRFProtection::generateToken();

$success_message = null;
$error_message = null;
$user_email = '';

// Get current user email
$conn = new mysqli($host, $username, $password, $database);
if (!$conn->connect_error) {
    $admin_username = 'admin';
    $stmt = $conn->prepare("SELECT email FROM users WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $admin_username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_email = $row['email'];
        }
        $stmt->close();
    }
    $conn->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token using security class
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Security validation failed. Please try again.";
        header("Location: change-password.php");
        exit;
    }
    
    // Rate limiting (5 attempts per 15 minutes)
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!RateLimiter::check($client_ip, 'change_password')) {
        $_SESSION['error_message'] = "Too many password change attempts. Please try again in 15 minutes.";
        header("Location: change-password.php");
        exit;
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = InputValidator::sanitizeEmail($_POST['email'] ?? '');
    
    // Validate email
    if ($email === false) {
        $_SESSION['error_message'] = "Invalid email address.";
        header("Location: change-password.php");
        exit;
    }

    // Connect to the database
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        $_SESSION['error_message'] = "Connection failed. Please try again later.";
        header("Location: change-password.php");
        exit;
    }

    // Fetch user for password verification
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $_SESSION['error_message'] = "Current password is incorrect.";
        $conn->close();
        header("Location: change-password.php");
        exit;
    }

    $updates = [];
    $params = [];
    $types = "";

    // If new password is provided, check and prepare update
    if (!empty($new_password) || !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $_SESSION['error_message'] = "New password and confirm password do not match.";
            $conn->close();
            header("Location: change-password.php");
            exit;
        }
        
        // Validate password strength
        $pwdCheck = InputValidator::validatePassword($new_password);
        if (!$pwdCheck['valid']) {
            $_SESSION['error_message'] = $pwdCheck['message'];
            $conn->close();
            header("Location: change-password.php");
            exit;
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);
        $updates[] = "password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }

    // If email is provided and different, prepare update
    if (!empty($email)) {
        $updates[] = "email = ?";
        $params[] = $email;
        $types .= "s";
    }

    if (!empty($updates)) {
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $user['id'];
        $types .= "i";
        $update_stmt = $conn->prepare($sql);
        $update_stmt->bind_param($types, ...$params);

        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Account updated successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to update account. Please try again.";
        }
        $update_stmt->close();
    } else {
        $_SESSION['error_message'] = "Please fill in all required fields.";
    }

    $conn->close();
    header("Location: change-password.php");
    exit;
}

// Get messages from session and clear them
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password - Authenticator</title>
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
        <!-- Content for the Change Password page -->
        <div class="w-full max-w-xl bg-white shadow-lg rounded-xl p-6 sm:p-8 border border-gray-200 card-shadow">
          <!-- Page header with icon -->
          <div class="text-center mb-8 hidden md:block">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-indigo-100 text-indigo-600 mb-4">
              <i class="fas fa-lock text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Change Password</h2>
            <p class="mt-2 text-gray-600">Update your account password</p>
          </div>
          
          <!-- Success and error messages -->
          <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
              <i class="fas fa-check-circle mr-2"></i>
              <?php echo InputValidator::escapeHtml($success_message); ?>
            </div>
          <?php endif; ?>

          <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
              <i class="fas fa-exclamation-circle mr-2"></i>
              <?php echo InputValidator::escapeHtml($error_message); ?>
            </div>
          <?php endif; ?>
          
          <!-- Form with enhanced styling -->
          <form method="POST" class="space-y-6">
            <?php echo CSRFProtection::getTokenField(); ?>
            <!-- Current Password field -->
            <div class="space-y-2">
              <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-key text-gray-400"></i>
                </div>
                <input type="password" name="current_password" id="current_password" required 
                  class="form-input block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                  placeholder="Enter your current password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" id="toggleCurrentPassword">
                  <i class="fas fa-eye-slash text-gray-400"></i>
                </div>
              </div>
            </div>
            
            <!-- New Password field -->
            <div class="space-y-2">
              <label for="new_password" class="block text-sm font-medium text-gray-700">New Password (Optional)</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-400"></i>
                </div>
                <input type="password" name="new_password" id="new_password"
                  class="form-input block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                  placeholder="Enter your new password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" id="toggleNewPassword">
                  <i class="fas fa-eye-slash text-gray-400"></i>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Leave blank if you only want to update email</p>
            </div>
            
            <!-- Confirm Password field -->
            <div class="space-y-2">
              <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password (Optional)</label>
              <div class="relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i class="fas fa-lock text-gray-400"></i>
                </div>
                <input type="password" name="confirm_password" id="confirm_password"
                  class="form-input block w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                  placeholder="Confirm your new password">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" id="toggleConfirmPassword">
                  <i class="fas fa-eye-slash text-gray-400"></i>
                </div>
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
                  placeholder="Enter your email address" value="<?php echo InputValidator::escapeHtml($user_email); ?>">
              </div>
              <p class="text-xs text-gray-500 mt-1">Used for password recovery and notifications</p>
            </div>
            

            
            <!-- Form buttons -->
            <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 pt-6 border-t border-gray-100">
              <a href="dashboard" 
                class="inline-flex justify-center items-center py-2.5 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-times mr-2"></i> Cancel
              </a>
              <button type="submit" 
                class="btn-primary inline-flex justify-center items-center py-2.5 px-4 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                <i class="fas fa-save mr-2"></i> Update
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

    // Password visibility toggle functionality
    function setupPasswordToggle(inputId, toggleId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(toggleId);

        if (passwordInput && toggleIcon) {
            toggleIcon.addEventListener('click', () => {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggleIcon.querySelector('i').classList.toggle('fa-eye');
                toggleIcon.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    }

    // Setup toggles for each password field
    setupPasswordToggle('current_password', 'toggleCurrentPassword');
    setupPasswordToggle('new_password', 'toggleNewPassword');
    setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
  </script>
</body>
</html>