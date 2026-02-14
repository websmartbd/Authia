<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Include the configuration file
require_once 'config/config.php';

// Set JSON header at the start
header("Content-Type: application/json");

// Rate limiting for API (30 requests per minute per IP)
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!RateLimiter::check($client_ip, 'api_call', 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// Check if domain and API key are provided via GET
$domain = InputValidator::sanitizeString($_GET['domain'] ?? '');
$apiKey = InputValidator::sanitizeString($_GET['key'] ?? '');

// 1. Validate that both parameters are present
if (empty($domain) || empty($apiKey)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing domain or API key']);
    exit;
}

// 2. Establish database connection
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// 3. Verify the Domain existence first
$sql = "SELECT id, domain, active, message, `delete`, license_type, expiry_date FROM domains WHERE domain = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $domain);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$domain_data = mysqli_fetch_assoc($result);

if (!$domain_data) {
    mysqli_close($conn);
    echo json_encode(['status' => 'error', 'message' => "Domain not found"]);
    exit;
}

// 4. Verify the API Key for this specific domain
$sql = "SELECT api_key FROM licenses WHERE domain_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $domain_data['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$license_row = mysqli_fetch_assoc($result);

if (!$license_row || !hash_equals($license_row['api_key'], $apiKey)) {
    mysqli_close($conn);
    echo json_encode(['status' => 'error', 'message' => "Invalid API key"]);
    exit;
}

// 5. Process data (Expiration check, etc.)
$active = (int)$domain_data['active'];
$message = $domain_data['message'];
$expiry_date = $domain_data['expiry_date'];

if ($domain_data['license_type'] !== 'lifetime' && !empty($expiry_date)) {
    if (date('Y-m-d') > $expiry_date) {
        $active = 0; // Mark as inactive if expired
        $message = "License Expired on " . $expiry_date;
    }
}

// 6. Return JSON response
echo json_encode([
    'status' => 'success',
    'data' => [
        'domain' => $domain_data['domain'],
        'active' => $active,
        'message' => $message,
        'delete' => $domain_data['delete'],
        'license_type' => $domain_data['license_type'],
        'expiry_date' => $expiry_date
    ]
]);

mysqli_close($conn);
exit;
?>
