# Database Schema

> **Source:** `Database/smartcashi_db.sql` (MariaDB 10.4.32 dump, generated 2026-05-17)  
> **Database:** `smartcashi_db` · **Engine:** InnoDB · **Charset:** `utf8mb4_unicode_ci`

---

## Table of Contents

1. [Full Table Inventory](#full-table-inventory)
2. [Core User Tables](#core-user-tables)
3. [Agent / AI Chat Tables](#agent--ai-chat-tables)
4. [AI System Tables](#ai-system-tables)
5. [Agricultural Data Tables](#agricultural-data-tables)
6. [Community & Social Tables](#community--social-tables)
7. [Alerts & Notifications Tables](#alerts--notifications-tables)
8. [Learning Center Tables](#learning-center-tables)
9. [Marketplace Tables](#marketplace-tables)
10. [Market Data Tables](#market-data-tables)
11. [Shop Module Tables](#shop-module-tables)
12. [Admin & Security Tables](#admin--security-tables)
13. [System & Infrastructure Tables](#system--infrastructure-tables)
14. [Naming Conventions](#naming-conventions)

---

## Full Table Inventory

> 80+ tables total. Grouped by domain.

| Group | Tables |
|-------|--------|
| **Users** | `users`, `general_users`, `farmer_profiles`, `officer_profiles`, `user_bans`, `user_sessions`, `user_warnings`, `user_notifications`, `notification_preferences`, `password_history` |
| **Agent / Chat** | `agent_conversations`, `agent_messages`, `agent_user_memory`, `chat_messages` |
| **AI System** | `ai_usage_logs`, `ai_chat_logs`, `ai_recommendations` |
| **Agriculture** | `crop_data`, `crop_activities`, `disease_reports`, `disease_report_responses`, `disease_library`, `farm_tasks`, `field_visits`, `fertilizer_recommendations`, `advisories`, `weather_data`, `weather_alerts` |
| **Community** | `community_posts`, `post_comments`, `post_likes`, `comment_likes`, `post_bookmarks`, `post_shares`, `post_helpfulness`, `post_reports`, `content_reports` |
| **Alerts** | `alerts` |
| **Learning** | `learn_categories`, `learn_content`, `learn_likes`, `learn_certificates`, `learn_paths`, `learn_path_items`, `learn_playlist_items`, `learn_quiz_questions`, `learn_quiz_options`, `video_content` |
| **Marketplace** | `marketplace_products`, `marketplace_orders`, `market_prices`, `product_reviews`, `product_reports`, `product_inquiries`, `product_offers`, `product_comparisons`, `product_wishlist`, `recently_viewed`, `review_helpfulness`, `review_likes`, `seller_stats` |
| **Shop** | `shop_cart`, `shop_orders`, `shop_order_items`, `shop_conversations`, `shop_messages`, `shop_otp_codes`, `shop_settings` |
| **Admin** | `admin_notifications`, `admin_activity_logs`, `admin_profiles`, `admin_sessions`, `admin_trusted_devices`, `admin_ip_rules`, `admin_login_attempts`, `admin_2fa_tokens`, `admin_2fa_backup_codes`, `generated_reports`, `scheduled_reports`, `scheduled_tasks`, `task_execution_logs`, `backup_records`, `restore_logs` |
| **Security** | `security_events`, `honeypot_logs`, `rate_limits`, `audit_trail` |
| **System** | `error_logs`, `system_logs`, `system_metrics`, `system_settings`, `settings`, `api_request_logs`, `email_queue`, `file_uploads`, `articles`, `dashboard_widgets` |

---

## Core User Tables

### `users`

The central user table for all platform roles.

```sql
CREATE TABLE `users` (
  `user_id`                int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`                  varchar(255) NOT NULL UNIQUE,
  `phone`                  varchar(20) NOT NULL,
  `password_hash`          varchar(255) NOT NULL,            -- bcrypt hash
  `first_name`             varchar(100) NOT NULL,
  `last_name`              varchar(100) DEFAULT NULL,
  `profile_img_url`        varchar(200) NOT NULL,            -- path: uploads/profiles/...
  `role`                   enum('farmer','officer','admin') DEFAULT 'farmer',
  `is_active`              tinyint(1) DEFAULT 1,             -- 0 = banned
  `is_verified`            tinyint(1) DEFAULT 0,             -- email verified
  `last_login`             timestamp NULL DEFAULT NULL,
  `created_at`             timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`             timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remember_token`         varchar(64) DEFAULT NULL,         -- "remember me" cookie token
  `remember_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Sample data shows:** real users with `profile_img_url` like `uploads/profiles/profile_49_1768498597.png`

**Role permissions:**

| Role | Access |
|------|--------|
| `farmer` | Dashboard, crops, marketplace, agent, community, alerts (read) |
| `officer` | All farmer features + alerts (create), field visits, user management |
| `admin` | Everything + `admin-secure/` panel |

---

### `farmer_profiles`

Extended farmer data linked to `users`.

| Column | Type | Notes |
|--------|------|-------|
| `profile_id` | INT PK AI | |
| `user_id` | INT UNIQUE | FK users |
| `farm_name` | VARCHAR(255) | |
| `farm_size` | DECIMAL(10,2) | in acres |
| `farm_location` | VARCHAR(255) | |
| `district` | VARCHAR(100) | |
| `division` | VARCHAR(100) | |
| `primary_crops` | TEXT | comma-separated |
| `farming_experience` | INT | years |
| `land_ownership` | ENUM | owned/leased/shared |
| `irrigation_type` | VARCHAR(100) | |
| `soil_type` | VARCHAR(100) | |
| `annual_income` | DECIMAL(12,2) | |
| `nid_number` | VARCHAR(20) | National ID |
| `bio` | TEXT | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | ON UPDATE |

---

### `officer_profiles`

Extended officer/field worker data.

| Column | Type | Notes |
|--------|------|-------|
| `profile_id` | INT PK AI | |
| `user_id` | INT UNIQUE | FK users |
| `designation` | VARCHAR(255) | job title |
| `department` | VARCHAR(255) | |
| `district` | VARCHAR(100) | assigned district |
| `division` | VARCHAR(100) | |
| `employee_id` | VARCHAR(50) | |
| `qualification` | TEXT | |
| `specialization` | TEXT | |
| `years_experience` | INT | |
| `created_at` | TIMESTAMP | |

---

### `user_bans`

Comprehensive ban system with appeal workflow.

```sql
CREATE TABLE `user_bans` (
  `ban_id`              int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`             int(11) NOT NULL,
  `ban_type`            enum('temporary','permanent','ip_ban','shadow_ban') NOT NULL,
  `reason`              text NOT NULL,
  `internal_notes`      text DEFAULT NULL,
  `ip_address`          varchar(45) DEFAULT NULL,
  `ip_range`            varchar(100) DEFAULT NULL,
  `banned_by`           int(11) NOT NULL,                   -- FK users (admin)
  `banned_at`           timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at`          timestamp NULL DEFAULT NULL,         -- NULL = permanent
  `is_active`           tinyint(1) DEFAULT 1,
  `unbanned_by`         int(11) DEFAULT NULL,
  `unbanned_at`         timestamp NULL DEFAULT NULL,
  `unban_reason`        text DEFAULT NULL,
  `appeal_submitted`    tinyint(1) DEFAULT 0,
  `appeal_text`         text DEFAULT NULL,
  `appeal_reviewed_by`  int(11) DEFAULT NULL,
  `appeal_status`       enum('pending','approved','denied') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `user_notifications`

User-facing notifications (bell icon in navbar).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `user_id` | INT | FK users |
| `title` | VARCHAR(255) | |
| `message` | TEXT | |
| `type` | VARCHAR(50) | info, warning, alert |
| `is_read` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `user_sessions`

Active login sessions per user.

| Column | Type | Notes |
|--------|------|-------|
| `session_id` | VARCHAR(128) PK | |
| `user_id` | INT | FK users |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | TEXT | |
| `created_at` | TIMESTAMP | |
| `last_activity` | TIMESTAMP | |

---

## Agent / AI Chat Tables

### `agent_conversations`

```sql
CREATE TABLE `agent_conversations` (
  `id`              int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` varchar(64) NOT NULL UNIQUE,             -- hex random string
  `user_id`         int(11) NOT NULL,
  `title`           varchar(255) NOT NULL DEFAULT 'New Chat',
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `summary`         text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** Both `id` (int auto-increment) and `conversation_id` (varchar 64) exist. The application uses `conversation_id` as the public identifier in URLs and API.

---

### `agent_messages`

```sql
CREATE TABLE `agent_messages` (
  `id`              int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` varchar(64) NOT NULL,                    -- FK agent_conversations.conversation_id
  `role`            enum('user','assistant') NOT NULL,
  `content`         text NOT NULL,                           -- HTML (assistant) / plain text (user)
  `images`          longtext DEFAULT NULL,                   -- JSON array of image paths (for image messages)
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `feedback`        tinyint(1) DEFAULT NULL                  -- 1=thumbs_up, -1=thumbs_down
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- `content` for `assistant` role contains HTML rendered from markdown
- `images` JSON field supports image-in-message feature (future or in use for disease images)
- `feedback` set via `conversations.php (action=feedback)`

---

### `agent_user_memory`

```sql
CREATE TABLE `agent_user_memory` (
  `id`           int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`      int(11) NOT NULL,
  `memory_key`   varchar(100) NOT NULL,
  `memory_value` text NOT NULL,
  `source`       enum('auto','manual') NOT NULL DEFAULT 'auto',
  `updated_at`   timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  UNIQUE KEY unique_user_key (user_id, memory_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Sample auto-extracted memories (real data from DB):**

| `memory_key` | `memory_value` | `source` |
|-------------|---------------|---------|
| `farming_type` | vegetable farmer | auto |
| `preferred_language` | Bangla | auto |
| `grows_crop` | rice | auto |
| `farming_method` | organic farming | auto |

---

### `chat_messages`

Legacy/general chat table (separate from agent).

| Column | Type | Notes |
|--------|------|-------|
| `message_id` | INT PK AI | |
| `sender_id` | INT | FK users |
| `receiver_id` | INT | FK users |
| `message` | TEXT | |
| `is_read` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

## AI System Tables

### `ai_usage_logs`

```sql
CREATE TABLE `ai_usage_logs` (
  `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`           int(11) DEFAULT NULL,
  `conversation_id`   varchar(64) DEFAULT NULL,
  `provider`          varchar(20) NOT NULL DEFAULT 'groq',
  -- (additional columns: model, prompt_tokens, completion_tokens, total_tokens, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Logs every AI API call. Linked to both `user_id` and `conversation_id`.

---

### `ai_chat_logs`

Legacy AI chat logging (separate from agent messages).

| Column | Type | Notes |
|--------|------|-------|
| `log_id` | INT PK AI | |
| `user_id` | INT | FK users |
| `user_message` | TEXT | |
| `ai_response` | TEXT | |
| `message_type` | ENUM | general, crop_advice, disease, weather, market |
| `language` | ENUM | bangla, english |
| `sentiment` | VARCHAR(50) | |
| `rating` | INT | 1–5 |
| `created_at` | TIMESTAMP | |

---

### `ai_recommendations`

AI-generated crop recommendations.

```sql
CREATE TABLE `ai_recommendations` (
  `recommendation_id`   int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`             int(11) NOT NULL,
  `crop_id`             int(11) DEFAULT NULL,
  `recommendation_type` enum('crop_selection','planting_time','irrigation',
                              'fertilizer','pest_control','harvesting') NOT NULL,
  `recommendation`      text NOT NULL,
  `confidence_score`    decimal(5,2) DEFAULT NULL,
  `based_on`            text DEFAULT NULL,                   -- weather, soil, history, etc.
  `is_accepted`         tinyint(1) DEFAULT NULL,
  `feedback`            text DEFAULT NULL,
  `created_at`          timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Agricultural Data Tables

### `crop_data`

Farmer crop records (the real crop management table).

```sql
CREATE TABLE `crop_data` (
  `crop_id`             int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `farmer_id`           int(11) NOT NULL,                    -- FK users
  `crop_name`           varchar(100) NOT NULL,
  `crop_type`           varchar(100) DEFAULT NULL,           -- grain, vegetable, fruit
  `variety`             varchar(100) DEFAULT NULL,
  `planting_date`       date DEFAULT NULL,
  `planted_date`        date DEFAULT NULL,
  `expected_harvest`    date DEFAULT NULL,
  `actual_harvest_date` date DEFAULT NULL,
  `area`                decimal(10,2) DEFAULT NULL,          -- in acres
  `area_hectares`       decimal(10,2) DEFAULT NULL,
  `field_location`      varchar(255) DEFAULT NULL,
  `status`              enum('planning','growing','harvesting','harvested','completed','failed')
                        DEFAULT 'planning',
  `expected_yield`      decimal(10,2) DEFAULT NULL,
  `actual_yield`        decimal(10,2) DEFAULT NULL,
  `notes`               text DEFAULT NULL,
  `created_at`          timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Real data:** Rice, Wheat, and other crops with hectare areas and growing status.

---

### `crop_activities`

Activity log per crop (irrigation, fertilizer, spraying, etc.).

| Column | Type | Notes |
|--------|------|-------|
| `activity_id` | INT PK AI | |
| `crop_id` | INT | FK crop_data |
| `farmer_id` | INT | FK users |
| `activity_type` | ENUM | planting, irrigation, fertilizer, spraying, weeding, harvesting, other |
| `description` | TEXT | |
| `activity_date` | DATE | |
| `cost` | DECIMAL(10,2) | |
| `quantity` | DECIMAL(10,2) | |
| `unit` | VARCHAR(50) | kg, liters, etc. |
| `notes` | TEXT | |
| `created_at` | TIMESTAMP | |

---

### `disease_reports`

Crop disease detection and treatment tracking.

```sql
CREATE TABLE `disease_reports` (
  `detection_id`          int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `report_id`             int(11) DEFAULT NULL,
  `user_id`               int(11) NOT NULL,
  `crop_id`               int(11) DEFAULT NULL,              -- FK crop_data
  `disease_name`          varchar(100) DEFAULT NULL,
  `disease_type`          varchar(100) DEFAULT NULL,
  `severity`              enum('low','medium','high') DEFAULT 'low',
  `confidence_score`      decimal(5,2) DEFAULT NULL,         -- AI confidence 0–100
  `image_url`             varchar(255) DEFAULT NULL,         -- uploaded photo path
  `symptoms`              text DEFAULT NULL,
  `detected_date`         timestamp NOT NULL DEFAULT current_timestamp(),
  `treatment_recommended` text DEFAULT NULL,
  `treatment_applied`     text DEFAULT NULL,
  `treatment_cost`        decimal(10,2) DEFAULT NULL,
  `status`                enum('detected','treating','cured','failed') DEFAULT 'detected',
  `verified_by`           int(11) DEFAULT NULL,              -- officer user_id who verified
  `verified_at`           timestamp NULL DEFAULT NULL,
  `created_at`            timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`            timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `disease_report_responses`

Officer responses to farmer disease reports.

| Column | Type | Notes |
|--------|------|-------|
| `response_id` | INT PK AI | |
| `report_id` | INT | FK disease_reports |
| `officer_id` | INT | FK users (officer) |
| `message` | TEXT | |
| `recommended_action` | TEXT | |
| `status` | ENUM | pending, reviewed, resolved |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | ON UPDATE |

---

### `disease_library`

Knowledge base of known crop diseases.

| Column | Type | Notes |
|--------|------|-------|
| `disease_id` | INT PK AI | |
| `disease_name` | VARCHAR(100) | English name |
| `disease_name_bn` | VARCHAR(100) | Bengali name |
| `common_name` | VARCHAR(100) | |
| `scientific_name` | VARCHAR(255) | |
| `affected_crops` | TEXT | |
| `symptoms` | TEXT | |
| `causes` | TEXT | |
| `prevention` | TEXT | |
| `treatment` | TEXT | Chemical treatment |
| `organic_treatment` | TEXT | Organic alternative |
| `image_url` | VARCHAR(255) | |
| `severity_level` | ENUM | low, medium, high |
| `created_at` | TIMESTAMP | |

---

### `farm_tasks`

Scheduled tasks/activities for farmers.

| Column | Type | Notes |
|--------|------|-------|
| `task_id` | INT PK AI | |
| `farmer_id` | INT | FK users |
| `crop_id` | INT | nullable FK crop_data |
| `task_type` | ENUM | irrigation, fertilizing, spraying, weeding, harvesting, planting, other |
| `title` | VARCHAR(255) | |
| `description` | TEXT | |
| `scheduled_date` | DATE | |
| `status` | ENUM | pending, in_progress, completed, cancelled |
| `priority` | ENUM | low, medium, high |
| `created_at` | TIMESTAMP | |

---

### `field_visits`

Officer-scheduled visits to farmer fields.

| Column | Type | Notes |
|--------|------|-------|
| `visit_id` | INT PK AI | |
| `officer_id` | INT | FK users (officer) |
| `farmer_id` | INT | FK users (farmer) |
| `visit_date` | DATETIME | |
| `purpose` | TEXT | |
| `findings` | TEXT | post-visit notes |
| `recommendations` | TEXT | |
| `status` | ENUM | scheduled, completed, cancelled |
| `created_at` | TIMESTAMP | |

---

### `fertilizer_recommendations`

AI-generated fertilizer advice for crops.

| Column | Type | Notes |
|--------|------|-------|
| `recommendation_id` | INT PK AI | |
| `crop_id` | INT | FK crop_data |
| `farmer_id` | INT | FK users |
| `crop_name` | VARCHAR(100) | |
| `soil_type` | VARCHAR(100) | |
| `growth_stage` | VARCHAR(100) | |
| `nitrogen_kg` | DECIMAL(8,2) | |
| `phosphorus_kg` | DECIMAL(8,2) | |
| `potassium_kg` | DECIMAL(8,2) | |
| `recommendation` | TEXT | full text advice |
| `created_at` | TIMESTAMP | |

---

### `advisories`

Agricultural advisories posted by officers/admins.

| Column | Type | Notes |
|--------|------|-------|
| `advisory_id` | INT PK AI | |
| `title` | VARCHAR(255) | |
| `content` | TEXT | |
| `category` | VARCHAR(100) | |
| `district` | VARCHAR(100) | nullable = nationwide |
| `created_by` | INT | FK users (officer) |
| `is_published` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `weather_data`

Stored weather records per location.

| Column | Type | Notes |
|--------|------|-------|
| `weather_id` | INT PK AI | |
| `location` | VARCHAR(255) | |
| `district` | VARCHAR(100) | |
| `temperature` | DECIMAL(5,2) | °C |
| `humidity` | INT | % |
| `rainfall` | DECIMAL(8,2) | mm |
| `wind_speed` | DECIMAL(8,2) | km/h |
| `condition` | VARCHAR(100) | Sunny, Cloudy, etc. |
| `forecast_date` | DATE | |
| `recorded_at` | TIMESTAMP | |

---

### `weather_alerts`

Weather-based agricultural alerts.

| Column | Type | Notes |
|--------|------|-------|
| `alert_id` | INT PK AI | |
| `alert_type` | ENUM | flood, drought, cold_wave, heat_wave, cyclone, other |
| `title` | VARCHAR(255) | |
| `message` | TEXT | |
| `affected_districts` | TEXT | JSON array |
| `severity` | ENUM | low, medium, high, critical |
| `issued_at` | TIMESTAMP | |
| `expires_at` | TIMESTAMP | |
| `created_by` | INT | FK users |

---

## Community & Social Tables

### `community_posts`

```sql
CREATE TABLE `community_posts` (
  `post_id`     int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`     int(11) NOT NULL,
  `title`       varchar(255) DEFAULT NULL,
  `content`     text DEFAULT NULL,
  `category`    varchar(100) DEFAULT NULL,
  `post_type`   enum('question','discussion','tip','success_story','problem')
                DEFAULT 'discussion',
  `image_url`   varchar(255) DEFAULT NULL,
  `likes`       int(11) DEFAULT 0,                           -- denormalized counter
  `views`       int(11) DEFAULT 0,
  `is_pinned`   tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `approved_by` int(11) DEFAULT NULL,
  `tags`        text DEFAULT NULL,                           -- comma-separated tags
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`  timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**50+ real posts** in the database covering rice, vegetables, fish farming, organic methods, etc.

---

### `post_comments`

Comments on community posts.

| Column | Type | Notes |
|--------|------|-------|
| `comment_id` | INT PK AI | |
| `post_id` | INT | FK community_posts |
| `user_id` | INT | FK users |
| `parent_id` | INT | nullable (nested comments) |
| `content` | TEXT | |
| `likes` | INT | |
| `is_approved` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `post_likes`

| Column | Type | Notes |
|--------|------|-------|
| `like_id` | INT PK AI | |
| `post_id` | INT | FK community_posts |
| `user_id` | INT | FK users |
| `created_at` | TIMESTAMP | |

UNIQUE KEY `(post_id, user_id)`.

---

### `post_bookmarks`

Users saving posts for later.

| Column | Type | Notes |
|--------|------|-------|
| `bookmark_id` | INT PK AI | |
| `post_id` | INT | FK community_posts |
| `user_id` | INT | FK users |
| `created_at` | TIMESTAMP | |

---

### `post_reports`

User flags on inappropriate posts.

| Column | Type | Notes |
|--------|------|-------|
| `report_id` | INT PK AI | |
| `post_id` | INT | FK community_posts |
| `reporter_id` | INT | FK users |
| `reason` | VARCHAR(255) | |
| `status` | ENUM | pending, reviewed, resolved |
| `created_at` | TIMESTAMP | |

---

### `content_reports`

Admin content moderation queue (covers posts, comments, products, users, messages, reviews).

```sql
CREATE TABLE `content_reports` (
  `report_id`    int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `content_type` enum('post','comment','product','user','message','review') NOT NULL,
  `content_id`   int(11) NOT NULL,
  `reporter_id`  int(11) NOT NULL,
  -- (reason, status, admin_notes, resolved_by, created_at, resolved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Alerts & Notifications Tables

### `alerts`

Platform alerts from officers/system to farmers. **Much richer than typical alert tables:**

```sql
CREATE TABLE `alerts` (
  `alert_id`   int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    int(11) NOT NULL,                             -- FK users (recipient)
  `alert_type` enum('weather','disease','market','system','advisory','crop','community')
               DEFAULT 'system',
  `title`      varchar(255) DEFAULT NULL,
  `message`    text DEFAULT NULL,
  `priority`   enum('low','medium','high','critical') DEFAULT 'low',
  `category`   varchar(100) DEFAULT NULL,
  `action_url` varchar(255) DEFAULT NULL,                    -- click-through URL
  `is_read`    tinyint(1) DEFAULT 0,
  `read_at`    timestamp NULL DEFAULT NULL,
  `sent_via`   enum('app','email','sms','all') DEFAULT 'app',
  `created_by` int(11) DEFAULT NULL,                        -- officer/admin user_id
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Real alert data shows:** Cold wave alerts, field visit notifications — each `user_id` row is one recipient's copy of the alert.

---

## Learning Center Tables

### `learn_categories`

```sql
CREATE TABLE `learn_categories` (
  `id`         int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name`       varchar(100) NOT NULL,
  `name_bn`    varchar(100) DEFAULT NULL,
  `icon`       varchar(60) NOT NULL DEFAULT 'menu_book',     -- Material Icons name
  `color`      varchar(20) NOT NULL DEFAULT '#557A46',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seeded categories:**

| id | name | name_bn | icon |
|----|------|---------|------|
| 1 | Video Tutorials | ভিডিও টিউটোরিয়াল | play_circle |
| 2 | Farming Blogs | কৃষি ব্লগ | article |
| 3 | Seasonal Guides | মৌসুমী গাইড | calendar_month |
| 4 | Expert Articles | বিশেষজ্ঞ নিবন্ধ | auto_stories |
| 5 | Live Webinars | লাইভ ওয়েবিনার | live_tv |
| 6 | Quizzes | কুইজ ও সার্টিফিকেট | quiz |

---

### `learn_content`

The main learning content table — covers 7 content types.

```sql
CREATE TABLE `learn_content` (
  `id`                   int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `type`                 enum('video','playlist','blog','guide','article','webinar','quiz') NOT NULL,
  `title`                varchar(255) NOT NULL,
  `title_bn`             varchar(255) DEFAULT NULL,
  `description`          text DEFAULT NULL,
  `thumbnail_url`        varchar(500) DEFAULT NULL,
  `category_id`          int(11) DEFAULT NULL,               -- FK learn_categories
  `season`               enum('all','boro','aman','aus','rabi','kharif') NOT NULL DEFAULT 'all',
  `crop_tags`            varchar(500) DEFAULT NULL,           -- e.g. 'rice,vegetable,wheat'
  `difficulty`           enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `duration_min`         int(11) DEFAULT NULL,               -- reading/viewing time in minutes
  `created_by`           int(11) NOT NULL,                   -- FK users (admin/officer)
  `is_published`         tinyint(1) NOT NULL DEFAULT 0,
  `is_featured`          tinyint(1) NOT NULL DEFAULT 0,
  `views`                int(11) NOT NULL DEFAULT 0,
  `content_body`         longtext DEFAULT NULL,              -- HTML body (blog/article/guide)
  `youtube_url`          varchar(500) DEFAULT NULL,          -- for video type
  `webinar_url`          varchar(500) DEFAULT NULL,          -- for webinar type
  `webinar_scheduled_at` datetime DEFAULT NULL,
  `pass_score`           int(11) NOT NULL DEFAULT 70,        -- quiz pass percentage
  `created_at`           timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`           timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Content types and their fields:**

| Type | Uses `content_body` | Uses `youtube_url` | Uses `webinar_url` | Uses `pass_score` |
|------|--------------------|--------------------|-------------------|------------------|
| `video` | — | Yes | — | — |
| `playlist` | — | — | — | — |
| `blog` | Yes | — | — | — |
| `guide` | Yes | — | — | — |
| `article` | Yes | — | — | — |
| `webinar` | — | — | Yes | — |
| `quiz` | — | — | — | Yes (default 70) |

---

### `learn_certificates`

Issued on quiz completion above `pass_score`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `user_id` | INT | FK users |
| `content_id` | INT | FK learn_content (quiz type) |
| `certificate_code` | VARCHAR(64) | e.g. `CERT-2024-U2-Q9-A1F3` |
| `score` | INT | percentage achieved |
| `issued_at` | TIMESTAMP | |

---

### `learn_likes`

User likes on learning content.

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | INT | |
| `content_id` | INT | FK learn_content |
| `liked_at` | TIMESTAMP | |

PK: `(user_id, content_id)`.

---

### `learn_paths`

Curated learning sequences.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `title` | VARCHAR(255) | |
| `description` | TEXT | |
| `crop_focus` | VARCHAR(100) | e.g. rice, vegetables |
| `created_by` | INT | FK users |
| `is_published` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

### `learn_path_items`

Content items within a learning path.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `path_id` | INT | FK learn_paths |
| `content_id` | INT | FK learn_content |
| `sort_order` | INT | |

### `learn_quiz_questions` / `learn_quiz_options`

Quiz system for assessable content:
- `learn_quiz_questions`: question text, correct_answer, points
- `learn_quiz_options`: option text, is_correct, question_id FK

---

## Marketplace Tables

### `marketplace_products`

Agricultural products listed for sale.

```sql
CREATE TABLE `marketplace_products` (
  `product_id`           int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `seller_id`            int(11) NOT NULL,                   -- FK users
  `product_name`         varchar(100) NOT NULL,
  `product_type`         enum('crop','seed','fertilizer','equipment','service','other')
                         DEFAULT 'crop',
  `category`             varchar(100) DEFAULT NULL,
  `description`          text DEFAULT NULL,
  `price`                decimal(10,2) NOT NULL,
  `price_unit`           varchar(20) DEFAULT 'kg',
  `quantity_available`   int(11) DEFAULT NULL,
  `unit`                 varchar(20) DEFAULT NULL,
  `quality_grade`        enum('A','B','C','standard') DEFAULT NULL,
  `location`             varchar(255) DEFAULT NULL,
  `district`             varchar(100) DEFAULT NULL,
  `region`               varchar(100) DEFAULT NULL,
  `image_url`            varchar(255) DEFAULT NULL,
  `images`               text DEFAULT NULL,                  -- JSON array of image URLs
  `contact_phone`        varchar(20) DEFAULT NULL,
  `contact_email`        varchar(255) DEFAULT NULL,
  `status`               enum('available','sold','pending','expired') DEFAULT 'available',
  `views`                int(11) DEFAULT 0,
  `is_featured`          tinyint(1) DEFAULT 0,
  `is_verified`          tinyint(1) DEFAULT 0,
  `verified_by`          int(11) DEFAULT NULL,
  `is_negotiable`        tinyint(1) DEFAULT 1,
  `min_order_quantity`   int(11) DEFAULT 1,
  `bulk_discount_percent` decimal(5,2) DEFAULT NULL,
  `bulk_min_quantity`    int(11) DEFAULT NULL,
  `average_rating`       decimal(3,2) DEFAULT 0.00,
  `review_count`         int(11) DEFAULT 0,
  `created_at`           timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`           timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at`           timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Real products:** Potato ৳15/kg, Tomatoes ৳30/kg, Prawn ৳800/kg, Cauliflower ৳10/kg, Rice ৳80/kg.

---

### `marketplace_orders`

```sql
CREATE TABLE `marketplace_orders` (
  `order_id`        int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `product_id`      int(11) NOT NULL,                        -- FK marketplace_products
  `seller_id`       int(11) NOT NULL,                        -- FK users
  `buyer_id`        int(11) NOT NULL,                        -- FK users
  `quantity`        int(11) NOT NULL,
  `total_price`     decimal(10,2) NOT NULL,
  `delivery_address` text DEFAULT NULL,
  `payment_method`  enum('cash','bkash','nagad','bank','other') DEFAULT 'cash',
  `order_status`    enum('pending','confirmed','delivered','cancelled') DEFAULT 'pending',
  `payment_status`  enum('pending','paid','refunded') DEFAULT 'pending',
  `notes`           text DEFAULT NULL,
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Payment methods:** cash, bkash, nagad, bank, other — reflects Bangladeshi payment ecosystem.

---

### `product_reviews`

| Column | Type | Notes |
|--------|------|-------|
| `review_id` | INT PK AI | |
| `product_id` | INT | FK marketplace_products |
| `user_id` | INT | FK users |
| `order_id` | INT | FK marketplace_orders |
| `rating` | TINYINT | 1–5 |
| `review_text` | TEXT | |
| `is_verified` | TINYINT(1) | verified purchase |
| `created_at` | TIMESTAMP | |

---

### `product_inquiries`

Pre-purchase product questions.

| Column | Type | Notes |
|--------|------|-------|
| `inquiry_id` | INT PK AI | |
| `product_id` | INT | FK marketplace_products |
| `buyer_id` | INT | FK users |
| `message` | TEXT | |
| `reply` | TEXT | seller's reply |
| `is_replied` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `seller_stats`

Denormalized seller performance metrics.

| Column | Type | Notes |
|--------|------|-------|
| `seller_id` | INT PK | FK users |
| `total_products` | INT | |
| `total_orders` | INT | |
| `total_revenue` | DECIMAL(12,2) | |
| `average_rating` | DECIMAL(3,2) | |
| `updated_at` | TIMESTAMP | |

---

## Market Data Tables

### `market_prices`

Real-time crop price data by location.

```sql
CREATE TABLE `market_prices` (
  `price_id`        int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `crop_name`       varchar(100) NOT NULL,
  `crop_type`       varchar(100) DEFAULT NULL,               -- grain, vegetable, fiber, pulse
  `variety`         varchar(100) DEFAULT NULL,
  `market_location` varchar(100) DEFAULT NULL,
  `district`        varchar(100) DEFAULT NULL,
  `region`          varchar(100) DEFAULT NULL,
  `price_per_unit`  decimal(10,2) NOT NULL,
  `unit_type`       varchar(20) DEFAULT 'kg',
  `min_price`       decimal(10,2) DEFAULT NULL,
  `max_price`       decimal(10,2) DEFAULT NULL,
  `avg_price`       decimal(10,2) DEFAULT NULL,
  `quality_grade`   enum('A','B','C','standard') DEFAULT 'standard',
  `demand_level`    enum('low','medium','high') DEFAULT 'medium',
  `supply_level`    enum('low','medium','high') DEFAULT 'medium',
  `recorded_date`   timestamp NOT NULL DEFAULT current_timestamp(),
  `price_date`      date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**20 seeded price records** covering: Rice (৳52/kg), Wheat (৳48/kg), Onion (৳45/kg), Potato (৳35/kg), Tomato (৳60/kg), Jute (৳120/kg), Chili (৳120/kg), Turmeric (৳320/kg), and 12 more crops across 8 divisions.

---

## Shop Module Tables

### `shop_cart`

```sql
CREATE TABLE `shop_cart` (
  `cart_id`    int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    int(11) NOT NULL,                             -- FK users
  `product_id` int(11) NOT NULL,                            -- FK marketplace_products
  `quantity`   int(11) NOT NULL DEFAULT 1,
  `added_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY unique_cart_item (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `shop_orders`

| Column | Type | Notes |
|--------|------|-------|
| `order_id` | INT PK AI | |
| `buyer_id` | INT | FK users |
| `total_amount` | DECIMAL(10,2) | |
| `status` | ENUM | pending, confirmed, shipped, delivered, cancelled |
| `delivery_address` | TEXT | |
| `delivery_phone` | VARCHAR(20) | |
| `payment_method` | ENUM | cash, bkash, nagad, bank |
| `notes` | TEXT | |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | ON UPDATE |

---

### `shop_order_items`

| Column | Type | Notes |
|--------|------|-------|
| `item_id` | INT PK AI | |
| `order_id` | INT | FK shop_orders |
| `product_id` | INT | FK marketplace_products |
| `seller_id` | INT | FK users (seller) |
| `quantity` | INT | |
| `unit_price` | DECIMAL(10,2) | Price at order time |
| `total_price` | DECIMAL(10,2) | |

---

### `shop_conversations`

Buyer–seller conversation threads.

| Column | Type | Notes |
|--------|------|-------|
| `conversation_id` | INT PK AI | |
| `buyer_id` | INT | FK users |
| `seller_id` | INT | FK users |
| `product_id` | INT | nullable FK marketplace_products |
| `last_message_at` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |

---

### `shop_messages`

Individual messages within conversations.

| Column | Type | Notes |
|--------|------|-------|
| `message_id` | INT PK AI | |
| `conversation_id` | INT | FK shop_conversations |
| `sender_id` | INT | FK users |
| `message` | TEXT | |
| `is_read` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `shop_settings`

Marketplace configuration key-value store.

| Column | Type | Notes |
|--------|------|-------|
| `setting_id` | INT PK AI | |
| `setting_key` | VARCHAR(100) UNIQUE | |
| `setting_value` | TEXT | |
| `updated_at` | TIMESTAMP | |

---

## Admin & Security Tables

### `admin_activity_logs`

Every admin action is logged here.

```sql
CREATE TABLE `admin_activity_logs` (
  `log_id`          int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`         int(11) NOT NULL,
  `action`          varchar(100) NOT NULL,                   -- e.g. 'admin_login', 'delete_user'
  `action_category` enum('user','security','system','content','settings','data','backup','report')
                    NOT NULL DEFAULT 'system',
  `entity_type`     varchar(50) DEFAULT NULL,
  `entity_id`       int(11) DEFAULT NULL,
  `old_value`       text DEFAULT NULL,                       -- JSON snapshot before change
  `new_value`       text DEFAULT NULL,                       -- JSON snapshot after change
  `ip_address`      varchar(45) DEFAULT NULL,
  `user_agent`      text DEFAULT NULL,
  `risk_level`      enum('low','medium','high','critical') DEFAULT 'low',
  `created_at`      timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**97 real log entries** covering: logins, user creation/deletion, role changes, report generation, backups, IP rule changes.

---

### `admin_ip_rules`

IP whitelist/blacklist/geoblock management.

```sql
CREATE TABLE `admin_ip_rules` (
  `rule_id`       int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ip_address`    varchar(45) NOT NULL,
  `ip_range_start` varchar(45) DEFAULT NULL,
  `ip_range_end`   varchar(45) DEFAULT NULL,
  `rule_type`     enum('whitelist','blacklist','geoblock') NOT NULL,
  `country_code`  varchar(2) DEFAULT NULL,                   -- for geoblock rules
  `reason`        text DEFAULT NULL,
  `auto_created`  tinyint(1) DEFAULT 0,                      -- auto-added by brute-force detection
  `created_by`    int(11) DEFAULT NULL,
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at`    timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `admin_2fa_tokens`

Two-factor authentication tokens for admin login.

```sql
CREATE TABLE `admin_2fa_tokens` (
  `token_id`   int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    int(11) NOT NULL,
  `token`      varchar(6) NOT NULL,                          -- 6-digit OTP
  `token_type` enum('email','totp','sms','backup') DEFAULT 'email',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `used`       tinyint(1) DEFAULT 0,
  `used_at`    timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `admin_2fa_backup_codes`

One-time backup codes for 2FA recovery.

| Column | Type | Notes |
|--------|------|-------|
| `code_id` | INT PK AI | |
| `user_id` | INT | FK users |
| `code_hash` | VARCHAR(255) | bcrypt hash of backup code |
| `used` | TINYINT(1) | |
| `used_at` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |

---

### `admin_login_attempts`

Brute-force login attempt tracking.

| Column | Type | Notes |
|--------|------|-------|
| `attempt_id` | INT PK AI | |
| `email` | VARCHAR(255) | attempted email |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | TEXT | |
| `success` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `admin_sessions`

Admin panel session tracking (separate from main user sessions).

| Column | Type | Notes |
|--------|------|-------|
| `session_id` | VARCHAR(128) PK | |
| `user_id` | INT | FK users (admin) |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | TEXT | |
| `device_fingerprint` | VARCHAR(255) | |
| `is_trusted` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |
| `last_activity` | TIMESTAMP | |

---

### `admin_trusted_devices`

Devices that have passed 2FA (skip 2FA on next login).

| Column | Type | Notes |
|--------|------|-------|
| `device_id` | INT PK AI | |
| `user_id` | INT | FK users |
| `device_fingerprint` | VARCHAR(255) | |
| `device_name` | VARCHAR(255) | |
| `trusted_at` | TIMESTAMP | |
| `expires_at` | TIMESTAMP | |

---

### `admin_settings`

Admin panel configuration stored as key-value pairs.

| Column | Type | Notes |
|--------|------|-------|
| `setting_id` | INT PK AI | |
| `setting_key` | VARCHAR(100) UNIQUE | |
| `setting_value` | TEXT | |
| `setting_type` | VARCHAR(50) | string, boolean, integer, json |
| `description` | TEXT | |
| `is_editable` | TINYINT(1) | |
| `updated_at` | TIMESTAMP | |

---

### `generated_reports`

Admin-generated report file metadata.

| Column | Type | Notes |
|--------|------|-------|
| `report_id` | INT PK AI | |
| `report_name` | VARCHAR(255) | |
| `report_type` | VARCHAR(50) | user_summary, security_audit, etc. |
| `format` | VARCHAR(10) | csv, pdf, xlsx |
| `file_path` | VARCHAR(500) | relative path to file |
| `file_name` | VARCHAR(255) | download filename |
| `date_from` | DATE | |
| `date_to` | DATE | |
| `generated_by` | INT | FK users (admin) |
| `created_at` | TIMESTAMP | |

---

### `scheduled_reports`

Recurring report generation schedules.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `report_name` | VARCHAR(255) | |
| `report_type` | VARCHAR(50) | |
| `format` | VARCHAR(10) | |
| `frequency` | ENUM | daily, weekly, monthly, quarterly |
| `is_active` | TINYINT(1) | |
| `notify_email` | VARCHAR(150) | |
| `created_by` | INT | FK users |
| `last_run` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |

---

### `scheduled_tasks`

System-level cron tasks with admin control.

| Column | Type | Notes |
|--------|------|-------|
| `task_id` | INT PK AI | |
| `task_name` | VARCHAR(100) | |
| `description` | TEXT | |
| `task_type` | VARCHAR(50) | report, backup, cleanup, notify |
| `schedule` | VARCHAR(50) | cron expression |
| `is_active` | TINYINT(1) | |
| `last_run` | TIMESTAMP | |
| `next_run` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |

---

## System & Infrastructure Tables

### `security_events`

| Column | Type | Notes |
|--------|------|-------|
| `event_id` | INT PK AI | |
| `event_type` | VARCHAR(100) | failed_login, csrf_violation, brute_force |
| `severity` | ENUM | low, medium, high, critical |
| `ip_address` | VARCHAR(45) | |
| `user_id` | INT | nullable |
| `details` | JSON | |
| `is_acknowledged` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `error_logs`

Server-side error logging.

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `severity` | ENUM | error, critical, warning, info |
| `message` | TEXT | |
| `file` | VARCHAR(500) | |
| `line` | INT | |
| `context` | JSON | |
| `is_resolved` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

### `email_queue`

Asynchronous email sending queue.

| Column | Type | Notes |
|--------|------|-------|
| `queue_id` | INT PK AI | |
| `to_email` | VARCHAR(255) | |
| `subject` | VARCHAR(255) | |
| `body_html` | TEXT | |
| `template` | VARCHAR(100) | template name |
| `template_data` | JSON | variables for template |
| `priority` | ENUM | low, normal, high, urgent |
| `status` | ENUM | pending, sending, sent, failed, cancelled |
| `attempts` | INT | retry count |
| `max_attempts` | INT | default 3 |
| `scheduled_at` | TIMESTAMP | |
| `sent_at` | TIMESTAMP | |

---

### `api_request_logs`

API performance monitoring.

| Column | Type | Notes |
|--------|------|-------|
| `log_id` | INT PK AI | |
| `endpoint` | VARCHAR(255) | |
| `method` | VARCHAR(10) | GET, POST, etc. |
| `response_code` | INT | |
| `response_time_ms` | INT | |
| `request_size` | INT | bytes |
| `response_size` | INT | bytes |
| `memory_usage` | INT | bytes |
| `user_id` | INT | |
| `ip_address` | VARCHAR(45) | |
| `created_at` | TIMESTAMP | |

---

### `backup_records`

Database backup history.

| Column | Type | Notes |
|--------|------|-------|
| `backup_id` | INT PK AI | |
| `file_name` | VARCHAR(255) | |
| `file_size` | BIGINT | bytes |
| `backup_type` | ENUM | full, incremental |
| `status` | ENUM | success, failed |
| `triggered_by` | INT | FK users (admin) |
| `notes` | TEXT | |
| `created_at` | TIMESTAMP | |

---

### `rate_limits`

Application-level rate limiting (augments session-based limits).

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK AI | |
| `identifier` | VARCHAR(255) | user_id or IP |
| `action` | VARCHAR(100) | e.g. 'agent_send', 'login' |
| `attempts` | INT | |
| `window_start` | TIMESTAMP | |
| `blocked_until` | TIMESTAMP | |

---

## Naming Conventions

| Convention | Pattern | Example |
|-----------|---------|---------|
| Primary key | `{table_singular}_id` | `user_id`, `post_id`, `crop_id` |
| Foreign key | Same name as referenced PK | `user_id → users.user_id` |
| Boolean flags | `is_{adjective}` TINYINT(1) | `is_active`, `is_verified`, `is_read` |
| Password storage | `password_hash` (never `password`) | bcrypt |
| Profile image | `profile_img_url` (varchar path) | `uploads/profiles/profile_49_...png` |
| Status fields | ENUM with explicit values | `status enum('pending','confirmed','delivered')` |
| Timestamps | Always `created_at` + `updated_at` | `ON UPDATE current_timestamp()` |
| Soft delete | `is_approved = 0` (posts) / separate `user_bans` table | Context-dependent |
| JSON fields | LONGTEXT with `CHECK (json_valid(...))` | `images`, `settings`, `details` |
| Agent IDs | `conversation_id varchar(64)` hex strings | `253ba0e2ee6cefe4f9bdea3451cc2d22` |
