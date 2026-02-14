<?php
/**
 * Security Configuration and Helper Functions
 * 
 * This file contains security-related functions and configurations
 * to protect against common vulnerabilities.
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    die('Direct access not permitted');
}

/**
 * CSRF Token Management
 */
class CSRFProtection {
    
    /**
     * Generate a new CSRF token
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get the current CSRF token
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? self::generateToken();
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate hidden input field with CSRF token
     */
    public static function getTokenField() {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Input Validation and Sanitization
 */
class InputValidator {
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize email
     */
    public static function sanitizeEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
    
    /**
     * Validate domain name
     */
    public static function validateDomain($domain) {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        
        // Allow localhost
        if (strtolower($domain) === 'localhost') {
            return $domain;
        }

        // Allow IP addresses (IPv4)
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }

        // Validate standard domain format
        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            return $domain;
        }
        
        return false;
    }
    
    /**
     * Validate integer
     */
    public static function validateInt($value, $min = null, $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return false;
        }
        
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        // Minimum 8 characters, at least one letter and one number
        if (strlen($password) < 8) {
            return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/[A-Za-z]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one letter'];
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one number'];
        }
        
        return ['valid' => true, 'message' => 'Password is valid'];
    }
    
    /**
     * Sanitize HTML output
     */
    public static function escapeHtml($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Rate Limiting
 */
class RateLimiter {
    
    private static $maxAttempts = 5;
    private static $timeWindow = 900; // 15 minutes
    
    /**
     * Check if action is rate limited
     */
    public static function check($identifier, $action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action . '_' . $identifier;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if ($now - $data['start'] > self::$timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= self::$maxAttempts) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
    
    /**
     * Reset rate limit for identifier
     */
    public static function reset($identifier, $action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action . '_' . $identifier;
        unset($_SESSION[$key]);
    }
    
    /**
     * Get remaining attempts
     */
    public static function getRemainingAttempts($identifier, $action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action . '_' . $identifier;
        
        if (!isset($_SESSION[$key])) {
            return self::$maxAttempts;
        }
        
        $data = $_SESSION[$key];
        $now = time();
        
        // Reset if time window has passed
        if ($now - $data['start'] > self::$timeWindow) {
            return self::$maxAttempts;
        }
        
        return max(0, self::$maxAttempts - $data['count']);
    }
}

/**
 * Session Security
 */
class SessionSecurity {
    
    /**
     * Initialize secure session
     */
    public static function init() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Enable secure flag if HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
        
        // Regenerate session ID on login
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Session hijacking prevention
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            // Possible session hijacking
            session_destroy();
            session_start();
            return false;
        }
        
        return true;
    }
    
    /**
     * Destroy session securely
     */
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            session_destroy();
        }
    }
}

/**
 * Security Headers
 */
class SecurityHeaders {
    
    /**
     * Set security headers
     */
    public static function set() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        // Remove any existing CSP to avoid conflicts
        header_remove('Content-Security-Policy');
        
        // Content Security Policy - broadened for Tailwind and common CDNs
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://*.tailwindcss.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.tailwindcss.com https://*.tailwindcss.com; ";
        $csp .= "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self' https://*.tailwindcss.com; ";
        $csp .= "frame-ancestors 'none';";
        
        header("Content-Security-Policy: " . $csp);
        
        // Strict Transport Security (only if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

/**
 * Database Security Helper
 */
class DatabaseSecurity {
    
    /**
     * Prepare and execute a SELECT query safely
     */
    public static function select($conn, $query, $params = [], $types = '') {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Prepare and execute an INSERT/UPDATE/DELETE query safely
     */
    public static function execute($conn, $query, $params = [], $types = '') {
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}

/**
 * File Security
 */
class FileSecurity {
    
    /**
     * Validate file upload
     */
    public static function validateUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        // Check if file was uploaded
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['valid' => false, 'message' => 'Invalid file upload'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Upload error occurred'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'File size exceeds limit'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'message' => 'Invalid file type'];
        }
        
        return ['valid' => true, 'message' => 'File is valid'];
    }
}

// Initialize security headers
SecurityHeaders::set();
