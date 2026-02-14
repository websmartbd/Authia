<?php
// Check if accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("Location: ../dashboard.php");
    exit;
}

// SMTP Configuration - Fetched from Database
function get_smtp_config() {
    static $config = null;
    if ($config !== null) return $config;

    // Use absolute path for reliability
    require __DIR__ . '/config.php';
    $db_conn = new mysqli($host, $username, $password, $database);
    if ($db_conn->connect_error) return [];

    $res = $db_conn->query("SELECT * FROM smtp_settings WHERE id = 1");
    $config = $res->fetch_assoc();
    $db_conn->close();
    return $config;
}

$smtp_config = get_smtp_config();

// Function to send email using direct SMTP connection
function send_email($to, $subject, $message, $headers = '') {
    global $smtp_config;
    
    // Default headers if none provided
    if (empty($headers)) {
        $headers = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
        $headers .= "Reply-To: {$smtp_config['reply_to']}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    try {
        // Create SSL connection
        $smtp = stream_socket_client(
            "ssl://{$smtp_config['host']}:{$smtp_config['port']}", 
            $errno, 
            $errstr, 
            30,
            STREAM_CLIENT_CONNECT,
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ])
        );
        
        if (!$smtp) {
            return false;
        }
        
        // Set timeout
        stream_set_timeout($smtp, 30);
        
        // Function to read multi-line response
        function read_response($smtp) {
            $response = '';
            while ($line = fgets($smtp, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') {
                    break;
                }
            }
            return $response;
        }
        
        // Read server response
        $response = read_response($smtp);
        if (!$response) {
            return false;
        }
        
        // Send EHLO
        fputs($smtp, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        $response = read_response($smtp);
        if (!$response) {
            return false;
        }
        
        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^334/', $response)) {
            return false;
        }
        
        fputs($smtp, base64_encode($smtp_config['username']) . "\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^334/', $response)) {
            return false;
        }
        
        fputs($smtp, base64_encode($smtp_config['password']) . "\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^235/', $response)) {
            return false;
        }
        
        // Send email
        fputs($smtp, "MAIL FROM: <{$smtp_config['from_email']}>\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^250/', $response)) {
            return false;
        }
        
        // Format recipient email address properly
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        fputs($smtp, "RCPT TO: <" . $to . ">\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^250/', $response)) {
            return false;
        }
        
        fputs($smtp, "DATA\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^354/', $response)) {
            return false;
        }
        
        // Send headers and message
        fputs($smtp, "Subject: $subject\r\n");
        fputs($smtp, $headers . "\r\n");
        fputs($smtp, $message . "\r\n");
        fputs($smtp, ".\r\n");
        $response = read_response($smtp);
        if (!$response || !preg_match('/^250/', $response)) {
            return false;
        }
        
        // Close connection
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Function to send HTML email
function send_html_email($to, $subject, $html_message) {
    global $smtp_config;
    
    $headers = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
    $headers .= "Reply-To: {$smtp_config['reply_to']}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $result = send_email($to, $subject, $html_message, $headers);
    
    if (!$result) {
        return false;
    }
    
    return true;
}

// Function to generate password reset email content
function generate_reset_email($reset_link, $username) {
    // ... preserved for backward compatibility if needed, but we'll focus on OTP
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4f46e5; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9fafb; }
            .button { display: inline-block; background-color: #4f46e5; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Password Reset Request</h1>
            </div>
            <div class="content">
                <p>Hello $username,</p>
                <p>We received a request to reset your password. Click the button below to reset it:</p>
                <p style="text-align: center;">
                <a href="$reset_link" class="text button" style="color: white;">Reset Password</a>
                </p>
                <p>If you didn't request this, please ignore this email or contact support if you have concerns.</p>
                <p>This link will expire in 1 hour.</p>
                <p>If the button above doesn't work, copy and paste this URL into your browser:</p>
                <p style="word-break: break-all;">$reset_link</p>
            </div>
            <div class="footer">
                <p>This is an automated message, please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
    
    return $html;
}

// Function to generate OTP email content
function generate_otp_email($otp_code, $username) {
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
            .card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 32px; border: 1px solid #e5e7eb; }
            .header { text-align: center; margin-bottom: 32px; }
            .logo { background: #4f46e5; color: white; width: 48px; height: 48px; line-height: 48px; border-radius: 12px; display: inline-block; font-size: 24px; font-weight: bold; }
            .otp-box { background: #f3f4f6; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0; border: 2px dashed #4f46e5; }
            .otp-code { font-size: 32px; font-weight: 800; letter-spacing: 0.2em; color: #4f46e5; }
            .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #9ca3af; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header">
                    <div class="logo">A</div>
                    <h2 style="margin-top: 16px; color: #111827;">Verification Code</h2>
                </div>
                <p>Hello <strong>$username</strong>,</p>
                <p>We received a request to reset your password. Use the verification code below to continue. This code will expire in 10 minutes.</p>
                
                <div class="otp-box">
                    <div class="otp-code">$otp_code</div>
                </div>
                
                <p style="font-size: 14px; color: #6b7280;">If you did not request this code, you can safely ignore this email. Your password will remain unchanged.</p>
                
                <div class="footer">
                    <p>&copy; 2024 Authenticator. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
    
    return $html;
}

// Function to generate Expiry Reminder email content
function generate_reminder_email($domain_name, $client_name, $expiry_date) {
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
            .card { background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 32px; border: 1px solid #e5e7eb; }
            .header { text-align: center; margin-bottom: 32px; }
            .logo { background: #ef4444; color: white; width: 48px; height: 48px; line-height: 48px; border-radius: 12px; display: inline-block; font-size: 24px; font-weight: bold; }
            .warning-box { background: #fef2f2; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; border: 1px solid #fee2e2; }
            .domain-highlight { font-size: 20px; font-weight: 700; color: #b91c1c; margin: 10px 0; }
            .footer { text-align: center; margin-top: 32px; font-size: 12px; color: #9ca3af; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="header">
                    <div class="logo">!</div>
                    <h2 style="margin-top: 16px; color: #b91c1c;">License Expired</h2>
                </div>
                <p>Hello <strong>$client_name</strong>,</p>
                <p>This is an automated reminder that your license for the following domain has expired and requires immediate attention to restore service.</p>
                
                <div class="warning-box">
                    <div class="text-sm font-semibold text-red-600 uppercase tracking-wider">Expired Domain</div>
                    <div class="domain-highlight">$domain_name</div>
                    <div class="text-sm text-gray-500 italic">Expired on: $expiry_date</div>
                </div>
                
                <p>To avoid service interruption or permanent suspension, please contact your administrator or renew your subscription.</p>
                
                <p style="font-size: 14px; color: #6b7280; margin-top: 24px;">If you have already settled this, please ignore this notification.</p>
                
                <div class="footer">
                    <p>&copy; 2024 Authenticator. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
    
    return $html;
}
?>