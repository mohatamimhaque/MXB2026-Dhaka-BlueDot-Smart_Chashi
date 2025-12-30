# 🚀 SmartCashi - Installation & Setup Guide

This guide will help you install and configure SmartCashi on a new hosting environment or XAMPP server.

---

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Checklist](#pre-installation-checklist)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Configuration](#configuration)
6. [File Permissions](#file-permissions)
7. [Testing Installation](#testing-installation)
8. [Post-Installation Steps](#post-installation-steps)
9. [Troubleshooting](#troubleshooting)
10. [Production Deployment](#production-deployment)

---

## 💻 System Requirements

### Minimum Requirements
- **Web Server**: Apache 2.4+ with mod_rewrite enabled
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.7 or higher (8.0+ recommended)
- **Storage**: 2GB minimum (5GB+ recommended for production)
- **RAM**: 512MB minimum (2GB+ recommended)

### Required PHP Extensions
```
- pdo
- pdo_mysql
- mysqli
- mbstring
- json
- curl
- gd (for image processing)
- openssl
- zip
- xml
```

### Recommended Server Configuration
- **PHP Memory Limit**: 256M or higher
- **Upload Max Size**: 50M
- **Post Max Size**: 50M
- **Max Execution Time**: 300 seconds
- **SSL Certificate**: Required for production

---

## ✅ Pre-Installation Checklist

Before starting, ensure you have:

- [ ] Server/XAMPP with Apache, PHP 7.4+, MySQL 8.0+
- [ ] Database credentials (host, username, password)
- [ ] FTP/SSH access to server (for remote hosting)
- [ ] Email account for SMTP (Gmail recommended)
- [ ] Domain name (optional, for production)
- [ ] SSL certificate (optional, for HTTPS)
- [ ] Backup of existing data (if upgrading)

---

## 📦 Installation Steps

### Option 1: Fresh XAMPP Installation (Windows)

#### Step 1: Download and Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install XAMPP to `C:\xampp\` (default location)
3. Start Apache and MySQL from XAMPP Control Panel

#### Step 2: Download SmartCashi Files
1. Download the SmartCashi project files
2. Extract to `C:\xampp\htdocs\smartcashi\`

Your directory structure should look like:
```
C:\xampp\htdocs\smartcashi\
├── admin-secure/
├── agent/
├── ajax/
├── api/
├── config/
├── layouts/
├── pages/
├── public/
├── index.php
├── README.md
└── SETUP.md (this file)
```

#### Step 3: Enable Apache mod_rewrite
1. Open `C:\xampp\apache\conf\httpd.conf`
2. Find the line: `#LoadModule rewrite_module modules/mod_rewrite.so`
3. Remove the `#` to uncomment it: `LoadModule rewrite_module modules/mod_rewrite.so`
4. Find all instances of `AllowOverride None` and change to `AllowOverride All`
5. Save the file and restart Apache

### Option 2: Linux Server Installation

#### Step 1: Install LAMP Stack
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache
sudo apt install apache2 -y

# Install MySQL
sudo apt install mysql-server -y

# Install PHP and extensions
sudo apt install php php-mysql php-curl php-gd php-mbstring php-xml php-zip -y

# Enable mod_rewrite
sudo a2enmod rewrite

# Restart Apache
sudo systemctl restart apache2
```

#### Step 2: Upload SmartCashi Files
```bash
# Navigate to web root
cd /var/www/html

# Clone or upload project files
sudo mkdir smartcashi
cd smartcashi

# Upload files via FTP/SCP or clone from repository
# Set proper ownership
sudo chown -R www-data:www-data /var/www/html/smartcashi
```

### Option 3: Shared Hosting (cPanel)

#### Step 1: Access cPanel
1. Login to your hosting cPanel
2. Navigate to File Manager
3. Go to `public_html` directory

#### Step 2: Upload Files
1. Upload SmartCashi ZIP file
2. Extract files to `public_html/smartcashi/` or main directory
3. Ensure proper file structure is maintained

#### Step 3: Create Database via phpMyAdmin
1. Go to cPanel → MySQL Databases
2. Create new database: `smartcashi_db`
3. Create new MySQL user with password
4. Grant ALL PRIVILEGES to user on database

---

## 🗄️ Database Setup

### Step 1: Access phpMyAdmin
- **XAMPP**: Visit `http://localhost/phpmyadmin/`
- **Linux**: Visit `http://your-server-ip/phpmyadmin/`
- **cPanel**: Access from cPanel dashboard

### Step 2: Create Database
1. Click "New" in the left sidebar
2. Database name: `smartcashi_db`
3. Collation: `utf8mb4_unicode_ci`
4. Click "Create"

### Step 3: Import Database Schema
1. Select `smartcashi_db` from left sidebar
2. Click "Import" tab
3. Click "Choose File" and select `smartcashi_db.sql` from project root
4. Click "Go" at the bottom
5. Wait for import to complete (may take 1-2 minutes)

### Step 4: Verify Database Tables
After import, you should see **35+ tables** including:
- `users`
- `farmer_profiles`
- `crops`
- `community_posts`
- `marketplace_products`
- `admin_activity_logs`
- `admin_sessions`
- `weather_data`
- And many more...

### Step 5: Create Admin User
Run this SQL query to create your first admin account:

```sql
-- Replace values with your desired admin credentials
INSERT INTO `users` (
    `email`, 
    `phone`, 
    `password_hash`, 
    `first_name`, 
    `last_name`, 
    `role`, 
    `is_active`, 
    `is_verified`, 
    `created_at`
) VALUES (
    'admin@smartcashi.com',           -- Change email
    '01700000000',                     -- Change phone
    '$2y$10$xBCPU/lz302alc3LrmAzkOm2GRFZxG1jPmbiECj2av5Ki2aI0SZQC',  -- Password: admin123
    'Admin',                           -- First name
    'User',                            -- Last name
    'admin',                           -- Role (DO NOT CHANGE)
    1,                                 -- Active
    1,                                 -- Verified
    NOW()
);
```

**Default Password**: `admin123` (⚠️ **CHANGE IMMEDIATELY after first login!**)

---

## ⚙️ Configuration

### Step 1: Database Configuration
Edit `config/config.php` with your database credentials:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');           // Change if using remote database
define('DB_PORT', 3306);                  // Default MySQL port
define('DB_NAME', 'smartcashi_db');       // Your database name
define('DB_USER', 'root');                // Your MySQL username
define('DB_PASS', '');                    // Your MySQL password (XAMPP default is empty)
```

### Step 2: Application Settings
Update the base URL in `config/config.php`:

```php
// Change based on your installation
$base_url = "http://localhost/smartcashi/";  // For XAMPP local
// OR
$base_url = "https://yourdomain.com/";       // For production
```

### Step 3: Email Configuration (SMTP)
Configure email settings for notifications:

```php
// Email Configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');      // Your Gmail address
define('SMTP_PASSWORD', 'your-app-password');         // Gmail App Password
define('SMTP_FROM_NAME', 'Smart Chashi');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
```

**To get Gmail App Password:**
1. Go to Google Account → Security
2. Enable 2-Step Verification
3. Go to App Passwords
4. Generate password for "Mail"
5. Use this 16-character password in config

### Step 4: API Configuration (Optional but Recommended)
The following APIs are already configured with free services:

```php
// Weather API (No key required - Free)
define('OPEN_METEO_API', 'https://api.open-meteo.com/v1/forecast');

// AI Chat API (Free tier - Get your key from https://console.groq.com)
define('GROQ_API_KEY', 'your_groq_api_key_here');  // Optional
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
```

### Step 5: Environment Settings
```php
define('APP_ENV', 'development');  // Change to 'production' when going live
define('APP_DEBUG', true);         // Set to false in production
```

---

## 📁 File Permissions

### For Windows (XAMPP)
No special permissions needed. Ensure the following directories exist:

```powershell
# Run in PowerShell from project root
New-Item -ItemType Directory -Force -Path "public/uploads/profiles"
New-Item -ItemType Directory -Force -Path "public/uploads/products"
New-Item -ItemType Directory -Force -Path "public/uploads/community"
New-Item -ItemType Directory -Force -Path "public/uploads/reviews"
New-Item -ItemType Directory -Force -Path "backups"
New-Item -ItemType Directory -Force -Path "reports"
```

### For Linux Server
Set proper permissions:

```bash
# Navigate to project directory
cd /var/www/html/smartcashi

# Set directory ownership
sudo chown -R www-data:www-data .

# Set directory permissions (755 for directories, 644 for files)
sudo find . -type d -exec chmod 755 {} \;
sudo find . -type f -exec chmod 644 {} \;

# Writable directories
sudo chmod -R 775 public/uploads/
sudo chmod -R 775 backups/
sudo chmod -R 775 reports/

# Protect config files
sudo chmod 640 config/config.php
```

---

## 🧪 Testing Installation

### Step 1: Access the Application
Open your browser and visit:
- **XAMPP Local**: `http://localhost/smartcashi/`
- **Server**: `http://your-domain.com/` or `http://server-ip/smartcashi/`

### Step 2: Test Main Pages
✅ Homepage should load with:
- SmartCashi logo and navigation
- Login/Register buttons
- Platform statistics

### Step 3: Test User Login
1. Click "Login" button
2. Use default credentials:
   - **Email**: `admin@smartcashi.com`
   - **Password**: `admin123`
3. Should redirect to home/dashboard

### Step 4: Test Admin Panel
1. Visit: `http://localhost/smartcashi/?page=admin-login`
2. Login with admin credentials
3. Should see admin dashboard with:
   - User statistics
   - Activity logs
   - System monitoring

### Step 5: Test Database Connection
If you see any of these errors:
- ❌ "Database Connection Error" → Check `config/config.php` credentials
- ❌ "Table doesn't exist" → Re-import `smartcashi_db.sql`
- ❌ "Page not found" → Enable Apache mod_rewrite

---

## 🔒 Post-Installation Steps

### 1. Change Default Admin Password
⚠️ **CRITICAL SECURITY STEP**

1. Login to admin panel
2. Go to Admin Dashboard → Settings → Admin Profile
3. Change password immediately
4. Use a strong password (12+ characters, mixed case, numbers, symbols)

### 2. Configure Security Settings
1. Admin Panel → Security
2. Enable 2FA (Two-Factor Authentication)
3. Configure IP whitelisting if needed
4. Review and configure trusted devices

### 3. Setup Automated Backups
1. Admin Panel → Backup & Recovery
2. Configure automatic backup schedule
3. Test backup creation
4. Verify backup files in `/backups/` directory

### 4. Create Test Users
Create test accounts for each role:
- Farmer account
- Agricultural Officer account
- Regular admin account (don't use super admin for daily tasks)

### 5. Test All Features
✅ User registration and login
✅ Profile management and avatar upload
✅ Crop management (add, edit, delete)
✅ Community posts (create, like, comment)
✅ Marketplace product listing
✅ Admin user management
✅ Backup and restore functionality
✅ Report generation

---

## 🐛 Troubleshooting

### Issue: Apache won't start in XAMPP
**Solution:**
- Check if port 80 is in use: `netstat -ano | findstr :80`
- Stop Skype/IIS/other services using port 80
- Or change Apache port in `httpd.conf`

### Issue: MySQL won't start in XAMPP
**Solution:**
- Check if port 3306 is in use
- Stop other MySQL services
- Check error logs: `C:\xampp\mysql\data\mysql_error.log`

### Issue: "Database Connection Error"
**Solutions:**
1. Verify MySQL is running
2. Check credentials in `config/config.php`
3. Test connection via phpMyAdmin
4. Ensure database `smartcashi_db` exists

### Issue: "403 Forbidden" or "404 Not Found"
**Solutions:**
1. Enable Apache mod_rewrite:
   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   ```
2. Change `AllowOverride None` to `AllowOverride All` in `httpd.conf`
3. Restart Apache
4. Verify `.htaccess` file exists in project root

### Issue: Blank white page
**Solutions:**
1. Enable error display in PHP:
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
2. Check Apache error logs
3. Check file permissions
4. Verify all required PHP extensions are enabled

### Issue: Images/Uploads not working
**Solutions:**
1. Check directory exists: `public/uploads/`
2. Verify write permissions (775 on Linux)
3. Check PHP upload settings:
   ```ini
   upload_max_filesize = 50M
   post_max_size = 50M
   ```
4. Restart Apache after changes

### Issue: Admin panel not loading
**Solutions:**
1. Verify URL: `http://localhost/smartcashi/?page=admin-login`
2. Check if admin user exists in database
3. Clear browser cache and cookies
4. Check session configuration in `config/config.php`

### Issue: Email notifications not sending
**Solutions:**
1. Verify SMTP credentials in `config/config.php`
2. Enable "Less secure app access" in Gmail (or use App Password)
3. Check if PHPMailer files exist in `ajax/PHPMailer/`
4. Test email function separately

### Issue: "Headers already sent" error
**Solutions:**
1. Remove any whitespace before `<?php` in PHP files
2. Save files with UTF-8 encoding (no BOM)
3. Check for echo statements before `session_start()`

---

## 🌐 Production Deployment

### Pre-Deployment Checklist

- [ ] Change all default passwords
- [ ] Set `APP_ENV` to `production`
- [ ] Set `APP_DEBUG` to `false`
- [ ] Configure SSL certificate (HTTPS)
- [ ] Update `$base_url` to production domain
- [ ] Setup automated backups
- [ ] Configure proper email settings
- [ ] Enable 2FA for all admin accounts
- [ ] Review and configure security settings
- [ ] Setup monitoring and alerts
- [ ] Optimize database (add indexes if needed)
- [ ] Test all critical features
- [ ] Create disaster recovery plan

### Production Configuration

Edit `config/config.php`:

```php
define('APP_ENV', 'production');
define('APP_DEBUG', false);  // Hide error messages

$base_url = "https://yourdomain.com/";  // Use HTTPS

// Secure session settings
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'secure' => true,      // Require HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
```

### SSL/HTTPS Setup (cPanel)
1. cPanel → SSL/TLS Status
2. Run AutoSSL or install Let's Encrypt
3. Update `.htaccess` to force HTTPS:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Performance Optimization

#### Enable PHP OPcache
Edit `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

#### Enable Gzip Compression
Add to `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

#### Browser Caching
Already configured in `.htaccess`:
```apache
<FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|svg|webp)$">
    Header set Cache-Control "max-age=31536000, public"
</FilesMatch>
```

### Monitoring Setup

1. **Database Monitoring**: Setup MySQL slow query log
2. **Application Logs**: Check admin activity logs regularly
3. **Server Monitoring**: Use tools like New Relic, Datadog
4. **Uptime Monitoring**: UptimeRobot, Pingdom
5. **Security Scanning**: Wordfence, Sucuri

---

## 📞 Support & Resources

### Documentation
- Main README: [README.md](README.md)
- API Documentation: Check `/api/` directory
- Database Schema: Import `smartcashi_db.sql` to see structure

### Getting Help
- Check logs: `/reports/` and Apache error logs
- Review admin activity logs in database
- Enable debug mode temporarily to see errors

### Version Information
- **Current Version**: 1.0.0
- **PHP Version Required**: 7.4+
- **MySQL Version Required**: 5.7+
- **Last Updated**: December 30, 2025

---

## ✅ Installation Complete!

Once all steps are completed, you should have a fully functional SmartCashi installation.

### Next Steps:
1. ✅ Login as admin and change password
2. ✅ Configure all settings via admin panel
3. ✅ Create test users and verify functionality
4. ✅ Setup automated backups
5. ✅ Review security settings
6. ✅ Customize branding (optional)
7. ✅ Start using SmartCashi!

### Default URLs:
- **User Frontend**: `http://localhost/smartcashi/`
- **Admin Panel**: `http://localhost/smartcashi/?page=admin-login`
- **AI Agent**: `http://localhost/smartcashi/agent/`

### Default Credentials:
- **Email**: admin@smartcashi.com
- **Password**: admin123
- ⚠️ **Change immediately after first login!**

---

**Thank you for choosing SmartCashi! 🌾**

For questions or issues, review the troubleshooting section or check the main [README.md](README.md) for detailed documentation.
