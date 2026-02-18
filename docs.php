<?php
// Enable security framework
define('SECURE_ACCESS', true);
require_once 'config/security.php';

// Initialize secure session
SessionSecurity::init();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#10b981',
                        danger: '#ef4444',
                        warning: '#f59e0b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        .sticky-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.9);
        }
        @media (max-width: 768px) {
            .mobile-menu {
                display: none;
            }
            .mobile-menu.active {
                display: block;
            }
        }
    </style>
    <link rel="icon" type="image/png" href="https://authia.hs.vc/security.png">
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased min-h-screen">
    <!-- Navigation -->
    <nav class="sticky-nav border-b border-gray-200 mb-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-primary">API Docs</h1>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="#overview" class="text-gray-600 hover:text-primary">Overview</a>
                    <a href="#authentication" class="text-gray-600 hover:text-primary">Authentication</a>
                    <a href="#endpoints" class="text-gray-600 hover:text-primary">Endpoints</a>
                    <a href="#examples" class="text-gray-600 hover:text-primary">Examples</a>
                </div>
                <button class="md:hidden text-gray-600" onclick="toggleMobileMenu()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                    </svg>
                </button>
            </div>
            <!-- Mobile Menu -->
            <div class="mobile-menu md:hidden py-4 border-t border-gray-200">
                <div class="flex flex-col space-y-4">
                    <a href="#overview" class="text-gray-600 hover:text-primary">Overview</a>
                    <a href="#authentication" class="text-gray-600 hover:text-primary">Authentication</a>
                    <a href="#endpoints" class="text-gray-600 hover:text-primary">Endpoints</a>
                    <a href="#examples" class="text-gray-600 hover:text-primary">Examples</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <header class="mb-12 text-center">
            <h1 class="text-5xl font-bold text-gray-900 mb-4">Domain Validation API</h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Complete guide to using the domain validation API for verifying and managing domain access.</p>
        </header>
        
        <main class="space-y-16">
            <section id="overview" class="scroll-mt-20">
                <div class="flex items-center space-x-3 mb-6">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h2 class="text-3xl font-bold text-gray-900">Overview</h2>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-8 space-y-6">
                    <p class="text-lg text-gray-700 leading-relaxed">
                        This API allows you to validate domains and retrieve their status information. It is designed to be used by client applications to verify if a domain is allowed to use specific functionality, and to check if the domain is active or flagged for deletion.
                    </p>
                    
                    <div class="bg-amber-50 border-l-4 border-warning p-6 rounded-r-xl">
                        <p class="flex items-start text-lg">
                            <svg class="h-6 w-6 text-warning mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><strong class="font-semibold">Note:</strong> This API requires authentication via an API key that must be generated for each domain through the admin panel.</span>
                        </p>
                    </div>
                </div>
            </section>
            
            <section id="authentication" class="scroll-mt-20">
                <div class="flex items-center space-x-3 mb-6">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <h2 class="text-3xl font-bold text-gray-900">Authentication</h2>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-8">
                    <p class="text-lg text-gray-700 leading-relaxed">
                        All API requests require an API key that is associated with a specific domain. API keys can be generated in the admin panel under the API management section.
                    </p>
                </div>
            </section>
            
            <section id="endpoints" class="scroll-mt-20">
                <div class="flex items-center space-x-3 mb-6">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h2 class="text-3xl font-bold text-gray-900">Endpoints</h2>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-8 border-l-4 border-primary space-y-8">
                    <div class="space-y-4">
                        <h3 class="text-2xl font-semibold text-primary">GET /api</h3>
                        <p class="text-lg text-gray-700">Validates a domain and returns its status information.</p>
                    </div>
                    
                    <div class="space-y-4">
                        <h4 class="text-xl font-semibold text-gray-800">Parameters</h4>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">domain</span>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-sm font-medium rounded">required</span>
                                </div>
                                <p class="text-gray-700">The domain to validate.</p>
                            </div>
                            <div class="bg-gray-50 p-6 rounded-lg">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">key</span>
                                    <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-sm font-medium rounded">required</span>
                                </div>
                                <p class="text-gray-700">The API key associated with the domain.</p>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="space-y-6">
                        <h4 class="text-xl font-semibold text-gray-800">Response</h4>
                        <p class="text-lg text-gray-700">The API returns a JSON response with the following structure:</p>
                        
                        <div class="space-y-6">
                            <div class="bg-green-50 border-l-4 border-secondary p-6 rounded-r-xl">
                                <h5 class="font-semibold text-gray-900 mb-3">Success Response (200 OK)</h5>
                                <pre class="bg-[#282c34] text-white p-6 rounded-lg font-mono text-sm overflow-x-auto">{
    "status": "success",
    "data": {
        "domain": "example.com",
        "active": 1,
        "message": "Domain is active",
        "delete": "no",
        "license_type": "yearly",
        "expiry_date": "2026-12-31"
    }
}</pre>
                            </div>
                            
                            <div class="bg-red-50 border-l-4 border-danger p-6 rounded-r-xl">
                                <h5 class="font-semibold text-gray-900 mb-3">Error Response (200 OK with error status)</h5>
                                <pre class="bg-[#282c34] text-white p-6 rounded-lg font-mono text-sm overflow-x-auto">{
    "status": "error",
    "message": "Domain not found"
}</pre>
                            </div>
                        </div>
                    </div>

            </section>
            
            <section id="examples" class="scroll-mt-20">
                <div class="flex items-center space-x-3 mb-6">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                    <h2 class="text-3xl font-bold text-gray-900">Usage Examples</h2>
                </div>
                
                <div class="space-y-4">
                    <!-- PHP Example -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <button class="w-full flex items-center justify-between p-4 text-left" onclick="toggleAccordion('php')">
                            <h3 class="text-xl font-semibold text-gray-900">PHP Example</h3>
                            <svg id="php-icon" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="php-content" class="hidden border-t border-gray-200">
                            <pre class="bg-[#282c34] text-white p-6 font-mono text-sm overflow-x-auto">&lt;?php
function checkDomainStatus($apiKey, $targetDomain) {
    $apiUrl = "https://your-domain.com/api.php?domain=" . urlencode($targetDomain) . "&key=" . urlencode($apiKey);
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if ($data['status'] === 'success') {
            $domainData = $data['data'];
            
            if ($domainData['active'] == 1) {
                return true; // Success! Domain is valid and active
            } else {
                // Return the specific message from the server (e.g. "License Expired")
                return $domainData['message']; 
            }
        }
    }
    return false; // General failure
}

// Usage
$apiKey = "YOUR_API_KEY";
$myDomain = $_SERVER['HTTP_HOST'];

$status = checkDomainStatus($apiKey, $myDomain);
if ($status === true) {
    // App continues normally
} else {
    // Show error message and stop
    die($status ?: "Access Denied");
}
?&gt;</pre>
                    </div>
                    
                    <!-- JavaScript Example -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <button class="w-full flex items-center justify-between p-4 text-left" onclick="toggleAccordion('js')">
                            <h3 class="text-xl font-semibold text-gray-900">JavaScript Example</h3>
                            <svg id="js-icon" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="js-content" class="hidden border-t border-gray-200">
                            <pre class="bg-[#282c34] text-white p-6 font-mono text-sm overflow-x-auto">const checkDomain = async () => {
    const baseApiUrl = 'http://ecample.com/api';
    const apiKey = 'your_api_key_here';
    const domain = window.location.hostname;
    
    try {
        const response = await fetch(
            `${baseApiUrl}?domain=${encodeURIComponent(domain)}
            &key=${encodeURIComponent(apiKey)}`
        );
        const data = await response.json();
        
        if (data.status === 'success') {
            const domainData = data.data;
            
            if (domainData.active === 1) {
                if (domainData.delete === 'no') {
                    // Domain is active and not flagged for deletion
                    return true;
                } else {
                    // Domain is active but flagged for deletion
                    // Handle deletion warning
                    showDeletionWarning();
                    return 'delete';
                }
            } else {
                // Domain is inactive
                showErrorMessage(domainData.message 
                    || 'Access Denied');
                return false;
            }
        } else {
            // API returned an error
            showErrorMessage('Domain validation failed');
            return false;
        }
    } catch (error) {
        // Network error or JSON parsing error
        showErrorMessage('Unable to verify domain '
            + 'at the moment');
        return false;
    }
};

function showErrorMessage(message) {
    document.body.innerHTML = `
        <p class="text-red-600 text-2xl font-semibold 
                  text-center">${message}</p>`;
}

function showDeletionWarning() {
    // Implement deletion warning UI
    // See full example in sample.txt for deletion handling
}

// Check domain status
checkDomain().then(status => {
    if (status === true) {
        // Initialize your application
        initApp();
    }
});</pre>
                    </div>
                    
                    <!-- Node.js Example -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <button class="w-full flex items-center justify-between p-4 text-left" onclick="toggleAccordion('node')">
                            <h3 class="text-xl font-semibold text-gray-900">Node.js Example</h3>
                            <svg id="node-icon" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="node-content" class="hidden border-t border-gray-200">
                            <pre class="bg-[#282c34] text-white p-6 font-mono text-sm overflow-x-auto">const axios = require('axios');

async function checkDomain() {
    const baseApiUrl = 'http://ecample.com/api';
    const apiKey = 'your_api_key_here';
    const domain = process.env.DOMAIN;

    try {
        const response = await axios.get(baseApiUrl, {
            params: {
                domain: domain,
                key: apiKey
            }
        });

        const data = response.data;
        
        if (data.status === 'success') {
            const domainData = data.data;
            
            if (domainData.active === 1) {
                if (domainData.delete === 'no') {
                    return true;
                } else {
                    console.warn('Domain is flagged for deletion');
                    return 'delete';
                }
            } else {
                console.error(domainData.message || 'Access Denied');
                return false;
            }
        } else {
            console.error('Domain validation failed');
            return false;
        }
    } catch (error) {
        console.error('Unable to verify domain:', error.message);
        return false;
    }
}

// Usage
checkDomain().then(status => {
    if (status === true) {
        // Initialize your application
        initApp();
    }
});</pre>
                    </div>
                    
                    <!-- Python Example -->
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <button class="w-full flex items-center justify-between p-4 text-left" onclick="toggleAccordion('python')">
                            <h3 class="text-xl font-semibold text-gray-900">Python Example</h3>
                            <svg id="python-icon" class="w-6 h-6 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div id="python-content" class="hidden border-t border-gray-200">
                            <pre class="bg-[#282c34] text-white p-6 font-mono text-sm overflow-x-auto">import requests
import os

def check_domain():
    base_api_url = 'http://ecample.com/api'
    api_key = 'your_api_key_here'
    domain = os.getenv('DOMAIN')

    try:
        response = requests.get(
            base_api_url,
            params={
                'domain': domain,
                'key': api_key
            }
        )
        data = response.json()

        if data['status'] == 'success':
            domain_data = data['data']

            if domain_data['active'] == 1:
                if domain_data['delete'] == 'no':
                    return True
                else:
                    print('Warning: Domain is flagged for deletion')
                    return 'delete'
            else:
                print(f"Error: {domain_data.get('message', 'Access Denied')}")
                return False
        else:
            print('Error: Domain validation failed')
            return False

    except Exception as e:
        print(f'Error: Unable to verify domain - {str(e)}')
        return False

# Usage
if __name__ == '__main__':
    status = check_domain()
    if status is True:
        # Initialize your application
        init_app()</pre>
                    </div>
                    </div>
                </div>
            </section>
            
            <section class="space-y-8">
                <div class="grid md:grid-cols-2 gap-8">
                    <div class="bg-white rounded-xl shadow-sm p-8">
                        <div class="flex items-center space-x-3 mb-6">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h2 class="text-2xl font-bold text-gray-900">Security Considerations</h2>
                        </div>
                        <ul class="list-disc pl-5 space-y-3 text-gray-700">
                            <li>API keys should be kept confidential and not exposed in client-side code.</li>
                            <li>For production use, consider implementing rate limiting to prevent abuse.</li>
                            <li>Always validate and sanitize user input to prevent SQL injection and other attacks.</li>
                            <li>Use HTTPS to encrypt API requests and responses in production environments.</li>
                        </ul>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-8">
                        <div class="flex items-center space-x-3 mb-6">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h2 class="text-2xl font-bold text-gray-900">Error Handling</h2>
                        </div>
                        <ul class="list-disc pl-5 space-y-3 text-gray-700">
                            <li>Invalid or missing API keys result in a standardized JSON error response.</li>
                            <li>Domains not found in the validation database return a <code>status: error</code> response.</li>
                            <li>Database connection issues are handled with a 200 OK status but with an error message in the body for client safety.</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-amber-50 border-l-4 border-warning p-8 rounded-r-xl">
                    <div class="flex items-center space-x-3 mb-4">
                        <svg class="w-8 h-8 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-900">Implementation Notes</h2>
                    </div>
                    <p class="text-gray-800 mb-4">The sample implementation in <code class="bg-amber-100 px-2 py-1 rounded"><a href="sample.txt">sample.txt</a></code> demonstrates how to:</p>
                    <ul class="list-disc pl-5 space-y-2 text-gray-800">
                        <li>Robust validation using <strong>cURL</strong> with proper timeout handling.</li>
                        <li>Automated local domain detection using <code>HTTP_HOST</code>.</li>
                        <li><strong>Self-Protection Protocol:</strong> Demonstrates a countdown-based file removal for domains flagged/blacklisted (delete status 'yes').</li>
                        <li>Premium UI implementation for expiration and security alerts.</li>
                    </ul>
                </div>
            </section>
        </main>
        
        <footer class="mt-16 pt-8 border-t border-gray-200 text-center text-gray-600">
            <p>&copy; <?php echo date('Y'); ?> Domain Validation API. All rights reserved.</p>
        </footer>
    </div>

    <script>
        function toggleMobileMenu() {
            const mobileMenu = document.querySelector('.mobile-menu');
            mobileMenu.classList.toggle('active');
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                    // Close mobile menu if open
                    const mobileMenu = document.querySelector('.mobile-menu');
                    if (mobileMenu.classList.contains('active')) {
                        mobileMenu.classList.remove('active');
                    }
                }
            });
        });

        // Highlight current section in navigation
        const sections = document.querySelectorAll('section[id]');
        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= sectionTop - 180) {
                    current = section.getAttribute('id');
                }
            });

            document.querySelectorAll('nav a').forEach(link => {
                link.classList.remove('text-primary');
                if (link.getAttribute('href').slice(1) === current) {
                    link.classList.add('text-primary');
                }
            });
        });
    </script>
</body>
</html>

<style>
    .tab-btn.active {
        border-bottom-width: 2px;
    }
    .tab-content {
        transition: opacity 0.2s ease-in-out;
    }
    .tab-content.hidden {
        display: none;
    }
</style>

<script>
    function showTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active', 'border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-gray-500');
        });
        
        const activeTab = document.getElementById(`${tabName}-tab`);
        activeTab.classList.add('active', 'border-primary', 'text-primary');
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        
        // Update content visibility
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        document.getElementById(`${tabName}-content`).classList.remove('hidden');
    }
</script>

<script>
function toggleAccordion(id) {
    const content = document.getElementById(`${id}-content`);
    const icon = document.getElementById(`${id}-icon`);
    
    // Toggle content visibility
    content.classList.toggle('hidden');
    
    // Rotate icon
    if (content.classList.contains('hidden')) {
        icon.style.transform = 'rotate(0deg)';
    } else {
        icon.style.transform = 'rotate(180deg)';
    }
}
</script>