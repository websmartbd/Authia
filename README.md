# Authia - Enterprise PHP Licensing Framework


**Authia** is a lightweight, high-performance, and ultra-secure licensing framework for PHP developers. It provides a complete solution for managing, generating, and validating software licenses with an emphasis on modern security standards and developer experience.

## 🚀 Vision
Protect your intellectual property without the bloat. Authia offers a "Set it and Forget it" approach to licensing, allowing you to integrate license validation into your PHP applications in minutes.

---

## ✨ Key Features

### 🛡️ Hardened Security
- **Argon2id Hashing:** Uses industry-standard cryptography to protect API keys.
- **CSRF Protection:** Secure form submissions across the entire dashboard.
- **Rate Limiting:** Built-in protection against brute-force attacks and license spamming.
- **Secure Sessions:** HttpOnly and SameSite cookie policies to prevent session hijacking.
- **Anti-Enumeration:** Masked error messages to prevent hackers from identifying registered domains.

### ⚡ Developer Experience
- **One-Click Generation:** Sleek AJAX-powered portal for generating and recovering keys.
- **Developer API:** A clean JSON endpoint for remote license validation.
- **Mobile Responsive:** A high-density, professional dashboard that works on any device.
- **Easy Integration:** Simple cURL-based validation that can be dropped into any project.

### 📊 Admin Control
- **Domain Management:** Activate, deactivate, or delete licenses with a single click.
- **SMTP Integration:** Automated email notifications for configuration and recovery.
- **Detailed Analytics:** Monitor total, active, and expired domains at a glance.

---

## 🛠️ Tech Stack
- **Backend:** PHP 8.1+ (Procedural & OOP Mix)
- **Database:** MariaDB / MySQL
- **CSS:** Tailwind CSS (Modern, high-density layout)
- **Icons:** Font Awesome 6
- **Typography:** Inter & JetBrains Mono

---

## � Requirements
- **PHP:** 8.1 or higher
- **Database:** MySQL 5.7+ or MariaDB 10.3+
- **Extensions:** `mysqli`, `openssl`, `json`, `session`, `hash`
- **Web Server:** Apache (with `mod_rewrite` recommended) or Nginx

## �📥 Installation

Authia features a built-in **3-Step GUI Installer** to get you up and running in minutes.

1. **Clone the Repository:**
   ```bash
   git clone https://github.com/websmartbd/Authentication-Manager.git
   ```

2. **Upload & Permissions:**
   - Upload all files to your server's root or a subdirectory.
   - Ensure the `config/` directory is writable by the web server.

3. **Run the GUI Installer:**
   Navigate to `install.php` (e.g., `https://your-site.com/install.php`) and follow the professional setup wizard:
   - **Step 0: API Verification:** Enter your master API key to authorize the installation. Get your feww key from [authia.hs.vc](https://authia.hs.vc/).
   - **Step 1: Database Configuration:** Provide your MySQL/MariaDB credentials. Authia will automatically create the configuration file and import the necessary database schema.
   - **Step 2: Admin Setup:** Create your secure administrator account. Authia uses **Argon2id** hashing for maximum password security.

4. **Post-Installation:**
   > ⚠️ **CRITICAL SECURITY STEP:** Delete the `install.php` file from your server immediately after successful installation to prevent unauthorized access to your configuration.

---

## 📡 API Documentation

### Validate a License
Perform a POST request to your validation endpoint:

**Endpoint:** `https://your-domain.com/api`

**Payload (JSON):**
```json
{
  "key": "bm-7a9s8d7f6g...",
  "domain": "client-site.com"
}
```

**Response (200 OK):**
```json
{
  "status": "success",
  "data": {
    "domain": "example.com",
    "active": 1,
    "message": "Domain is active",
    "delete": "no",
    "license_type": "yearly",
    "expiry_date": "2026-12-31"
  }
}
```

---

## 📄 License
This project is licensed under the **MIT License**. You are free to use, modify, and distribute it for personal and commercial projects.

---

## ❤️ Credits
Developed with love by **B.M Shifat**.

- **Facebook:** [bmshifat0](https://www.facebook.com/bmshifat0)
- **GitHub:** [websmartbd](https://github.com/websmartbd)

---
*Security is a journey, not a destination. Maintain your Authia installation regularly.*
