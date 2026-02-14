<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

// Include the configuration file
require_once 'config/config.php';
require_once 'config/smtp.php';

// Set timezone for consistent expiration checks
date_default_timezone_set('UTC');

// Generate CSRF token
CSRFProtection::generateToken();

// Get messages from session
$error_message = $_SESSION['reset_error'] ?? null;
$success_message = $_SESSION['reset_success'] ?? null;
$step = $_SESSION['reset_step'] ?? 'request'; // Steps: request, verify, reset, complete

// Clear messages from session immediately after reading
unset($_SESSION['reset_error'], $_SESSION['reset_success']);

// Connect to the database
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token using security class
    if (!CSRFProtection::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['reset_error'] = "Security validation failed. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        
        // Rate limiting for all reset actions
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!RateLimiter::check($client_ip, 'reset_password')) {
            $_SESSION['reset_error'] = "Too many attempts. Please try again in 15 minutes.";
        } else {
            // STEP 1: Request Code
            if ($action === 'request_code') {
                $login_input = trim($_POST['login_input'] ?? '');
                
                if (empty($login_input)) {
                    $_SESSION['reset_error'] = "Please enter your username or email.";
                } else {
                    $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE username = ? OR email = ?");
                    $stmt->bind_param("ss", $login_input, $login_input);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    if ($user) {
                        // Generate 6-digit OTP
                        $otp_code = sprintf("%06d", mt_rand(100000, 999999));
                        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        
                        // Store in DB
                        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                        $update->bind_param("ssi", $otp_code, $expires, $user['id']);
                        
                        if ($update->execute()) {
                            if (!empty($user['email'])) {
                                $email_content = generate_otp_email($otp_code, $user['username']);
                                if (send_html_email($user['email'], "Verification Code: $otp_code", $email_content)) {
                                    $_SESSION['reset_user_id'] = $user['id'];
                                    $_SESSION['reset_step'] = 'verify';
                                } else {
                                    $_SESSION['reset_error'] = "Failed to send email. Please check your SMTP settings.";
                                }
                            } else {
                                $_SESSION['reset_error'] = "No email associated with this account. (Dev Code: $otp_code)";
                            }
                        }
                    } else {
                        $_SESSION['reset_error'] = "If an account exists, a code has been sent.";
                        $_SESSION['reset_step'] = 'verify'; 
                    }
                }
            }
            // STEP 2: Verify Code
            elseif ($action === 'verify_code') {
                $code = trim($_POST['otp_code'] ?? '');
                $user_id = $_SESSION['reset_user_id'] ?? 0;
                $now = date('Y-m-d H:i:s');
                
                $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND reset_token = ? AND reset_token_expires > ?");
                $stmt->bind_param("iss", $user_id, $code, $now);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $_SESSION['reset_step'] = 'reset';
                } else {
                    $_SESSION['reset_error'] = "Invalid or expired verification code.";
                }
            }
            // STEP 3: Reset Password
            elseif ($action === 'reset_password') {
                $new_pass = $_POST['new_password'] ?? '';
                $conf_pass = $_POST['confirm_password'] ?? '';
                $user_id = $_SESSION['reset_user_id'] ?? 0;
                
                $pwdCheck = InputValidator::validatePassword($new_pass);
                if (!$pwdCheck['valid']) {
                    $_SESSION['reset_error'] = $pwdCheck['message'];
                } elseif ($new_pass !== $conf_pass) {
                    $_SESSION['reset_error'] = "Passwords do not match.";
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_ARGON2ID);
                    $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
                    $update->bind_param("si", $hashed, $user_id);
                    
                    if ($update->execute()) {
                        $_SESSION['reset_success'] = "Password updated successfully!";
                        $_SESSION['reset_step'] = 'complete';
                        unset($_SESSION['reset_user_id']);
                        RateLimiter::reset($client_ip, 'reset_password');
                    }
                }
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: reset-password.php");
    exit;
}

// Reset flow if requested
if (isset($_GET['restart'])) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_step'], $_SESSION['reset_error'], $_SESSION['reset_success']);
    header("Location: reset-password.php");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Authenticator</title>
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
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
        }
        .form-input:focus {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            transition: all 0.2s ease;
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
            0% { transform: scale(0, 0); opacity: 0.5; }
            20% { transform: scale(25, 25); opacity: 0.3; }
            100% { opacity: 0; transform: scale(40, 40); }
        }
        .otp-input:focus { letter-spacing: 0.4em; }
        @media (max-width: 640px) {
            .form-container {
                padding: 1.5rem;
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col">
    <div class="flex min-h-screen">
        <!-- Left Side - Image/Pattern (Matching login.php) -->
        <div class="hidden lg:flex lg:w-1/2 gradient-bg items-center justify-center">
            <div class="text-white text-center px-12 z-10 animate-fadeIn">
                <div class="mb-8 animate-pulse">
                    <i class="fas fa-shield-alt text-5xl mb-4 opacity-90"></i>
                </div>
                <h1 class="text-4xl font-bold mb-4">Security Center</h1>
                <p class="text-xl opacity-90 mb-6">Recover your access with our secure verification system</p>
                <div class="flex justify-center space-x-6 mb-6">
                    <div class="text-center">
                        <i class="fas fa-envelope-open-text text-3xl mb-2"></i>
                        <p class="text-sm">Verify</p>
                    </div>
                    <div class="text-center">
                        <i class="fas fa-user-lock text-3xl mb-2"></i>
                        <p class="text-sm">Identity</p>
                    </div>
                    <div class="text-center">
                        <i class="fas fa-redo text-3xl mb-2"></i>
                        <p class="text-sm">Restore</p>
                    </div>
                </div>
                <div class="w-16 h-1 bg-white opacity-50 mx-auto rounded-full"></div>
            </div>
        </div>

        <!-- Right Side - Functional Form -->
        <div class="w-full lg:w-1/2 flex items-center justify-center p-4 sm:p-6">
            <div class="w-full max-w-md px-6 sm:px-8 py-8 sm:py-10 bg-white rounded-xl shadow-lg form-container backdrop-blur-sm bg-white/95">
                
                <!-- Step Header -->
                <div class="text-center mb-8">
                    <div class="inline-block p-3 rounded-full bg-indigo-100 mb-4">
                        <?php if ($step === 'request'): ?>
                            <i class="fas fa-key text-3xl text-indigo-600"></i>
                        <?php elseif ($step === 'verify'): ?>
                            <i class="fas fa-envelope-open-text text-3xl text-indigo-600"></i>
                        <?php elseif ($step === 'reset'): ?>
                            <i class="fas fa-lock text-3xl text-indigo-600"></i>
                        <?php else: ?>
                            <i class="fas fa-check-circle text-3xl text-green-500"></i>
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="text-3xl font-bold text-gray-900">
                        <?php 
                        if ($step === 'request') echo "Reset Password";
                        elseif ($step === 'verify') echo "Verify Code";
                        elseif ($step === 'reset') echo "New Password";
                        else echo "Success!";
                        ?>
                    </h2>
                </div>

                <!-- Messages -->
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center animate-fadeIn">
                        <i class="fas fa-exclamation-triangle mr-3"></i>
                        <p class="text-sm"><?php echo InputValidator::escapeHtml($error_message); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center animate-fadeIn">
                        <i class="fas fa-check-circle mr-3"></i>
                        <p class="text-sm"><?php echo InputValidator::escapeHtml($success_message); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'complete'): ?>
                    <div class="text-center space-y-6">
                        <p class="text-gray-600">Your password has been changed successfully. You can now return to the login page.</p>
                        <a href="login" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-all duration-300">
                            Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-6">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        
                        <?php if ($step === 'request'): ?>
                            <input type="hidden" name="action" value="request_code">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
                                <div class="relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-indigo-500"></i>
                                    </div>
                                    <input type="text" name="login_input" required 
                                        class="form-input block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-all duration-200"
                                        placeholder="Enter your credentials">
                                </div>
                            </div>
                            <button type="submit" id="submitBtn" class="login-btn w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-all duration-300">
                                <span><i class="fas fa-paper-plane mr-2"></i> Send Reset Code</span>
                                <span id="loadingIndicator" class="hidden ml-2"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </button>

                        <?php elseif ($step === 'verify'): ?>
                            <input type="hidden" name="action" value="verify_code">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1 text-center">6-Digit Verification Code</label>
                                <input type="text" name="otp_code" required maxlength="6" 
                                    class="otp-input block w-full text-center text-3xl font-bold py-3 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200 tracking-[0.4em]"
                                    placeholder="000000">
                            </div>
                            <button type="submit" id="submitBtn" class="login-btn w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-all duration-300">
                                <span>Verify Code <i class="fas fa-arrow-right ml-2"></i></span>
                                <span id="loadingIndicator" class="hidden ml-2"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </button>
                            <div class="text-center">
                                <a href="?restart=1" class="text-sm text-indigo-600 hover:text-indigo-500 font-medium">
                                    <i class="fas fa-redo-alt mr-1"></i> Resend code
                                </a>
                            </div>

                        <?php elseif ($step === 'reset'): ?>
                            <input type="hidden" name="action" value="reset_password">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-lock text-indigo-500"></i>
                                        </div>
                                        <input type="password" id="new_password" name="new_password" required minlength="6"
                                            class="form-input block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-all duration-200">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                                            <i id="toggleNewPassword" class="fas fa-eye text-gray-400 hover:text-indigo-500 transition-colors duration-200"></i>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                    <div class="relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="fas fa-check-double text-indigo-500"></i>
                                        </div>
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                            class="form-input block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm transition-all duration-200">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                                            <i id="toggleConfirmPassword" class="fas fa-eye text-gray-400 hover:text-indigo-500 transition-colors duration-200"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" id="submitBtn" class="login-btn w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-all duration-300">
                                <span>Update Password <i class="fas fa-save ml-2"></i></span>
                                <span id="loadingIndicator" class="hidden ml-2"><i class="fas fa-circle-notch fa-spin"></i></span>
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <!-- Back Link -->
                <div class="mt-10 pt-8 border-t border-gray-100 text-center">
                    <a href="login" class="text-sm font-medium text-gray-500 hover:text-indigo-600 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Login Page
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                const loading = document.getElementById('loadingIndicator');
                if (btn && loading) {
                    btn.disabled = true;
                    loading.classList.remove('hidden');
                }
            });
        }

        // Password visibility toggles
        const setupToggle = (toggleId, inputId) => {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }
        };

        setupToggle('toggleNewPassword', 'new_password');
        setupToggle('toggleConfirmPassword', 'confirm_password');
    });
    </script>
</body>
</html>