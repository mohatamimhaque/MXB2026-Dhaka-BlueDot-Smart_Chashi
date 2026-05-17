# Setup & Installation Guide

> **Smart Chashi** — AI-powered agricultural platform for Bangladeshi farmers and agricultural officers.  
> This guide walks you from zero to a fully working local or production instance.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation Steps](#installation-steps)
3. [Database Setup](#database-setup)
4. [API Keys & Credentials](#api-keys--credentials)
5. [Email (SMTP) Configuration](#email-smtp-configuration)
6. [File Permissions](#file-permissions)
7. [Web Server Configuration](#web-server-configuration)
8. [First Run & Verification](#first-run--verification)
9. [User Roles & Access](#user-roles--access)
10. [Common Issues](#common-issues)
11. [Production Deployment Notes](#production-deployment-notes)

---

## Prerequisites

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| **PHP** | 8.0 | 8.2+ |
| **MySQL / MariaDB** | MySQL 8.0 or MariaDB 10.4+ | MariaDB 10.4+ (XAMPP default) |
| **Apache** | 2.4 | 2.4 (mod_rewrite enabled) |
| **XAMPP** | 8.0+ | Latest |
| **PHP Extensions** | `pdo_mysql`, `curl`, `mbstring`, `gd`, `json`, `fileinfo` | Same |
| **Disk Space** | 200 MB | 1 GB (for uploads) |
| **RAM** | 512 MB | 2 GB |

> The project was developed and tested against **MariaDB 10.4.32** (the MariaDB version bundled with XAMPP). The SQL dump uses MariaDB syntax. MySQL 8.0+ is also compatible.

> **Windows users**: XAMPP 8.2+ bundles all required PHP extensions. No extra configuration needed.

---

## Installation Steps

### Step 1 — Place the Project

Copy or clone the project into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\smartchashi\
```

The application auto-detects `BASE_URL` from `$_SERVER['HTTP_HOST']` and the folder path. The folder name (`smartchashi`) determines the URL prefix.

**Verify the folder structure:**
```
smartchashi/
├── config/
│   └── config.php          ← must exist before any other step
├── agent/
├── admin-secure/
├── shop/
├── pages/
├── .htaccess
└── index.php
```

---

### Step 2 — Enable Apache mod_rewrite

The application uses clean URLs via `.htaccess`. This requires `AllowOverride All` in Apache config.

**Edit:** `C:\xampp\apache\conf\httpd.conf`

Find the `<Directory>` block for htdocs and change `AllowOverride None` to:

```apache
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks Includes ExecCGI
    AllowOverride All
    Require all granted
</Directory>
```

Restart Apache after saving.

---

### Step 3 — Configure `config.php`

Open `config/config.php` and set your local values:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartcashi_db');
define('DB_USER', 'root');
define('DB_PASS', '');           // leave blank for default XAMPP

// Base URL (auto-detected; override only if needed)
define('BASE_URL', 'http://localhost/smartchashi');
```

---

## Database Setup

### Step 1 — Create the Database

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar
3. Name: `smartcashi_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**

### Step 2 — Import the Main Schema

In phpMyAdmin, select `smartcashi_db` → **Import** tab → choose file → Execute:

```
Database/smartcashi_db.sql      ← complete schema (80+ tables, seeded with sample data)
```

> This single file contains the full database including all tables, indexes, foreign keys, and seed data. It was generated from MariaDB 10.4.32.

### Step 3 — Run Migrations (if starting from an older dump)

If you have an older database dump (before the full consolidation), run these migration files in order:

| # | File | What It Creates |
|---|------|----------------|
| 1 | `agent/migration.sql` | `agent_conversations`, `agent_messages` |
| 2 | `agent/migration_v2.sql` | `agent_user_memory`, feedback column on messages |
| 3 | `learn-migration.sql` | `learn_categories`, `learn_content` and related tables |
| 4 | `learn-seed.sql` | Sample learning data |
| 5 | `ai-usage-migration.sql` | `ai_usage_logs`, `ai_providers` |
| 6 | `admin-notifications-migration.sql` | `admin_notifications` |
| 7 | `shop/Database/shop_tables.sql` | Shop core tables (`shop_cart`, `shop_orders`, `shop_order_items`, `general_users`) |
| 8 | `shop/Database/phase2_tables.sql` | `shop_conversations`, `shop_messages`, `product_reviews` |
| 9 | `shop/Database/migration_v2.sql` | Shop v2 features |
| 10 | `shop/Database/migration_v3.sql` | Shop v3 features |
| 11 | `shop/Database/migration_v4.sql` | `shop_settings`, `shop_otp_codes`, seller features |

> **Recommended:** Use the single `Database/smartcashi_db.sql` import instead of running migrations manually. The migrations are only needed when upgrading an existing installation.

> **Important:** If running migrations manually, execute them in the exact order above. Later migrations reference tables created by earlier ones.

---

## API Keys & Credentials

Edit `config/config.php` to add your API keys:

### Required Keys

```php
// Primary AI provider for the Chashi Bhai agent
define('GROQ_API_KEY', 'gsk_...');

// Disease detection via Google Gemini Vision
define('GEMINI_API_KEY', 'AIza...');
```

### Optional Keys (admin-switchable providers)

```php
define('OPENAI_API_KEY', 'sk-...');
define('CLAUDE_API_KEY', 'sk-ant-...');
define('DEEPSEEK_API_KEY', '...');
define('OPENROUTER_API_KEY', 'sk-or-...');
define('PLANT_ID_API_KEY', '...');         // Plant.id backup for disease detection
```

### Free APIs (no key needed — auto-configured)

| Service | Purpose |
|---------|---------|
| Open-Meteo | Real-time weather data |
| Nominatim (OpenStreetMap) | Location geocoding |
| OpenStreetMap | Map tiles |

### Where to Get API Keys

| Provider | URL | Free Tier |
|----------|-----|-----------|
| GROQ | console.groq.com | ~14,400 req/day for LLaMA 3.3 70B |
| Google Gemini | aistudio.google.com | 1,500 req/day |
| OpenAI | platform.openai.com | Pay-per-token |
| Anthropic Claude | console.anthropic.com | Pay-per-token |
| DeepSeek | platform.deepseek.com | Low cost |
| OpenRouter | openrouter.ai | 100+ models, pay-per-token |

---

## Email (SMTP) Configuration

Required for: password reset, email verification, order notifications, scheduled report alerts.

```php
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'your@gmail.com');
define('SMTP_PASS',      'xxxx-xxxx-xxxx-xxxx');   // Gmail App Password
define('SMTP_FROM',      'your@gmail.com');
define('SMTP_FROM_NAME', 'Smart Chashi');
```

**Gmail App Password Setup:**
1. Enable 2-Factor Authentication on your Google account
2. Go to Google Account → Security → App Passwords
3. Generate a new App Password for "Mail"
4. Use this 16-character password in `SMTP_PASS` (not your Gmail login password)

---

## File Permissions

The following directories must be **writable** by the web server process:

| Directory | Purpose |
|-----------|---------|
| `uploads/` | Disease detection image uploads |
| `public/uploads/` | Profile photos, product images |
| `reports/` | Generated CSV/JSON report files |

**Windows / XAMPP:** Writable by default. No action needed.

**Linux (Apache):**
```bash
chmod -R 775 uploads/ public/uploads/ reports/
chown -R www-data:www-data uploads/ public/uploads/ reports/
```

---

## Web Server Configuration

### `.htaccess` Rules

The project's `.htaccess` handles clean URL routing:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

This routes all non-file requests to `index.php`, where `router.php` maps the URL to the correct page file.

### PHP Settings (Recommended)

In `php.ini` or via `.htaccess`:

```ini
upload_max_filesize = 50M
post_max_size = 52M
max_execution_time = 60
memory_limit = 256M
display_errors = Off    ; production
display_errors = On     ; development
```

---

## First Run & Verification

After completing all steps, visit:

```
http://localhost/smartchashi/
```

You should see the Smart Chashi home/login page.

### Verification Checklist

| Test | URL | Expected |
|------|-----|----------|
| Home page | `/smartchashi/` | Login page loads |
| Registration | `/smartchashi/register` | Form visible |
| Agent chat | `/smartchashi/agent/chat` | Chat UI loads |
| Admin panel | `/smartchashi/admin-secure/` | Admin login |
| Marketplace | `/smartchashi/shop/` | Shop homepage |
| Disease detection | `/smartchashi/disease` | Upload form (after login) |

---

## User Roles & Access

| Role | Default | Access Level |
|------|---------|-------------|
| `farmer` | Registration default | Dashboard, crops, weather, marketplace, agent, community |
| `officer` | Must be assigned | All farmer features + alerts, advisory, reports, user management |
| `admin` | Must be assigned via SQL | Full access including admin panel |

### Creating an Admin Account

Register normally via `/register`, then promote via SQL:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

Or promote to officer:
```sql
UPDATE users SET role = 'officer' WHERE email = 'officer@email.com';
```

---

## Common Issues

| Problem | Likely Cause | Fix |
|---------|-------------|-----|
| Blank white page | PHP error with `display_errors = Off` | Enable display errors in `php.ini` |
| "404 Not Found" on pages | `.htaccess` not processed | Enable `AllowOverride All` in httpd.conf; restart Apache |
| DB connection error | Wrong credentials in `config.php` | Verify `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| Agent gives no response | `GROQ_API_KEY` missing or invalid | Check key in `config.php`; verify it works in GROQ console |
| Disease detection fails | `GEMINI_API_KEY` missing | Add Gemini key; check API quota |
| Uploads not saving | Folder not writable | Set write permissions on `uploads/`, `public/uploads/` |
| Email not sending | Wrong SMTP or app password | Use Gmail App Password; check port 587 not blocked |
| "Session already started" warning | Duplicate session start | Check `config.php` — `session_start()` should run once |
| Bengali text garbled | Charset mismatch | Verify database collation is `utf8mb4_unicode_ci` |
| Shop pages not loading | Missing shop migrations | Run all `shop/Database/*.sql` migrations in order |

---

## Production Deployment Notes

1. **Disable `display_errors`** in `php.ini`: `display_errors = Off`
2. **Set `BASE_URL`** explicitly in `config.php` to your domain
3. **Use HTTPS** — the application passes session cookies; enable SSL
4. **Restrict admin path** — consider IP whitelisting `/admin-secure/` in Apache
5. **Set up cron** for scheduled reports:
   ```
   0 0 * * * curl -s http://yourdomain.com/admin-secure/ajax/reports.php?action=run_scheduled
   ```
6. **Backup uploads** regularly — disease photos and profile images are not in DB
7. **Rotate API keys** — never commit keys to version control
8. **MySQL user** — create a dedicated MySQL user with only SELECT/INSERT/UPDATE/DELETE on `smartcashi_db` (no DROP, no FILE)
