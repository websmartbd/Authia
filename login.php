<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';

$error_message = null; // Initialize error message

// Generate CSRF token using security class
CSRFProtection::generateToken();
// Check if the user is already authenticated via session
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    // If authenticated, redirect to dashboard page
    header("Location: dashboard");
    exit;
}
// Check for "Remember Me" cookie if session is not active
if (!isset($_SESSION['authenticated']) && isset($_COOKIE['remember_me'])) {
    $remember_token = $_COOKIE['remember_me'];

    // Connect to the database to validate the token
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        // Log error, but don't expose detailed database error to user
        error_log("Database connection failed during remember me check: " . $conn->connect_error);
        // Continue to display login page
    } else {
        // Prepare SQL statement to retrieve user by remember_token
        $stmt = $conn->prepare("SELECT id FROM users WHERE remember_token = ?");

        if ($stmt) {
            $stmt->bind_param("s", $remember_token);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user) {
                // Token is valid, authenticate user and redirect
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['id']; // Store user ID in session
                
                // Optional: Regenerate token for security
                $new_remember_token = bin2hex(random_bytes(32));
                $update_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("si", $new_remember_token, $user['id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                    // Update cookie with the new token
                    $expiry = time() + (86400 * 30); // 30 days
                    setcookie('remember_me', $new_remember_token, $expiry, "/", "", false, true);
                }

                header("Location: dashboard");
                exit;
            } else {
                // Invalid token, clear the cookie
                setcookie('remember_me', '', time() - 3600, "/");
            }
        } else {
             error_log("Error preparing database statement for remember me: " . $conn->error);
        }
            $conn->close();
        }
    }

// Handle login form submission (POST request)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token using security class
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Security validation failed. Please try again.";
    } else {
        $login_input = InputValidator::sanitizeString($_POST['login_input'] ?? '');
        $password_input = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        // Check rate limiting (5 attempts per 15 minutes)
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::check($client_ip, 'login')) {
            $remaining_time = 15; // minutes
            $error_message = "Too many login attempts. Please try again in {$remaining_time} minutes.";
        } else {
            // Connect to the database for login validation
            $conn = new mysqli($host, $username, $password, $database);

            if ($conn->connect_error) {
                $error_message = "Database connection failed.";
                error_log("Database connection failed during login attempt: " . $conn->connect_error);
            } else {
                // Modified SQL to check both username and email
                $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ? OR email = ?");

                if ($stmt) {
                    $stmt->bind_param("ss", $login_input, $login_input);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();

                    // Verify password
                    if ($user && password_verify($password_input, $user['password'])) {
                        // Authentication successful - reset rate limiter
                        RateLimiter::reset($client_ip, 'login');
                        
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        $_SESSION['authenticated'] = true;
                        $_SESSION['user_id'] = $user['id']; // Store user ID in session

                        // Handle "Remember Me" on successful login
                        if ($remember_me) {
                            // Generate a secure, unique token
                            $remember_token = bin2hex(random_bytes(32)); // 64 characters

                            // Store the token in the database
                            $update_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param("si", $remember_token, $user['id']);
                                $update_stmt->execute();
                                $update_stmt->close();

                                // Set the cookie (expires in 30 days)
                                $expiry = time() + (86400 * 30); // 86400 = 1 day, 30 days
                                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                                setcookie('remember_me', $remember_token, $expiry, "/", "", $secure, true); // secure, httponly
                            } else {
                                 error_log("Error preparing remember token update statement: " . $conn->error);
                            }
                        } else {
                            // If "Remember Me" is not checked, clear any existing token/cookie for this user
                            $update_stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                            if ($update_stmt) {
                                 $update_stmt->bind_param("i", $user['id']);
                                 $update_stmt->execute();
                                 $update_stmt->close();
                            }
                            if (isset($_COOKIE['remember_me'])) {
                                 setcookie('remember_me', '', time() - 3600, "/");
                            }
                        }
                        // Redirect to dashboard page
                        header("Location: dashboard");
                        exit;
                    } else {
                        // Authentication failed
                        $error_message = "Invalid login credentials.";
                    }
                } else {
                    $error_message = "Error preparing database statement.";
                    error_log("Error preparing login statement: " . $conn->error);
                }
                $conn->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        .gradient-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        .form-input:focus {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            transition: all 0.2s ease;
        }
        .login-btn {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .login-btn:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        .login-btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.3;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }
        .error-message {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        .form-container {
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 1s ease-out;
        }
        
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        @media (max-width: 640px) {
            .form-container {
                padding: 1.5rem;
                margin: 0.5rem;
            }
        }
    </style>
  </head>
  <body class="min-h-screen bg-gray-50">
    <div class="flex min-h-screen">
        <!-- Left Side - Image/Pattern -->
        <div class="hidden lg:flex lg:w-1/2 gradient-bg items-center justify-center">
            <div class="text-white text-center px-12 z-10 animate-fadeIn">
                <div class="mb-8 animate-pulse">
                    <i class="fas fa-shield-alt text-5xl mb-4 opacity-90"></i>
                </div>
                <h1 class="text-4xl font-bold mb-4">Welcome Back!</h1>
                <p class="text-xl opacity-90 mb-6">Manage your domains with our powerful control panel</p>
                <div class="flex justify-center space-x-6 mb-6">
                    <div class="text-center">
                        <i class="fas fa-globe text-3xl mb-2"></i>
                        <p class="text-sm">Domains</p>
                    </div>
                    <div class="text-center">
                        <i class="fas fa-key text-3xl mb-2"></i>
                        <p class="text-sm">Security</p>
                    </div>
                    <div class="text-center">
                        <i class="fas fa-tachometer-alt text-3xl mb-2"></i>
                        <p class="text-sm">Performance</p>
                    </div>
                </div>
                <div class="w-16 h-1 bg-white opacity-50 mx-auto rounded-full"></div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-6">
            <div class="w-full max-w-md px-6 sm:px-8 py-8 sm:py-10 bg-white rounded-xl shadow-lg form-container backdrop-blur-sm bg-white/95">
                <div class="text-center mb-10">
                    <div class="inline-block p-3 rounded-full bg-indigo-100 mb-4">
                        <i class="fas fa-user-shield text-3xl text-indigo-600"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900">Control Panel</h2>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-center error-message">
                        <div class="flex items-center justify-center">
                            <p><i class="fas fa-times-circle mr-1"></i> Invalid username or password.</p>
                        </div>
                    </div>
                <?php elseif ($error_message): // Display internal errors if any ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-center error-message">
                        <div class="flex items-center justify-center">
                            <p><i class="fas fa-times-circle mr-1"></i> <?php echo InputValidator::escapeHtml($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" action="login.php" method="post">
                    <!-- CSRF Protection -->
                    <?php echo CSRFProtection::getTokenField(); ?>
                    <div>
                        <label for="login_input" class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-indigo-500"></i>
                            </div>
                            <input id="login_input" name="login_input" type="text" required
                                class="form-input block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-all duration-200"
                                placeholder="Enter your username or email">
                        </div>
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-indigo-500"></i>
                            </div>
                            <input id="password" name="password" type="password" required
                                class="form-input block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-all duration-200"
                                placeholder="Enter your password">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                                <i id="togglePassword" class="fas fa-eye text-gray-400 hover:text-indigo-500 transition-colors duration-200"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Remember Me Checkbox -->
                    <div class="flex items-center justify-between pt-2">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded transition-all duration-200">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700 hover:text-gray-900 transition-colors duration-200">
                                Remember me
                            </label>
                        </div>
                            <div class="text-sm">
                            <a href="reset-password" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors duration-200">
                                <i class="fas fa-question-circle mr-1"></i> Forgot your password?
                            </a>
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit" id="loginButton"
                            class="login-btn w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-300 transform hover:-translate-y-0.5">
                            <span>Sign in</span>
                            <span id="loadingIndicator" class="hidden ml-2">
                                <i class="fas fa-circle-notch fa-spin"></i>
                            </span>
                        </button>
                    </div>
                </form>
                
                <div class="mt-8 text-center text-xs text-gray-500">
                    <p><i class="fas fa-shield-alt mr-1"></i>Protected by encryption</p>

                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Form validation and enhancements
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const usernameInput = document.getElementById('login_input');
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        
        // Password visibility toggle
        togglePassword.addEventListener('click', function() {
            // Toggle password visibility
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Add focus animations
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('ring-2', 'ring-indigo-100', 'ring-opacity-50');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('ring-2', 'ring-indigo-100', 'ring-opacity-50');
            });
        });
        
        // Form validation
        form.addEventListener('submit', function(e) {
            let valid = true;
            
            if (!usernameInput.value.trim()) {
                valid = false;
                highlightError(usernameInput);
            }
            
            if (!passwordInput.value.trim()) {
                valid = false;
                highlightError(passwordInput);
            }
            
            if (!valid) {
                e.preventDefault();
            } else {
                // Show loading indicator
                const loginButton = document.getElementById('loginButton');
                const loadingIndicator = document.getElementById('loadingIndicator');
                
                if (loginButton && loadingIndicator) {
                    loginButton.disabled = true;
                    loadingIndicator.classList.remove('hidden');
                }
            }
        });
        
        function highlightError(input) {
            input.classList.add('border-red-500');
            input.addEventListener('input', function() {
                input.classList.remove('border-red-500');
            }, { once: true });
        }
        
        // Auto-focus username field if empty
        if (!usernameInput.value) {
            setTimeout(() => usernameInput.focus(), 100);
        }
    });
    </script>
  </body>
</html>
