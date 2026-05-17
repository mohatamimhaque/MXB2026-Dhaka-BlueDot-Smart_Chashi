# API Reference

> Smart Chashi uses session-based authentication for all API endpoints. The browser must carry a valid PHP session cookie. All AJAX endpoints return JSON. REST-style endpoints accept JSON bodies; form handlers accept `multipart/form-data` or URL-encoded POST.

---

## Table of Contents

1. [Authentication](#authentication)
2. [Response Format](#response-format)
3. [HTTP Status Codes](#http-status-codes)
4. [Rate Limiting](#rate-limiting)
5. [Authentication API](#authentication-api)
6. [Agent / AI Chat API](#agent--ai-chat-api)
7. [Disease Detection API](#disease-detection-api)
8. [Community API](#community-api)
9. [Alerts API](#alerts-api)
10. [Dashboard API](#dashboard-api)
11. [Crops API](#crops-api)
12. [Reports API (User)](#reports-api-user)
13. [Admin Reports API](#admin-reports-api)
14. [Admin Users API](#admin-users-api)
15. [Admin Notifications API](#admin-notifications-api)
16. [Database Class Quick Reference](#database-class-quick-reference)

---

## Authentication

All endpoints require an active PHP session (`$_SESSION['user_id']` must be set), established via the login flow.

```
POST ajax/auth.php (action=login)
    → sets $_SESSION['user_id'], $_SESSION['user_role']
    → all subsequent requests to other endpoints succeed if session cookie sent
```

No API keys, no JWT, no Bearer tokens for browser clients. The session cookie is automatically sent by the browser.

**For server-to-server calls** (e.g., mobile app), use the token-based endpoints under `api/auth/`:
```
POST api/auth/login.php   → returns session token
```

---

## Response Format

All AJAX endpoints return JSON with a consistent envelope:

**Success:**
```json
{
  "success": true,
  "data": { ... }
}
```

**Error:**
```json
{
  "success": false,
  "message": "Human-readable error description"
}
```

Some endpoints return fields directly at the root level (e.g., `reply`, `conversation_id`) without a `data` wrapper. These are documented individually below.

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| `200` | Success |
| `400` | Bad request / validation error |
| `401` | Not authenticated (no session) |
| `403` | Forbidden (wrong role / invalid CSRF token) |
| `404` | Resource not found |
| `429` | Rate limit exceeded |
| `500` | Internal server error |

---

## Rate Limiting

| Endpoint | Limit | Scope |
|----------|-------|-------|
| `agent/api/send.php` | 30 requests per 60 seconds | Per PHP session |
| All others | No application-level limit | Provider quota applies |

Rate limit response:
```json
{
  "success": false,
  "message": "Rate limit exceeded. Please wait before sending another message.",
  "retry_after": 60
}
```

---

## Authentication API

### `POST ajax/auth.php`

**Auth required:** No

| `action` Value | Required Fields | Response |
|----------------|----------------|----------|
| `login` | `email`, `password` | `{success, message, redirect}` |
| `register` | `first_name`, `last_name`, `email`, `password`, `role` | `{success, message}` |
| `logout` | — | Destroys session, redirects to `/login` |
| `forgot_password` | `email` | `{success, message}` |

**Login request:**
```
POST ajax/auth.php
Content-Type: application/x-www-form-urlencoded

action=login&email=user@example.com&password=secret
```

**Login response (success):**
```json
{
  "success": true,
  "message": "Login successful",
  "redirect": "/smartchashi/farmer-dashboard"
}
```

**Login response (failure):**
```json
{
  "success": false,
  "message": "Invalid email or password"
}
```

**Register fields:**

| Field | Type | Validation |
|-------|------|-----------|
| `first_name` | string | Required, 2–100 chars |
| `last_name` | string | Optional |
| `email` | string | Required, must be unique, valid email format |
| `password` | string | Required, min 6 chars — stored as `password_hash` (bcrypt) in DB |
| `role` | enum | `farmer` or `officer` |

---

## Agent / AI Chat API

### `POST agent/api/send.php`

Send a user message and receive an AI response from Chashi Bhai.

**Auth required:** Yes  
**Content-Type:** `application/json`  
**Rate limit:** 30 requests per 60 seconds per session

**Request body:**
```json
{
  "conversation_id": "abc123def456",
  "message": "আমার ধানে পাতার মড়ক রোগ হয়েছে, কী করব?",
  "location": "Rajshahi, Bangladesh",
  "personality": "pest",
  "lang": "bn"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `conversation_id` | string | No | Existing conversation ID. Omit to start a new conversation. |
| `message` | string | Yes | User's message text |
| `location` | string | No | User's location (default: "Bangladesh") |
| `personality` | enum | No | `general` \| `pest` \| `soil` \| `market` \| `weather` |
| `lang` | enum | No | `en` \| `bn`. Omit for auto-detection from message content. |

**Personality modes:**

| Mode | Focus | System Prompt Tone |
|------|-------|-------------------|
| `general` | All agricultural topics | Balanced farming advisor |
| `pest` | Pest and disease management | Crop protection specialist |
| `soil` | Soil health and fertilizer | Soil science expert |
| `market` | Prices and market conditions | Agricultural economist |
| `weather` | Weather impacts on crops | Agro-meteorologist |

**Response (success):**
```json
{
  "success": true,
  "reply": "<p>ধান পাতার মড়ক রোগের জন্য...</p>",
  "detectedLang": "bn",
  "conversation_id": "abc123def456",
  "title": "ধানের পাতার মড়ক রোগ",
  "msg_id": 42,
  "followUps": [
    "কোন ছত্রাকনাশক সবচেয়ে কার্যকর?",
    "রোগটি কি ছড়িয়ে পড়তে পারে?",
    "প্রতিরোধে কী করা উচিত?"
  ]
}
```

| Response Field | Type | Description |
|---------------|------|-------------|
| `reply` | string (HTML) | AI response, markdown converted to HTML |
| `detectedLang` | string | `en` or `bn` — language used in response |
| `conversation_id` | string | The conversation ID (new or existing) |
| `title` | string | Auto-generated conversation title (first message only) |
| `msg_id` | int | DB ID of the saved assistant message |
| `followUps` | array | 3 suggested follow-up questions |

**Response (rate limited):**
```json
{
  "success": false,
  "message": "Rate limit exceeded. Please wait before sending another message."
}
```

---

### `POST agent/api/conversations.php`

Manage chat conversations and AI memory.

**Auth required:** Yes  
**Content-Type:** `application/json`

All requests include an `action` field.

#### `list` — Get all conversations
```json
{ "action": "list" }
```
Returns last 100 conversations ordered by `updated_at DESC`:
```json
{
  "success": true,
  "conversations": [
    {
      "conversation_id": "abc123",
      "title": "ধানের রোগ",
      "updated_at": "2025-05-17 14:23:01",
      "message_count": 8
    }
  ]
}
```

#### `new` — Create a new conversation
```json
{ "action": "new" }
```
```json
{
  "success": true,
  "conversation_id": "newid789"
}
```

#### `load` — Load conversation with messages
```json
{ "action": "load", "conversation_id": "abc123" }
```
```json
{
  "success": true,
  "conversation": {
    "conversation_id": "abc123",
    "title": "ধানের রোগ",
    "created_at": "2025-05-17 10:00:00"
  },
  "messages": [
    { "id": 1, "role": "user", "content": "...", "created_at": "..." },
    { "id": 2, "role": "assistant", "content": "<p>...</p>", "feedback": null, "created_at": "..." }
  ]
}
```

#### `rename` — Rename a conversation
```json
{ "action": "rename", "conversation_id": "abc123", "title": "নতুন শিরোনাম" }
```

#### `delete` — Delete a conversation
```json
{ "action": "delete", "conversation_id": "abc123" }
```
Deletes all messages and the conversation record.

#### `feedback` — Rate an AI message
```json
{ "action": "feedback", "message_id": 42, "value": 1 }
```

| `value` | Meaning |
|---------|---------|
| `1` | Helpful (thumbs up) |
| `-1` | Not helpful (thumbs down) |
| `0` | Clear rating |

#### `memory_list` — Get AI memory entries
```json
{ "action": "memory_list" }
```
```json
{
  "success": true,
  "memory": [
    { "id": 1, "memory_key": "user_district", "memory_value": "Rajshahi", "source": "auto", "updated_at": "..." },
    { "id": 2, "memory_key": "farm_size", "memory_value": "5 bigha", "source": "manual", "updated_at": "..." }
  ]
}
```

#### `memory_save` — Add or update a memory item
```json
{ "action": "memory_save", "key": "farm_size", "value": "10 bigha" }
```
Uses `INSERT ... ON DUPLICATE KEY UPDATE` — upsert on `(user_id, memory_key)`.  
Key max: 100 chars. Value max: 500 chars.

#### `memory_delete` — Delete one memory item
```json
{ "action": "memory_delete", "id": 5 }
```

#### `memory_clear` — Delete all memory for current user
```json
{ "action": "memory_clear" }
```

---

## Disease Detection API

### `POST api/disease/analyze.php`

Upload a crop photo for AI-powered disease identification using Google Gemini Vision.

**Auth required:** Yes  
**Content-Type:** `multipart/form-data`

**Request fields:**

| Field | Type | Required | Constraints |
|-------|------|----------|------------|
| `image` | file | Yes | JPEG or PNG, max 50 MB |
| `crop_type` | string | No | e.g., `rice`, `wheat`, `tomato`, `potato` |

**Response (success):**
```json
{
  "success": true,
  "disease": "Rice Blast (Magnaporthe oryzae)",
  "confidence": 0.92,
  "description": "A fungal disease causing diamond-shaped lesions on leaves...",
  "treatment": "Apply tricyclazole or isoprothiolane fungicide...",
  "prevention": "Use disease-resistant varieties, maintain proper water management...",
  "severity": "moderate",
  "image_url": "/smartchashi/uploads/disease_1234567890.jpg"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `disease` | string | Disease name (English + scientific name if available) |
| `confidence` | float | AI confidence score 0.0–1.0 |
| `description` | string | Disease description |
| `treatment` | string | Recommended treatment steps |
| `prevention` | string | Prevention measures |
| `severity` | enum | `low` \| `moderate` \| `high` \| `severe` |
| `image_url` | string | URL of uploaded image (saved in `uploads/`) |

**Response (invalid image):**
```json
{
  "success": false,
  "message": "Invalid file type. Only JPEG and PNG images are accepted."
}
```

**Response (file too large):**
```json
{
  "success": false,
  "message": "File size exceeds the 50 MB limit."
}
```

**What it accepts:**
- JPEG, PNG images
- Any crop or plant photo
- Minimum image quality: photo must be in focus and show the affected area

**What it rejects:**
- Non-image files (PDF, video, etc.)
- Files over 50 MB
- Images with no plant content (AI returns low confidence)

---

## Community API

### `POST ajax/community.php`

**Auth required:** Partially (read = no, write = yes)  
**Content-Type:** `application/x-www-form-urlencoded`

| `action` | Auth | Description |
|----------|------|-------------|
| `get_posts` | No | Paginated post list with pagination metadata |
| `get_post` | No | Single post with all comments |
| `create_post` | Yes | Create a new community post |
| `add_comment` | Yes | Add comment to a post |
| `like_post` | Yes | Toggle like on a post |
| `report_post` | Yes | Flag a post as inappropriate |
| `delete_post` | Yes (owner or admin) | Soft-delete a post |

**get_posts request:**
```
action=get_posts&page=1&per_page=10&category=pest
```

**get_posts response:**
```json
{
  "success": true,
  "posts": [
    {
      "post_id": 12,
      "user_id": 5,
      "author_name": "Rahman Ali",
      "title": "টমেটোর সাদামাছি",
      "content": "...",
      "category": "pest",
      "likes": 7,
      "comments": 3,
      "created_at": "2025-05-15 09:30:00"
    }
  ],
  "total": 47,
  "page": 1,
  "pages": 5
}
```

**create_post request:**
```
action=create_post&title=...&content=...&category=pest&csrf_token=...
```

---

## Alerts API

### `POST ajax/alerts.php`

**Auth required:** Yes

| `action` | Role | Description |
|----------|------|-------------|
| `get_alerts` | Any | User's alert list (paginated) |
| `get_unread_count` | Any | Badge count (integer) |
| `create_alert` | officer / admin | Post a new alert to one or more users |
| `mark_read` | Any | Mark a single alert as read |
| `mark_all_read` | Any | Mark all alerts as read |

The `alerts` table uses a **per-recipient row model** — each alert row belongs to exactly one `user_id`. When an officer broadcasts to a district, the system inserts one row per recipient.

**get_unread_count response:**
```json
{
  "success": true,
  "count": 3
}
```

**create_alert request:**
```
action=create_alert
&title=ধান পাতার মড়ক রোগের সতর্কতা
&message=Rajshahi district farmers should apply fungicide...
&alert_type=disease
&priority=high
&sent_via=app
&csrf_token=...
```

**Alert field values (from DB schema):**

| Field | Type | Values |
|-------|------|--------|
| `alert_type` | enum | `weather` \| `disease` \| `market` \| `system` \| `advisory` \| `crop` \| `community` |
| `priority` | enum | `low` \| `medium` \| `high` \| `critical` |
| `sent_via` | enum | `app` \| `email` \| `sms` \| `all` |

**Alert object (in list response):**
```json
{
  "alert_id": 111,
  "user_id": 50,
  "alert_type": "weather",
  "title": "Cold Wave",
  "message": "There will be a cold wave from the 22nd to the 26th",
  "priority": "critical",
  "category": "Weather",
  "action_url": null,
  "is_read": false,
  "sent_via": "app",
  "created_by": 70,
  "created_at": "2026-01-15 11:09:29",
  "expires_at": null
}
```

---

## Dashboard API

### `GET ajax/get-dashboard-stats.php`

**Auth required:** Yes

Returns KPI summary for the farmer dashboard widget:

```json
{
  "success": true,
  "crops": 5,
  "upcoming_tasks": 2,
  "unread_alerts": 1,
  "weather": {
    "temp": 32,
    "condition": "Partly Cloudy",
    "humidity": 78
  }
}
```

### `GET ajax/get-upcoming-tasks.php`

**Auth required:** Yes

```json
{
  "success": true,
  "tasks": [
    {
      "task_id": 3,
      "title": "Apply fertilizer to rice field",
      "due_date": "2025-05-20",
      "status": "pending"
    }
  ]
}
```

### `GET ajax/get-recent-crops.php`

**Auth required:** Yes

```json
{
  "success": true,
  "crops": [
    {
      "crop_id": 45,
      "crop_name": "Rice",
      "variety": "BRRI dhan28",
      "area_hectares": 3.0,
      "status": "growing",
      "planted_date": "2026-01-08",
      "expected_harvest": "2026-05-15"
    }
  ]
}
```

---

## Crops API

### Endpoints under `api/crop/`

**Auth required:** Yes  
**Content-Type:** `application/json`  
**DB table:** `crop_data` (keyed by `farmer_id`)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api/crop/get-crops.php` | GET | All crops for current user |
| `api/crop/get-crop.php?id=1` | GET | Single crop detail |
| `api/crop/add-crop.php` | POST | Create new crop record |
| `api/crop/update-crop.php` | POST | Update crop fields |
| `api/crop/update-status.php` | POST | Change crop growth status |
| `api/crop/delete-crop.php` | POST | Delete a crop record |

**add-crop request (real `crop_data` column names):**
```json
{
  "crop_name": "Boro Rice",
  "crop_type": "grain",
  "variety": "BRRI dhan28",
  "planted_date": "2026-01-08",
  "expected_harvest": "2026-05-15",
  "area_hectares": 3.0,
  "field_location": "North field, near irrigation canal",
  "expected_yield": 12.5,
  "notes": "Main field"
}
```

**Crop status values (from `crop_data.status` ENUM):** `planning` | `growing` | `harvesting` | `harvested` | `completed` | `failed`

**Crop object fields:**

| Field | Type | Description |
|-------|------|-------------|
| `crop_id` | int | Primary key |
| `farmer_id` | int | Owner (references `users.user_id`) |
| `crop_name` | varchar(100) | Crop name |
| `crop_type` | varchar(100) | Category: grain, vegetable, fruit, etc. |
| `variety` | varchar(100) | Cultivar or variety name |
| `planted_date` | date | Date sown |
| `expected_harvest` | date | Target harvest date |
| `actual_harvest_date` | date | Actual harvest date (nullable) |
| `area_hectares` | decimal | Field area in hectares |
| `field_location` | varchar(255) | Human-readable location |
| `status` | enum | Current growth stage |
| `expected_yield` | decimal | Estimated yield in tons |
| `actual_yield` | decimal | Post-harvest actual yield |
| `notes` | text | Free-form notes |

---

## Reports API (User)

### `POST ajax/export-reports.php`

Export a farmer's own crop/activity reports.

**Auth required:** Yes

| `action` | Description | Response |
|----------|-------------|----------|
| `export_csv` | Download user's data as CSV | Streamed file download |
| `export_pdf` | Generate PDF summary | Streamed file download |

**Request:**
```
action=export_csv&date_from=2025-01-01&date_to=2025-05-17&csrf_token=...
```

---

## Admin Reports API

### `POST admin-secure/ajax/reports.php`

**Auth required:** admin role  
**CSRF token required:** Yes (must include `csrf_token` in every POST body)

All requests must include:
```
csrf_token: <token from window.CSRF_TOKEN>
```

| `action` | Description |
|----------|-------------|
| `generate_report` | Generate a new report file |
| `download_report` | Stream file download |
| `preview_report` | Return parsed data for in-browser preview |
| `delete_report` | Delete DB record + file from disk |
| `create_scheduled_report` | Create recurring schedule |
| `toggle_scheduled_report` | Enable / disable a schedule |
| `delete_scheduled_report` | Remove a schedule |
| `get_stats` | Summary: count, storage, active schedules |

**generate_report request:**
```
action=generate_report
&report_type=user_summary
&format=csv
&date_from=2025-05-01
&date_to=2025-05-17
&report_name=May User Summary
&csrf_token=...
```

**generate_report response:**
```json
{
  "success": true,
  "report_id": 15,
  "file_name": "user_summary_20250517.csv",
  "message": "Report generated successfully"
}
```

**preview_report response:**
```json
{
  "success": true,
  "metrics": {
    "total_users": 342,
    "new_this_period": 28,
    "farmers": 290,
    "officers": 48,
    "admins": 4
  },
  "rows": [
    { "date": "2025-05-01", "new_registrations": 3, "role": "farmer" },
    ...
  ],
  "total_rows": 47
}
```

**Report type values:**
`user_summary` | `security_audit` | `activity_log` | `content_analytics` | `system_health` | `financial` | `ai_usage`

**Format values:** `csv` | `pdf` | `xlsx`

---

## Admin Users API

### `POST admin-secure/ajax/users.php`

**Auth required:** admin role  
**CSRF token required:** Yes

| `action` | Description | Required Fields |
|----------|-------------|----------------|
| `list` | Paginated user list with filters | `page`, `search` (opt), `role` (opt) |
| `ban` | Ban a user (`is_active = 0`) | `user_id` |
| `unban` | Re-activate a user | `user_id` |
| `change_role` | Update user role | `user_id`, `role` |
| `delete` | Hard-delete user and related data | `user_id` |
| `get_user` | Single user detail + stats | `user_id` |

---

## Admin Notifications API

### `POST admin-secure/ajax/notifications.php`

**Auth required:** admin role  
**CSRF token required:** Yes

| `action` | Description | Required Fields |
|----------|-------------|----------------|
| `send` | Send a notification | `title`, `message`, `type`, `target` |
| `list` | All sent notifications | — |
| `delete` | Remove a notification | `id` |

**send request:**
```json
{
  "action": "send",
  "title": "System Maintenance",
  "message": "The platform will be offline 2-4 AM tonight.",
  "type": "warning",
  "target": "all",
  "csrf_token": "..."
}
```

**target values:** `all` | `farmers` | `officers` | `user:{id}` (e.g., `user:42`)  
**type values:** `info` | `warning` | `alert`

---

## Database Class Quick Reference

The `Database` class is available everywhere after `config/config.php` is included:

```php
$db = new Database();

// Fetch single row — returns associative array or false
$row = $db->single("SELECT * FROM users WHERE user_id = ?", [$id]);

// Fetch all rows — returns array of associative arrays
$rows = $db->resultSet("SELECT * FROM users ORDER BY created_at DESC LIMIT 20");

// Mutate — fluent interface with bind()
$db->query("UPDATE users SET role = ? WHERE user_id = ?")
   ->bind(1, $role)
   ->bind(2, $id)
   ->execute();

// Insert — get new row ID
$db->query("INSERT INTO table (col) VALUES (?)")
   ->bind(1, $value)
   ->execute();
$newId = $db->lastInsertId();

// Typed bind (for NULL / INT)
->bind(1, null, PDO::PARAM_NULL)
->bind(2, $intValue, PDO::PARAM_INT)

// Row count after SELECT
$db->rowCount();
```

All SQL is executed via **prepared statements** — no string interpolation, no SQL injection risk.
