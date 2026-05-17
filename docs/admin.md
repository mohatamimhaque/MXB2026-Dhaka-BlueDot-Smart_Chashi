# Admin Panel

> The Smart Chashi admin panel provides full platform oversight: user management, AI provider configuration, analytics, content moderation, report generation, security monitoring, and system health — all behind a role-gated interface.

---

## Table of Contents

1. [Access & Authentication](#access--authentication)
2. [Admin Panel Pages](#admin-panel-pages)
3. [Dashboard](#dashboard)
4. [User Management](#user-management)
5. [Ban Management & Appeals](#ban-management--appeals)
6. [Reports System](#reports-system)
7. [Analytics](#analytics)
8. [AI Provider Management](#ai-provider-management)
9. [Learning Center Management](#learning-center-management)
10. [Notifications System](#notifications-system)
11. [Content Moderation](#content-moderation)
12. [Security Events](#security-events)
13. [Admin 2FA System](#admin-2fa-system)
14. [IP Rules Management](#ip-rules-management)
15. [Admin Activity Logs](#admin-activity-logs)
16. [System Monitoring](#system-monitoring)
17. [Settings & Configuration](#settings--configuration)
18. [Shop Settings](#shop-settings)
19. [Database Backup](#database-backup)
20. [CSRF Protection](#csrf-protection)
21. [File Structure](#file-structure)

---

## Access & Authentication

### URL

```
http://localhost/smartchashi/admin-secure/
```

Unauthenticated requests are redirected to:
```
http://localhost/smartchashi/admin-secure/admin-login
```

### Requirements

A user account must exist in the `users` table with `role = 'admin'`.

**Create admin via SQL:**
```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

### Auth Gate

Every admin page includes `admin-secure/layouts/admin-header.php` at the top, which immediately checks:

```php
if (!isLoggedIn()) {
    header('Location: /smartchashi/admin-secure/admin-login');
    exit;
}
$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: /smartchashi/');
    exit;
}
```

---

## Admin Panel Pages

| Page | URL Slug | Description |
|------|----------|-------------|
| Dashboard | `admin-dashboard` | KPI overview, real-time activity, alerts |
| Users | `admin-users` | Browse, search, filter, ban/unban, role changes |
| Reports | `admin-reports` | Generate, schedule, preview, download platform reports |
| Analytics | `admin-analytics` | Tabbed analytics (marketplace, learning, community, AI) |
| AI Providers | `admin-ai` | Switch primary AI backend, view token usage |
| Learning | `admin-learning` | Create and manage learning modules and articles |
| Notifications | `admin-notifications` | Send targeted or broadcast notifications |
| Content | `admin-content` | Review and resolve user-submitted content reports |
| Security | `admin-security` | Security events, failed logins, suspicious activity |
| Monitoring | `admin-monitoring` | Error logs, system health, unresolved issues |
| Settings | `admin-settings` | App-wide configuration constants |
| Shop Settings | `admin-shop-settings` | Marketplace name, tagline, delivery notes |
| Backup | `admin-backup` | Database backup and restore |

---

## Dashboard

**File:** `admin-secure/pages/admin-dashboard.php`

The dashboard provides a single-screen overview of platform health:

### KPI Cards
- Total registered users (with role breakdown)
- Active AI conversations (last 24h)
- Community posts (last 7 days)
- Marketplace orders (pending, confirmed)
- Disease detections (this week)
- New alerts issued (this week)

### Activity Feed
- Last 10 actions across the platform (logins, posts, orders, reports)
- Color-coded by event type

### Alert Badges
- Red badge on sidebar for: unacknowledged security events, critical errors, pending content reports

---

## User Management

**File:** `admin-secure/pages/admin-users.php`  
**AJAX:** `admin-secure/ajax/users.php`

### Features

| Feature | Description |
|---------|-------------|
| Search | Filter by name, email, or district |
| Role filter | Show only farmers / officers / admins |
| Status filter | Active / banned users |
| Ban / Unban | Toggle `is_active` flag — banned users cannot log in |
| Role change | Promote farmer → officer → admin or demote |
| View profile | Drill into user details: crops, reports, conversations |
| Delete | Hard-delete user record and related data |

### What Counts as "Banned"

Setting `is_active = 0` on a user prevents login. The login handler checks:
```php
if (!$user['is_active']) {
    return ['success' => false, 'message' => 'Account suspended'];
}
```

---

## Ban Management & Appeals

**Table:** `user_bans`

The ban system supports temporary and permanent bans with an optional appeal workflow.

### Ban Record Structure

| Column | Type | Description |
|--------|------|-------------|
| `ban_id` | int | Primary key |
| `user_id` | int | Banned user |
| `banned_by` | int | Admin who issued the ban |
| `ban_type` | enum | `temporary` or `permanent` |
| `reason` | text | Ban reason |
| `ban_expires_at` | timestamp | NULL for permanent bans |
| `is_active` | tinyint | 1 = ban in force |
| `appeal_text` | text | User's appeal message |
| `appeal_status` | enum | `none` \| `pending` \| `approved` \| `rejected` |
| `appeal_reviewed_by` | int | Admin who reviewed the appeal |
| `appeal_reviewed_at` | timestamp | Review timestamp |

### Appeal Workflow

```
User submits appeal (from profile or login screen)
    → appeal_text saved, appeal_status = 'pending'
    → Admin sees pending appeal badge in sidebar
    → Admin reviews: approve (is_active = 0) or reject
    → appeal_status updated, appeal_reviewed_by/at recorded
```

Banning a user via the Users panel sets `is_active = 0` in the `users` table AND creates a row in `user_bans`.

---

## Reports System

**File:** `admin-secure/pages/admin-reports.php`  
**AJAX:** `admin-secure/ajax/reports.php`  
**Storage:** `reports/` directory + `generated_reports` table

### Report Types

| Type Key | Display Name | Data Collected |
|----------|-------------|---------------|
| `user_summary` | User Summary | Registration trends by date, role distribution, active vs inactive, district breakdown |
| `security_audit` | Security Audit | Failed login count by day, CSRF violations, brute-force detections, event severity distribution |
| `activity_log` | Activity Log | Page views, feature usage, engagement metrics, peak usage times |
| `content_analytics` | Content Analytics | Post counts by category, comment volume, report rates, trending topics |
| `system_health` | System Health | Error count by severity, unresolved issue count, error trend over time |
| `financial` | Financial Report | Order totals, revenue estimates, product category breakdown, seller performance |
| `ai_usage` | AI Usage | Token consumption by provider, requests per day, cost estimates, top users |

### Report Formats

| Format | Notes |
|--------|-------|
| **CSV** | UTF-8 BOM encoded for correct Bengali display in Excel |
| **PDF** (stored as JSON) | Displayed as in-browser preview with metrics grid |
| **XLSX** | Downloads as CSV with `.xlsx` extension for Excel compatibility |

### Generating a Report

1. Navigate to **Admin → Reports**
2. Choose a report type card
3. Set the date range (presets: 7d, 30d, 90d, 1 year, this month)
4. Choose output format
5. Click **Generate** — animated 3-step progress bar while generating
6. Report appears in the generated reports table below

### Downloading / Previewing

| Action | Description |
|--------|-------------|
| **Download** | Streams the file from `reports/` directory |
| **Preview** | In-browser: renders summary metrics + first 15 data rows |
| **Delete** | Removes the DB record and file from disk |

> Preview works even if the file was deleted from disk — it regenerates data on-the-fly from the current DB state.

### Scheduled Reports

Automate report generation on a recurring schedule:

1. Click **Schedule** on any report type
2. Set frequency: `Daily`, `Weekly`, `Monthly`, or `Quarterly`
3. Set format and optional email for delivery notification
4. Toggle active/inactive without deleting the schedule
5. **Run Now** forces immediate generation regardless of schedule

Schedules stored in `scheduled_reports`. Execution requires a cron job:
```
0 0 * * * curl -s http://yourdomain.com/admin-secure/ajax/reports.php?cron=1
```

---

## Analytics

**File:** `admin-secure/pages/admin-analytics.php`

Four tabs of platform-wide analytics:

### Marketplace Tab
- Order volume over time (line chart)
- Revenue estimates (bar chart)
- Top 10 products by sales
- Seller performance ranking
- Order status distribution (pie chart)

### Learning Tab
- Module view counts (ranking table)
- Completion rates per module
- Popular content types (article vs video)
- Daily active learners trend

### Community Tab
- Post and comment volume over time
- Most active users
- Trending topics / tags
- Content report rate (flags per post)

### AI Usage Tab
- API requests per day by provider (stacked bar chart)
- Token consumption breakdown (prompt vs completion)
- Cost estimates per provider
- Top 10 users by AI usage
- Response time distribution

---

## AI Provider Management

**File:** `admin-secure/pages/admin-ai.php`  
**AJAX:** `admin-secure/ajax/ai.php`

### What You Can Do

| Action | Description |
|--------|-------------|
| View current provider | Shows active provider name, model, and key status |
| Switch provider | Select from: GROQ, Gemini, Claude, OpenAI, DeepSeek, OpenRouter |
| Set model name | Free-text or dropdown for common model names |
| Test connection | Sends a test request to verify the API key works |
| Save | Writes to `ai_providers` table; takes effect for next request |
| View usage logs | Token usage history filtered by date range |

### Provider Switching Flow

```
Admin selects new provider + model → clicks Test → API call succeeds
→ clicks Save
→ UPDATE ai_providers SET provider_name = ?, model_name = ?, is_active = 1
→ all subsequent agent chat requests use new provider
```

No server restart required. Change is immediate.

---

## Learning Center Management

**File:** `admin-secure/pages/admin-learning.php`  
**Tables:** `learn_categories`, `learn_content`

### Module Management

| Action | Description |
|--------|-------------|
| Create module | Title (EN + BN), description, category, level (beginner/intermediate/advanced), thumbnail |
| Edit module | Update any field; toggle published state |
| Delete module | Removes module and all associated content |
| Reorder content | Drag-and-drop ordering via `order_index` |

### Content Management

| Field | Description |
|-------|-------------|
| Title | Article/video title |
| Content | Rich HTML body (article) or embed URL (video) |
| Content type | `article` or `video` |
| Order index | Display order within module |

Published modules appear in `pages/learn.php`. Unpublished modules are hidden from users but remain editable in the admin panel.

---

## Notifications System

**File:** `admin-secure/pages/admin-notifications.php`  
**AJAX:** `admin-secure/ajax/notifications.php`  
**Table:** `admin_notifications`

### Sending Notifications

| Target | How | Effect |
|--------|-----|--------|
| **Broadcast** | `user_id = NULL` | All active users see it |
| **By user ID** | `user_id = 42` | Only user 42 sees it |
| **By role** | Loop insert per role | All farmers or all officers |

### Notification Types

| Type | Badge Color | Use Case |
|------|------------|---------|
| `info` | Blue | General announcements |
| `warning` | Yellow | Maintenance windows, downtime |
| `alert` | Red | Critical: disease outbreaks, weather warnings |

### How Users See Notifications

- Bell icon in main site navbar shows unread count badge
- Bell click opens notification dropdown
- Marking one read → `is_read = 1`
- Unread count fetched via `ajax/get-unread-count.php`

---

## Content Moderation

**File:** `admin-secure/pages/admin-content.php`  
**Table:** `content_reports`

Users can flag community posts, comments, and marketplace products as inappropriate.

### Report Statuses

| Status | Description |
|--------|-------------|
| `pending` | Awaiting admin review |
| `resolved` | Action taken (post deleted or warning issued) |
| `dismissed` | Report reviewed; no action taken |

### Admin Actions

| Action | Description |
|--------|-------------|
| View reported content | See the flagged post/comment/product |
| Resolve | Mark as resolved; optionally delete the content |
| Dismiss | Mark as dismissed; no action |
| Ban reporter (if abuse) | User management link |

### Auto-escalation

Pending content reports older than 7 days appear with a visual warning in the admin sidebar badge.

---

## Security Events

**File:** `admin-secure/pages/admin-security.php`  
**Table:** `security_events`

### Event Types Logged

| Event Type | Trigger |
|-----------|---------|
| `failed_login` | Wrong password on login attempt |
| `brute_force` | 5+ failed logins from same IP in 10 minutes |
| `csrf_violation` | POST with missing or mismatched CSRF token |
| `suspicious_request` | Malformed request pattern detected |
| `admin_action` | Sensitive admin operation performed |

### Severity Levels

| Level | Badge | Definition |
|-------|-------|-----------|
| `low` | Gray | Informational; normal in low volumes |
| `medium` | Yellow | Needs monitoring |
| `high` | Orange | Likely malicious; review immediately |
| `critical` | Red | Active attack pattern; immediate action required |

### Dashboard Badge

High or critical severity events from the last 24 hours trigger a red badge on the Security menu item in the admin sidebar.

### Acknowledging Events

Admin can mark events as `is_acknowledged = 1` to clear them from the active view. Acknowledged events remain in the log for audit purposes.

---

## Admin 2FA System

**Tables:** `admin_2fa_tokens`, `admin_2fa_backup_codes`, `admin_trusted_devices`, `admin_sessions`

Admin accounts can require two-factor authentication before gaining panel access.

### Token Types

| Type | Description |
|------|-------------|
| `email` | 6-digit code sent to registered email |
| `totp` | Time-based one-time password (authenticator app) |
| `sms` | Code sent via SMS |
| `backup` | Pre-generated single-use backup code |

### Trust Flow

1. Admin logs in with password → 2FA prompt appears
2. Admin enters 6-digit token from `admin_2fa_tokens`
3. Optionally tick "Trust this device" → `admin_trusted_devices` row created
4. Subsequent logins from that device skip 2FA challenge
5. Trusted device entries can be revoked from the security settings page

### Backup Codes

10 one-time codes stored (hashed) in `admin_2fa_backup_codes`. Each has a `used` flag — once consumed it cannot be reused. Backup codes are regenerated when the admin resets their 2FA configuration.

---

## IP Rules Management

**Table:** `admin_ip_rules`

Restricts or grants access to the admin panel based on IP address or country.

### Rule Types

| Type | Behavior |
|------|----------|
| `whitelist` | Only these IPs can access the admin panel |
| `blacklist` | These IPs are always blocked |
| `geoblock` | Block by country code |

### Rule Fields

| Column | Description |
|--------|-------------|
| `ip_address` | Single IPv4 or IPv6 address |
| `ip_range_start` / `ip_range_end` | CIDR range support |
| `rule_type` | `whitelist` \| `blacklist` \| `geoblock` |
| `country_code` | ISO 3166-1 alpha-2 for geoblock rules |
| `reason` | Human-readable note |
| `auto_created` | 1 if auto-generated after brute-force detection |
| `expires_at` | NULL for permanent; timestamp for temporary blocks |

### Admin UI Actions

- Add IP rule (with manual IP, range, or country)
- Delete rule (logs `delete_ip_rule` in `admin_activity_logs`)
- View active rules table
- Auto-created blacklist entries appear after brute-force detection

---

## Admin Activity Logs

**Table:** `admin_activity_logs`

Every sensitive admin action is recorded with before/after JSON snapshots. The log currently holds **97 entries** in the demo dataset.

### Log Columns

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | int | Admin who performed the action |
| `action` | varchar | Action name (e.g., `update_user`, `ban_user`, `generate_report`) |
| `action_category` | enum | `user` \| `security` \| `system` \| `content` \| `settings` \| `data` \| `backup` \| `report` |
| `entity_type` | varchar | What was affected (`user`, `ip_rule`, `backup`, `report`, `task`) |
| `entity_id` | int | Primary key of the affected entity |
| `old_value` | text (JSON) | Full before-state of the record |
| `new_value` | text (JSON) | Full after-state or action payload |
| `ip_address` | varchar | Admin's IP at time of action |
| `risk_level` | enum | `low` \| `medium` \| `high` \| `critical` |

### Risk Level Guide

| Level | Examples |
|-------|---------|
| `low` | Admin login, view action, export |
| `medium` | Update user, add IP rule, generate report |
| `high` | Delete user, ban user, trigger backup |
| `critical` | Reserved for destructive system-wide actions |

### Querying the Log

```sql
-- All high-risk actions in the last 7 days
SELECT * FROM admin_activity_logs
WHERE risk_level IN ('high','critical')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;

-- All actions by a specific admin
SELECT * FROM admin_activity_logs
WHERE user_id = 49 ORDER BY created_at DESC;
```

---

## System Monitoring

**File:** `admin-secure/pages/admin-monitoring.php`  
**AJAX:** `admin-secure/ajax/monitoring.php`  
**Table:** `error_logs`

### What Is Monitored

| Metric | Source |
|--------|--------|
| Server error count | `error_logs` table |
| Error severity breakdown | `severity` column: error / critical / warning / info |
| Unresolved issues | `is_resolved = 0` filter |
| Error trend | Errors per day over last 30 days |
| Recent errors | Last 20 error log entries with file + line |

### Resolving Errors

Admins can mark errors as resolved (`is_resolved = 1`) directly from the monitoring page. This removes them from the active issue list without deleting the log entry.

---

## Settings & Configuration

**File:** `admin-secure/pages/admin-settings.php`

Manages app-wide configuration that may change post-deployment:

| Setting | Description |
|---------|-------------|
| App name | Display name in emails and UI |
| Maintenance mode | Toggle maintenance page for non-admin users |
| Registration | Enable/disable new user registration |
| Max upload size | Image upload size limit |
| Notification email | Default sender email for system notifications |

Settings may be stored in a DB table or `config/config.php` depending on implementation version.

---

## Shop Settings

**File:** `admin-secure/pages/admin-shop-settings.php`

Controls marketplace display configuration:

| Setting | Description |
|---------|-------------|
| Shop name | Display name shown in shop header |
| Shop tagline | Subtitle line under shop name |
| Currency symbol | Default: ৳ (Taka) |
| Delivery notes | Shown on checkout and confirmation pages |
| Shop enabled | Toggle shop visibility |

These write to `shop/config/config.php` constants or a dedicated settings table.

---

## Database Backup

**File:** `admin-secure/pages/admin-backup.php`

| Feature | Description |
|---------|-------------|
| Generate backup | Dumps current `smartcashi_db` as SQL file |
| Download | Download SQL dump to local machine |
| View backup history | List of previous backups with timestamps |
| Restore | Upload a SQL file to restore (with confirmation step) |

> **Warning:** Restore replaces all data. Always backup current state before restoring.

---

## CSRF Protection

All admin AJAX requests require a CSRF token generated at page load:

### Token Generation (in admin-header.php)
```php
$csrf_token = generateCSRFToken(); // stored in $_SESSION['csrf_token']
```

### Token in Forms
```html
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
```

### Token in AJAX
```javascript
const formData = new FormData();
formData.append('csrf_token', window.CSRF_TOKEN);
formData.append('action', 'generate_report');
```

### Validation in AJAX Handler
```php
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}
```

Token TTL: **1 hour**. Expired tokens force a page reload to get a fresh token.

---

## File Structure

```
admin-secure/
│
├── layouts/
│   └── admin-header.php          ← Auth gate, sidebar nav, CSRF setup, <head>
│
├── pages/
│   ├── admin-login.php           ← Admin login form (no auth required)
│   ├── admin-dashboard.php       ← KPI overview
│   ├── admin-users.php           ← User management table
│   ├── admin-reports.php         ← Report generation UI + schedules
│   ├── admin-analytics.php       ← 4-tab analytics charts
│   ├── admin-ai.php              ← Provider switcher + usage logs
│   ├── admin-learning.php        ← Learning module editor
│   ├── admin-notifications.php   ← Notification composer + history
│   ├── admin-content.php         ← Content report queue
│   ├── admin-security.php        ← Security event log
│   ├── admin-monitoring.php      ← Error log + system health
│   ├── admin-settings.php        ← App configuration
│   ├── admin-shop-settings.php   ← Marketplace configuration
│   └── admin-backup.php          ← DB backup/restore
│
├── ajax/
│   ├── reports.php               ← Report generate/download/preview/schedule
│   ├── users.php                 ← User CRUD operations
│   ├── notifications.php         ← Send notifications
│   ├── monitoring.php            ← System health data
│   └── (other admin handlers)
│
└── assets/
    ├── css/                      ← Admin-specific styles
    └── js/                       ← Admin-specific scripts
```
