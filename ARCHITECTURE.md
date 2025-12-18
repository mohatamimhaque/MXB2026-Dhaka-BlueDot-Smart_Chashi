# Smart Chashi - PHP Frontend & Clean URL Architecture Update
**Date:** December 14, 2025  
**Update:** Frontend converted to PHP files with clean URL routing and file blob serving

---

## 📋 What Was Added

### 1. **README.md Updates**
   - Added "Frontend File Structure & Routing" section explaining PHP-based frontend
   - Added comprehensive ".htaccess Configuration" section with URL rewriting rules
   - Added "Clean URL Examples" comparison table
   - Added "File Blob URL Serving" documentation with security implementation
   - Updated Technology Stack table to include:
     - PHP files for server-side page rendering
     - Apache mod_rewrite for URL routing
     - File blob server for secure downloads
   - Updated "Installation" section with Apache setup and clean URL testing

### 2. **.htaccess File** (`/.htaccess`)
   - **Location:** Project root directory
   - **Purpose:** URL rewriting and routing configuration
   - **Key Features:**
     - Security rules: Blocks access to `.htaccess`, `.env`, `config/`, `app/`
     - Page routing: `/dashboard` → `index.php?page=dashboard`
     - API routing: `/api/login` → `api/handler.php?action=login`
     - File serving: `/files/abc123` → `public/api/file-blob.php?file=abc123`
     - Cache headers: 1-year cache for static assets
     - Gzip compression enabled
     - Security headers (X-Content-Type-Options, X-XSS-Protection)

### 3. **API Handler** (`/api/handler.php`)
   - **Purpose:** Central AJAX request handler for all API calls
   - **Features:**
     - Login/Register handlers with password hashing
     - Profile management (get/update)
     - Crop management (get/add)
     - Disease detection with image upload
     - JSON response format
     - CORS support for cross-origin requests
     - Session-based authentication (no JWT)
     - Error handling and validation

### 4. **File Blob Server** (`/public/api/file-blob.php`)
   - **Purpose:** Secure file download/streaming with permission checks
   - **Features:**
     - User authentication verification
     - Permission checks (users can only download their own files or public files)
     - File expiration support
     - Download count tracking
     - File access logging
     - Range request support (for video streaming)
     - Security headers to prevent unauthorized access
     - Proper MIME type handling
     - Path traversal protection

---

## 🗂️ Frontend File Structure

```
smart-chashi/
├── .htaccess                      ← URL rewriting rules
├── index.php                      ← Main entry point (routes all requests)
├── dashboard.php                  ← Farmer dashboard
├── crops.php                      ← Crop management
├── disease.php                    ← Disease detection
├── chat.php                       ← AI Chat interface
├── profile.php                    ← User profile
├── alerts.php                     ← Weather & alerts
├── marketplace.php                ← Marketplace
├── community.php                  ← Community forum
├── videos.php                     ← Video platform
├── admin-dashboard.php            ← Officer dashboard
│
├── public/
│   ├── css/
│   │   └── style.css             (Mobile-first CSS framework)
│   ├── js/
│   │   └── app.js                (jQuery AJAX communication)
│   ├── images/                   (Static images)
│   ├── uploads/                  (User-uploaded files)
│   │   ├── user_*/
│   │   ├── disease_images/
│   │   └── ...
│   └── api/
│       └── file-blob.php         ← Secure file serving
│
├── api/
│   └── handler.php               ← Central AJAX handler
│
├── app/
│   ├── models/
│   │   └── Database.php          (Database class)
│   ├── controllers/              (To be created)
│   ├── views/                    (To be created)
│   └── helpers/                  (To be created)
│
├── config/
│   └── config.php                (Database & app configuration)
│
├── database/
│   ├── migrations/               (SQL migration files)
│   └── seeds/                    (Sample data)
│
└── README.md                      (Project documentation)
```

---

## 🔄 Clean URL Examples

| **Old Query String URL** | **New Clean URL** | **Maps To** |
|---|---|---|
| `index.php?page=dashboard` | `/dashboard` | `index.php?page=dashboard` |
| `crops.php?id=5` | `/crops/5` | `index.php?page=crops&id=5` |
| `disease.php?crop_id=3` | `/disease/3` | `index.php?page=disease&id=3` |
| `profile.php?user_id=123` | `/profile/123` | `index.php?page=profile&id=123` |
| `api/handler.php?action=login` | `/api/login` | `api/handler.php?action=login` |
| `api/handler.php?action=register` | `/api/register` | `api/handler.php?action=register` |
| `upload/serve.php?id=abc123` | `/files/abc123` | `public/api/file-blob.php?file=abc123` |

---

## 📡 AJAX Communication Flow

### Old Approach (fetch API)
```javascript
fetch('/index.php?page=dashboard&action=login', {
  method: 'POST',
  body: JSON.stringify({email, password})
}).then(res => res.json())
```

### New Approach (jQuery AJAX via clean URLs)
```javascript
$.ajax({
  type: 'POST',
  url: '/api/login',           // Clean URL
  data: {email, password},
  dataType: 'json',
  success: function(response) {
    console.log(response.message);
  }
});
```

**Clean URL is rewritten to:** `api/handler.php?action=login`

---

## 🔐 Security Features

### 1. File Permission Checks
- Users can only download their own files
- Public files accessible to all authenticated users
- File expiration dates enforced
- Access logging for audit trails

### 2. URL Rewriting Security
- Sensitive config files blocked from direct access
- Only real files/directories served without rewrite
- .env files protected from web access
- API paths controlled through .htaccess

### 3. Password Security
- Passwords hashed using PHP's password_hash (BCRYPT)
- Minimum 8 character password requirement
- Sessions secure with HttpOnly flag
- CSRF token support (ready to implement)

### 4. Database Protection
- Prepared statements to prevent SQL injection
- Database class uses parameterized queries
- Password verification with password_verify()

---

## ⚙️ Apache Configuration Required

### Enable mod_rewrite
```bash
# Linux
sudo a2enmod rewrite

# Then restart Apache
sudo systemctl restart apache2
```

### .htaccess Permissions
```bash
chmod 644 .htaccess
```

### Virtual Host Configuration (Optional)
```apache
<VirtualHost *:80>
    DocumentRoot /var/www/smart-chashi
    ServerName chashi.local
    
    <Directory /var/www/smart-chashi>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## 📝 PHP File Template (index.php)

Each page file should follow this pattern:

```php
<?php
session_start();
require_once 'config/config.php';
require_once 'app/models/Database.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// Get page parameter if needed
$page = $_GET['page'] ?? 'dashboard';
$id = $_GET['id'] ?? null;

// Load page-specific controller/logic here
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Chashi - <?php echo ucfirst($page); ?></title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <!-- Content -->
    <!-- jQuery & App JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="/public/js/app.js"></script>
</body>
</html>
```

---

## 🚀 Next Steps

### 1. Create PHP Page Files
- [ ] Create `dashboard.php`
- [ ] Create `crops.php`
- [ ] Create `disease.php`
- [ ] Create `chat.php`
- [ ] Create `profile.php`
- [ ] Create `alerts.php`
- [ ] Create `marketplace.php`
- [ ] Create `community.php`
- [ ] Create `videos.php`
- [ ] Create `admin-dashboard.php`

### 2. Implement Database Migrations
- [ ] Create migration scripts for all 12 tables
- [ ] Add seed data
- [ ] Create migration runner

### 3. Add More API Handlers
- [ ] Weather data fetching
- [ ] Market price updates
- [ ] Chat message handling
- [ ] Fertilizer recommendations
- [ ] User profile updates

### 4. Testing & Debugging
- [ ] Test all clean URLs
- [ ] Verify file serving works
- [ ] Check permission system
- [ ] Test mobile responsiveness

---

## 📚 Documentation Files Created

1. **README.md** - Updated with:
   - Frontend File Structure section
   - .htaccess Configuration with examples
   - Clean URL Examples table
   - File Blob URL Serving section
   - Installation instructions for Apache
   - PHP Configuration details

2. **.htaccess** - Complete URL routing configuration with:
   - Security rules
   - Page routing
   - API routing
   - File blob serving
   - Caching headers
   - Compression settings

3. **api/handler.php** - AJAX request handler with:
   - Login/Register handlers
   - Profile management
   - Crop management
   - Disease detection
   - Proper JSON responses

4. **public/api/file-blob.php** - Secure file serving with:
   - Permission checks
   - File expiration
   - Download tracking
   - Access logging
   - Range request support

---

## ✅ Summary

**Smart Chashi is now configured with:**
- ✅ PHP-based frontend files (instead of pure HTML)
- ✅ Clean SEO-friendly URLs using Apache mod_rewrite
- ✅ Centralized AJAX handler for all API requests
- ✅ Secure file blob serving with permission checks
- ✅ Proper .htaccess configuration for routing and security
- ✅ Session-based authentication (no JWT)
- ✅ File-based caching (no Redis)
- ✅ jQuery AJAX communication pattern
- ✅ Comprehensive documentation in README

**Technology Stack:**
- Frontend: PHP + HTML5 + CSS3 + jQuery AJAX
- Backend: PHP 7.4+ with session-based auth
- Database: MySQL with 3NF normalization
- File Serving: Secure blob server with access logs
- URL Routing: Apache mod_rewrite via .htaccess
- Caching: File-based JSON + HTTP cache headers

---

**Status:** Ready for page file creation and development
**Version:** 1.0.0 - Clean URL Architecture Complete
