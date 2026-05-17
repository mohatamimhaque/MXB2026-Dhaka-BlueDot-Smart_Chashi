# System Architecture

> Smart Chashi is a full-stack PHP web application targeting Bangladeshi farmers and agricultural officers. It combines AI-powered crop advisory, disease detection, a community forum, a marketplace, structured learning, scheduling, and a comprehensive admin panel — all in a single deployable PHP project.

---

## Table of Contents

1. [Design Philosophy](#design-philosophy)
2. [High-Level Architecture](#high-level-architecture)
3. [Directory Structure](#directory-structure)
4. [Request Flow](#request-flow)
5. [Module Map](#module-map)
6. [Authentication & Authorization](#authentication--authorization)
7. [Database Access Layer](#database-access-layer)
8. [AI Provider System](#ai-provider-system)
9. [Multi-language System](#multi-language-system)
10. [Frontend Architecture](#frontend-architecture)
11. [Key Database Tables](#key-database-tables)
12. [Data Flow Diagrams](#data-flow-diagrams)

---

## Design Philosophy

| Principle | Implementation |
|-----------|---------------|
| **No framework** | Pure PHP — no Laravel, Symfony, or Composer dependencies |
| **Modular by directory** | Each feature has its own folder (`agent/`, `shop/`, `admin-secure/`) |
| **Session-based auth** | PHP sessions — no JWT tokens, no OAuth (except email verification) |
| **Single entry point** | All HTTP requests route through `index.php` → `router.php` |
| **AI-agnostic** | Provider abstraction layer — swap AI backends via admin panel |
| **Bilingual** | English + Bengali supported on all user-facing text |

---

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENT BROWSER                               │
│  HTML/CSS/JS (no SPA framework — vanilla JS + fetch API)            │
└──────────────────────┬──────────────────────────────────────────────┘
                       │ HTTP/HTTPS
                       ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     APACHE WEB SERVER                               │
│  .htaccess → mod_rewrite → strips URL segments                      │
└──────────────────────┬──────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     index.php + router.php                          │
│  URL-to-file mapping, auth gate, layout wrapping                    │
└────┬──────────────────┬───────────────────────┬─────────────────────┘
     │                  │                       │
     ▼                  ▼                       ▼
┌─────────┐    ┌──────────────┐    ┌─────────────────────┐
│pages/   │    │ ajax/ api/   │    │ admin-secure/       │
│ PHP UI  │    │ JSON handlers│    │ shop/ agent/        │
│ files   │    │              │    │ (sub-modules)       │
└────┬────┘    └──────┬───────┘    └──────────┬──────────┘
     │                │                       │
     └───────┬─────────┘                       │
             ▼                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                  config/config.php                                  │
│  Database class (PDO), session, constants, helper functions         │
└──────────────────────┬──────────────────────────────────────────────┘
                       │
          ┌────────────┼────────────────────────┐
          ▼            ▼                        ▼
  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐
  │   MySQL DB   │  │ External APIs│  │  providers/*.php     │
  │ smartcashi_db│  │ Groq, Gemini │  │  AI abstraction layer│
  │  InnoDB UTF8 │  │ Open-Meteo   │  │  (6 providers)       │
  └──────────────┘  └──────────────┘  └──────────────────────┘
```

---

## Directory Structure

```
smartchashi/
│
├── index.php                  ← Entry point — loads router, applies layout
├── router.php                 ← URL string → page file mapping
├── .htaccess                  ← Apache mod_rewrite clean URL rules
│
├── config/
│   └── config.php             ← DB credentials, API keys, session start,
│                                 Database class, helper functions, translations
│
├── layouts/                   ← Shared HTML wrappers for main site
│   ├── header.php             — DOCTYPE, <head>, navbar, auth state check
│   └── footer.php             — Shared JS, closing HTML tags
│
├── pages/                     ← User-facing page files (server-rendered HTML)
│   ├── home.php               — Landing page / redirect after login
│   ├── login.php              — Login form
│   ├── register.php           — Registration form (farmer / officer)
│   ├── farmer-dashboard.php   — Farmer's personal dashboard
│   ├── officer-dashboard.php  — Officer's management dashboard
│   ├── farmer-profile-view.php— Public farmer profile
│   ├── officer-profile-view.php
│   ├── profile.php            — Edit own profile
│   ├── disease.php            — Crop disease detection (image upload)
│   ├── crops.php              — Crop management (add, edit, status)
│   ├── weather.php            — Real-time weather + forecast
│   ├── marketplace.php        — Shop product listings (embedded view)
│   ├── community.php          — Forum: posts, comments, likes, reports
│   ├── alerts.php             — System/officer alerts list
│   ├── issue-alert.php        — Officers: post new alert
│   ├── schedule.php           — Task / activity scheduler
│   ├── advisory.php           — Agricultural advisory content
│   ├── learn.php              — Learning center module list
│   ├── learn-view.php         — Single learning module reader
│   ├── officer-learn.php      — Officer's learning management
│   ├── create-report.php      — Generate farmer field reports
│   ├── my-reports.php         — Farmer's report history
│   ├── farmer-reports.php     — Officer: view farmer reports
│   ├── farmer-orders.php      — Farmer: marketplace order management
│   ├── farmer-messages.php    — Farmer: marketplace messaging
│   ├── user-management.php    — Officer: manage users in district
│   ├── maintenance.php        — Maintenance mode page
│   └── 404.php                — Not found page
│
├── ajax/                      ← Stateful AJAX handlers (session auth required)
│   ├── auth.php               — login, register, logout, forgot-password
│   ├── community.php          — post CRUD, comments, likes, reports
│   ├── alerts.php             — alert CRUD, unread count
│   ├── officer.php            — Officer-specific data handlers
│   ├── profile.php            — Profile update, avatar upload
│   ├── export-reports.php     — Export farmer report as CSV/PDF
│   ├── get-dashboard-stats.php— Farmer dashboard KPIs (JSON)
│   ├── get-upcoming-tasks.php — Task list for dashboard widget
│   ├── get-recent-crops.php   — Crop summary widget
│   ├── get-report-details.php — Single report detail JSON
│   ├── get-my-reports.php     — Farmer's report list JSON
│   ├── get-my-report-detail.php
│   ├── respond-to-report.php  — Officer: add response to farmer report
│   ├── farmer-reports-data.php— Officer: all farmer reports JSON
│   ├── get-recent-activities.php
│   ├── delete-product.php     — Marketplace product deletion
│   ├── update-product.php     — Marketplace product edit
│   └── forgot-password.php    — Password reset email
│
├── api/                       ← REST-style JSON API (token or session auth)
│   ├── auth/                  — login.php, register.php
│   ├── disease/               — analyze.php (Gemini Vision)
│   ├── crop/                  — get, add, update, delete, update-status
│   ├── community/             — like-post.php
│   ├── chat/                  — send-message.php
│   ├── tasks/                 — task management
│   └── sms/                   — SMS integration (gateway)
│
├── providers/                 ← AI provider abstraction layer
│   ├── BaseProvider.php       — Abstract class: chat(), getModelName()
│   ├── GroqProvider.php       — LLaMA 3.3 70B (default primary)
│   ├── GeminiProvider.php     — Google Gemini 1.5 Flash
│   ├── ClaudeProvider.php     — Anthropic Claude 3.5 Haiku
│   ├── OpenAIProvider.php     — OpenAI GPT-4o Mini
│   ├── DeepSeekProvider.php   — DeepSeek Chat
│   ├── OpenRouterProvider.php — OpenRouter gateway (100+ models)
│   └── AIProviderFactory.php  — Factory: create() / createFast()
│
├── agent/                     ← Chashi Bhai AI chat module
│   ├── chat.php               — Full-page chat UI (3500+ lines, self-contained)
│   ├── api/
│   │   ├── send.php           — Message processing + AI response pipeline
│   │   └── conversations.php  — Conversation CRUD + memory management API
│   ├── assets/
│   │   ├── logo.png
│   │   └── css/
│   ├── migration.sql          — Initial agent tables
│   └── migration_v2.sql       — Memory table + feedback column
│
├── admin-secure/              ← Admin panel (role-gated, CSRF-protected)
│   ├── layouts/
│   │   └── admin-header.php   — Auth gate (role=admin), sidebar nav, CSRF setup
│   ├── pages/                 — 14 admin page files
│   ├── ajax/                  — Admin AJAX: reports, users, notifications, monitoring
│   └── assets/                — Admin CSS/JS
│
├── shop/                      ← E-commerce marketplace (self-contained module)
│   ├── config/config.php      — Shop constants, SHOP_URL, currency
│   ├── Database/              — 5 SQL migration files
│   ├── includes/              — DB class, functions, auth, email helpers
│   ├── layouts/               — Shop header/footer
│   ├── auth/                  — Shop login, register, verify-email
│   ├── pages/                 — Product listing, cart, checkout, orders, tracking
│   ├── profile/               — Buyer/seller account pages
│   ├── ajax/                  — Cart, orders, reviews, messages, upload
│   └── assets/                — Shop CSS/JS
│
├── public/                    ← Publicly accessible assets
│   ├── css/                   — Shared stylesheets
│   ├── js/                    — Shared scripts
│   └── uploads/               — Profile photos, product images
│
├── img/                       ← Static app images, logos
├── uploads/                   ← Disease detection image uploads (private-served)
├── reports/                   ← Generated report files (CSV, JSON)
└── Database/                  ← Main DB schema dump
    └── smartcashi_db(8).sql
```

---

## Request Flow

### Standard Page Request

```
Browser: GET /smartchashi/weather
          │
          ▼
.htaccess (mod_rewrite)
  → rewrites to: index.php?url=weather
          │
          ▼
index.php
  1. require config/config.php  (DB, session, constants)
  2. require router.php         (URL → page file map)
  3. Check auth:                isLoggedIn() → redirect to login if needed
  4. require layouts/header.php (navbar, <head>, sidebar)
  5. require pages/weather.php  (page content + data fetching)
  6. require layouts/footer.php (scripts, closing HTML)
          │
          ▼
Browser receives complete HTML page
```

### AJAX Request

```
Browser: fetch('/smartchashi/ajax/community.php', {method:'POST', body:...})
          │
          ▼
ajax/community.php
  1. require config/config.php
  2. Validate session (isLoggedIn())
  3. Validate CSRF token (if mutating)
  4. Parse POST body
  5. Execute DB query (PDO prepared statement)
  6. echo json_encode(['success' => true, 'data' => ...])
```

### Agent Chat Request

```
Browser: fetch('/smartchashi/agent/api/send.php', {body: JSON})
          │
          ▼
agent/api/send.php
  1. Session auth check
  2. Rate limit check (30 req/min per session)
  3. Load conversation history from DB
  4. Retrieve user memory from agent_user_memory
  5. Build system prompt (personality + location + memory)
  6. Run 9-domain RAG keyword scorers → inject domain knowledge
  7. Call AIProviderFactory::create($db)->chat($messages)
  8. Auto-extract new memory facts from AI response
  9. Generate conversation title (if new)
  10. Generate follow-up questions (fast model)
  11. Save messages to DB
  12. Log token usage to ai_usage_logs
  13. Return JSON: {reply, detectedLang, followUps, conversation_id}
```

---

## Module Map

| Module | Entry Point | Auth Required | Role |
|--------|------------|---------------|------|
| Home | `pages/home.php` | No (redirect after login) | Any |
| Farmer Dashboard | `pages/farmer-dashboard.php` | Yes | farmer |
| Officer Dashboard | `pages/officer-dashboard.php` | Yes | officer |
| AI Agent (Chashi Bhai) | `agent/chat.php` | Yes | Any |
| Disease Detection | `pages/disease.php` | Yes | Any |
| Crop Management | `pages/crops.php` | Yes | farmer |
| Weather | `pages/weather.php` | Yes | Any |
| Community | `pages/community.php` | Yes | Any |
| Alerts | `pages/alerts.php` | Yes | Any |
| Issue Alert | `pages/issue-alert.php` | Yes | officer |
| Schedule | `pages/schedule.php` | Yes | Any |
| Advisory | `pages/advisory.php` | Yes | Any |
| Learning Center | `pages/learn.php` | Yes | Any |
| Marketplace | `pages/marketplace.php` | Yes | Any |
| Farmer Reports | `pages/create-report.php` | Yes | farmer |
| Admin Panel | `admin-secure/` | Yes | admin |
| Shop Module | `shop/` | Partial | Any (browse); auth (buy) |

---

## Authentication & Authorization

### Session-Based Auth

```php
// Session started in config/config.php
session_start();

// Check helpers (defined in config/config.php)
isLoggedIn()     → (bool) $_SESSION['user_id'] is set
getCurrentUser() → user row from DB (or null)
```

### Role System

| Role | Value | Can Access |
|------|-------|-----------|
| Farmer | `farmer` | Dashboard, crops, marketplace, agent, community, weather |
| Officer | `officer` | All farmer areas + alerts management, user management, reports |
| Admin | `admin` | Everything + `admin-secure/` panel |

Role checks are inline in page files:
```php
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: /smartchashi/');
    exit;
}
```

### CSRF Protection

All mutating requests include CSRF token validation:
```php
$token = generateCSRFToken();    // stored in session, 1-hour TTL
verifyCSRFToken($submitted_token); // hash_equals check
```

The admin panel enforces CSRF on every AJAX call.

### Admin Panel Auth Gate

`admin-secure/layouts/admin-header.php` runs at the top of every admin page:
```php
if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    header('Location: /smartchashi/admin-secure/admin-login');
    exit;
}
```

---

## Database Access Layer

A custom PDO wrapper class `Database` is defined in `config/config.php`:

```php
$db = new Database();

// Fetch single row
$user = $db->single("SELECT * FROM users WHERE user_id = ?", [$id]);

// Fetch all rows
$rows = $db->resultSet("SELECT * FROM posts LIMIT 10");

// Insert / Update / Delete (fluent interface)
$db->query("INSERT INTO table (col) VALUES (?)")
   ->bind(1, $value)
   ->execute();

// Last inserted ID
$newId = $db->lastInsertId();

// Typed bind (for NULL / INT)
->bind(1, null, PDO::PARAM_NULL)
->bind(2, $intVal, PDO::PARAM_INT)
```

All queries use **prepared statements** — no raw string interpolation in SQL.

---

## AI Provider System

### Architecture

```
AIProviderFactory
   ├── create($db)       → reads ai_providers table → instantiates active provider
   └── createFast($db)   → always returns GROQ with llama-3.1-8b-instant

BaseProvider (abstract)
   ├── chat(array $messages, array $options): string
   └── getModelName(): string

Concrete implementations:
   GroqProvider       → api.groq.com/openai/v1/chat/completions
   GeminiProvider     → generativelanguage.googleapis.com
   ClaudeProvider     → api.anthropic.com/v1/messages
   OpenAIProvider     → api.openai.com/v1/chat/completions
   DeepSeekProvider   → api.deepseek.com/chat/completions
   OpenRouterProvider → openrouter.ai/api/v1/chat/completions
```

### Provider Selection

1. Admin selects provider via `admin-secure/pages/admin-ai.php`
2. Selection stored in `ai_providers` table (`is_active = 1`)
3. `AIProviderFactory::create($db)` reads this at runtime — every request
4. No code changes required to switch providers

### Fast Provider

Used for non-critical tasks (title generation, follow-up questions):
- Always `llama-3.1-8b-instant` via GROQ
- Lower latency, lower token cost
- Does not read `ai_providers` table

---

## Multi-language System

```php
// Detect language (cookie > session > default 'en')
get_language() → 'en' or 'bn'

// Translate a key
__('key') → translated string in current language

// Translation arrays defined in config/config.php
$translations = [
    'en' => ['login_btn' => 'Login', 'new_chat_btn' => 'New Chat', ...],
    'bn' => ['login_btn' => 'লগইন', 'new_chat_btn' => 'নতুন চ্যাট', ...]
];
```

The agent UI has its own language mode:
- **Auto** — detects Bengali/English from message content
- **Force Bengali** — all AI responses in Bengali
- **Force English** — all AI responses in English

---

## Frontend Architecture

### No JavaScript Framework

The frontend uses vanilla JS (ES6+). No React, Vue, or jQuery.

Key patterns used:
- `fetch()` API for all AJAX calls
- `FormData` for file uploads
- `localStorage` for client-side persistence (chat settings, bookmarks)
- `CSS custom properties` for theming
- `MutationObserver` for dynamic content
- `Web Speech API` for text-to-speech (agent)
- `IntersectionObserver` for lazy loading

### Asset Delivery

| Asset Type | Location | Notes |
|-----------|---------|-------|
| Global CSS | `public/css/` | Shared across pages |
| Global JS | `public/js/` | Shared scripts |
| Admin CSS/JS | `admin-secure/assets/` | Admin panel only |
| Shop CSS/JS | `shop/assets/` | Shop module only |
| Agent CSS/JS | Inline in `agent/chat.php` | Self-contained |

### Chat UI (agent/chat.php)

The entire agent chat interface is a single self-contained PHP file (~3500 lines) that includes:
- All HTML structure
- All CSS (embedded `<style>`)
- All JavaScript (embedded `<script>`)
- PHP for server-side data injection (user info, existing conversations)

This design avoids external dependencies for the chat module.

---

## Key Database Tables

The database contains **80+ tables** across all modules. Key tables by module:

### Core Platform

| Table | Purpose |
|-------|---------|
| `users` | All platform users (farmers, officers, admins) — `password_hash`, `profile_img_url`, `is_verified` |
| `farmer_profiles` | Extended farmer data linked to `users` |
| `officer_profiles` | Extended officer data linked to `users` |
| `crop_data` | Farmer crop records — `farmer_id`, `area_hectares`, `expected_yield`, `actual_yield` |
| `alerts` | Per-recipient alert rows — `alert_type`, `priority`, `sent_via`, `expires_at` |
| `farm_tasks` | Scheduled farm activities |
| `market_prices` | Commodity price data by district/region (20+ seeded records) |
| `disease_library` | Disease knowledge base with Bengali names, organic treatments |
| `weather_data` / `weather_alerts` | Stored weather observations and alert records |

### AI Agent

| Table | Purpose |
|-------|---------|
| `agent_conversations` | Chat sessions — dual ID: int `id` + varchar `conversation_id` |
| `agent_messages` | Messages with `images` LONGTEXT JSON for image uploads |
| `agent_user_memory` | Cross-session user facts auto-extracted by AI |
| `ai_usage_logs` | Token consumption per request by provider |
| `ai_providers` | Active provider config (1 active row at a time) |

### Admin Panel

| Table | Purpose |
|-------|---------|
| `admin_activity_logs` | Comprehensive audit trail — `action_category`, `old_value`/`new_value` JSON, `risk_level` |
| `admin_2fa_tokens` | Admin 2FA codes — `token_type` enum(email/totp/sms/backup) |
| `admin_2fa_backup_codes` | One-time backup codes for 2FA recovery |
| `admin_trusted_devices` | Devices exempted from 2FA challenge |
| `admin_ip_rules` | IP whitelist/blacklist/geoblock rules |
| `admin_sessions` | Admin session tracking |
| `generated_reports` | Report file metadata |
| `scheduled_reports` | Report automation schedules |
| `content_reports` | User content flags for moderation |
| `error_logs` | Server-side error log |
| `security_events` | Auth/CSRF/brute-force events |
| `admin_notifications` | Platform notification queue |
| `user_bans` | Ban records with appeal workflow (`appeal_text`, `appeal_status`) |

### Community

| Table | Purpose |
|-------|---------|
| `community_posts` | Forum posts |
| `post_comments` | Threaded comments |
| `post_likes` | Like tracking |
| `post_bookmarks` | Saved posts |
| `post_reports` | Content reports |

### Learning Center

| Table | Purpose |
|-------|---------|
| `learn_categories` | Course category tree |
| `learn_content` | Articles, videos, quizzes — 7 content types, season-aware |
| `learn_progress` | Per-user completion tracking |
| `learn_quiz_questions` / `learn_quiz_options` / `learn_quiz_attempts` | Quiz system |
| `learn_paths` / `learn_path_items` | Learning path sequences |
| `learn_certificates` | Completion certificates |

### Marketplace

| Table | Purpose |
|-------|---------|
| `marketplace_products` | Product listings — `product_type`, `quality_grade`, `is_negotiable`, `bulk_discount_percent` |
| `marketplace_orders` | Direct platform orders — payment methods: cash/bkash/nagad/bank/other |
| `product_reviews` | Product ratings and text reviews with threaded replies |
| `product_wishlist` | Saved product watchlists |

### Shop Module (self-contained sub-app)

| Table | Purpose |
|-------|---------|
| `general_users` | Separate shop user accounts (not linked to main `users`) |
| `shop_cart` | Active cart items — supports guest (session_id) and logged-in users |
| `shop_orders` | Shop module orders with full shipping fields — `order_number`, `shipping_district` |
| `shop_order_items` | Line items per shop order |
| `shop_conversations` | Messaging threads — `farmer_id`, `customer_id`, `customer_type` |
| `shop_messages` | Individual messages in a conversation thread |
| `shop_settings` | Key-value store for shop configuration (delivery charges, footer text, etc.) |
| `shop_otp_codes` | Email OTP for shop registration/password reset |

### Security & System

| Table | Purpose |
|-------|---------|
| `rate_limits` | Request throttling by ip/user/session/api_key |
| `api_request_logs` | HTTP request audit log |
| `honeypot_logs` | Bot trap detection |
| `email_queue` | Queued outbound emails with retry logic |
| `audit_trail` | Low-level system audit events |

Full column definitions → see [database.md](database.md)

---

## Data Flow Diagrams

### User Authentication Flow

```
Register / Login Form
         │
         ▼
ajax/auth.php
  → validate email + password
  → password_verify() against bcrypt hash
  → set $_SESSION['user_id'], ['user_role']
  → return JSON {success, redirect}
         │
         ▼
Browser redirects to dashboard
  → all subsequent pages check isLoggedIn()
```

### Disease Detection Flow

```
User uploads crop photo (disease.php)
         │
         ▼
api/disease/analyze.php
  → validate: image/jpeg|png, max 50MB
  → save to uploads/ with unique name
  → base64-encode image
  → POST to Gemini Vision API
      prompt: "Identify crop disease, confidence, treatment..."
  → parse JSON response
  → return: {disease, confidence, description, treatment, prevention}
         │
         ▼
disease.php renders result card
```

### Community Post Flow

```
User writes post (community.php)
         │
         ▼
ajax/community.php (action=create_post)
  → auth check
  → CSRF check
  → sanitize content (htmlspecialchars)
  → INSERT into community_posts
  → return new post HTML fragment
         │
         ▼
JS prepends post to feed (no page reload)
```
