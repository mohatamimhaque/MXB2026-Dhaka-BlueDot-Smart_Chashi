# Chashi Bhai AI Agent — Technical Documentation

> **Version:** 3.0 | **Last updated:** 2026-05-18
> **Base URL:** `/agent/chat` | **API root:** `/agent/api/`

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture](#2-architecture)
3. [Dual-Path Processing Model](#3-dual-path-processing-model)
4. [Case 1 — Image Pipeline](#4-case-1--image-pipeline)
5. [Case 2 — Text Pipeline](#5-case-2--text-pipeline)
6. [Shared Processing (Both Paths)](#6-shared-processing-both-paths)
7. [Intelligence Layers](#7-intelligence-layers)
8. [What the Agent Accepts](#8-what-the-agent-accepts)
9. [What the Agent Rejects](#9-what-the-agent-rejects)
10. [Frontend Features](#10-frontend-features)
11. [Memory System](#11-memory-system)
12. [Personality Modes](#12-personality-modes)
13. [Language Handling](#13-language-handling)
14. [API Reference](#14-api-reference)
15. [Database Schema](#15-database-schema)
16. [File Structure](#16-file-structure)
17. [Configuration & Environment](#17-configuration--environment)
18. [Security & Rate Limiting](#18-security--rate-limiting)
19. [Error Handling](#19-error-handling)
20. [Keyboard Shortcuts](#20-keyboard-shortcuts)
- [Appendix A: Request Flow Diagram](#appendix-a-request-flow-diagram)
- [Appendix B: Markdown Rendering](#appendix-b-markdown-rendering)
- [Appendix C: Disease Detection Service](#appendix-c-disease-detection-service)

---

## 1. Overview

**Chashi Bhai** is a full-featured, ChatGPT-style AI assistant embedded in Smart Chashi, purpose-built for Bangladesh agriculture. It answers questions about crops, pests, diseases, soil health, weather, irrigation, market prices, and government programs — in Bengali and English.

As of v3, the backend runs a **dual-path architecture**: image submissions and text-only messages follow completely separate processing pipelines, each with a tailored system prompt and AI context.

### Core Design Principles

| Principle | Implementation |
|-----------|---------------|
| Bangladesh-first | All knowledge, crop calendars, and BRRI/BARI data are Bangladesh-specific |
| Bilingual | Detects Bengali/English automatically; can be forced to either |
| Dual-path intelligence | Image path → Disease API → structured diagnosis prompt; Text path → RAG/weather/web layers |
| Personalized | Persistent memory across sessions; adapts to each farmer's profile |
| Graceful degradation | Primary AI → Railway fallback → cached response → error message |
| Farming domain guard | Rejects non-crop image uploads; guides off-topic messages back to agriculture |

---

## 2. Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        BROWSER (chat.php)                           │
│                                                                     │
│  ┌──────────┐   ┌────────────────────┐   ┌──────────────────────┐  │
│  │ Sidebar  │   │   Chat Messages    │   │   Input Area         │  │
│  │ Conv list│   │   Typing indicator │   │   Textarea + Actions │  │
│  │ Search   │   │   Follow-up chips  │   │   Image strip        │  │
│  │ Memory   │   │   Image lightbox   │   │   Voice / Emoji      │  │
│  └──────────┘   └────────────────────┘   └──────────────────────┘  │
│                           │ fetch() POST                            │
└───────────────────────────┼─────────────────────────────────────────┘
                            │ POST /agent/api/send.php
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      send.php (Backend v2)                          │
│                                                                     │
│  Input parse → queryType detection → Rate limit                     │
│                                                                     │
│    queryType === 'image' / 'image_with_text'                        │
│        │  Validate → Save to disk → Disease API (sequential)        │
│        │  Build image-optimised prompt                              │
│        ▼                                                            │
│    queryType === 'text'                                             │
│        │  Cache? → Greeting? → RAG + Weather + Web search          │
│        │  Build agricultural advisor prompt                         │
│        ▼                                                            │
│    SHARED: AI call → Fallback → Format → Persist → Title + Chips   │
│       ↓                                                             │
│  JSON response → Browser                                            │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
              ┌─────────────┼──────────────────┬──────────────┐
              ▼             ▼                  ▼              ▼
        ┌──────────┐  ┌──────────┐    ┌──────────────┐  ┌──────────┐
        │ MySQL DB │  │ AI Provider│  │ Disease API  │  │ Railway  │
        │ Messages │  │ (dynamic) │  │ Flask/Python │  │ Fallback │
        │ Memory   │  │ Groq/etc  │  │ :8080        │  └──────────┘
        │ Convos   │  └──────────┘   └──────────────┘
        │ Uploads  │
        └──────────┘
```

### Key Components

| Component | File | Role |
|-----------|------|------|
| Chat UI | `agent/chat.php` | Single-file PHP + HTML + CSS + JS |
| Message API | `agent/api/send.php` | Dual-path AI orchestration |
| Conversation API | `agent/api/conversations.php` | CRUD + memory management |
| AI providers | `providers/*.php` | Swappable AI backend drivers |
| Image storage | `agent/uploads/` | Persisted user-uploaded images |

---

## 3. Dual-Path Processing Model

Every request is classified into one of three `queryType` values immediately after input parsing:

| `queryType` | Condition | Pipeline |
|-------------|-----------|----------|
| `text` | No images attached | Full text pipeline: cache → greeting → RAG → weather → web search → LLM |
| `image` | Images only, no text | Image pipeline: validate → save → Disease API → image prompt → LLM |
| `image_with_text` | Images + text | Image pipeline + RAG knowledge layer, then LLM |

**Key rule:** `image` queries skip all text knowledge layers (RAG, weather, DuckDuckGo, NASA, FAO). The Disease Detection API analysis is the primary context. `image_with_text` additionally runs RAG retrieval for the user's text portion.

---

## 4. Case 1 — Image Pipeline

Handles `image` and `image_with_text` query types.

```
POST /agent/api/send.php  {images: [...], message: "optional"}
        │
        ▼
① Per-image validation loop (sequential)
   ├─ Format check: JPEG / PNG / WebP / GIF only
   │    └─ Fail → 400 INVALID_FORMAT
   ├─ Base64 decode + integrity check (min 100 bytes)
   │    └─ Fail → 400 INVALID_IMAGE_DATA
   └─ Size check: ≤ 10 MB decoded
        └─ Fail → 400 IMAGE_TOO_LARGE
        │
        ▼
② Save to disk (agent/uploads/)
   ├─ Filename: {userId}_{timestamp}_{4-byte-hex}.{ext}
   ├─ Path stored in storedImagePaths[]
   └─ Returned to frontend as BASE_URL + relative path (persists on reload)
        │
        ▼
③ Disease Detection API call (one per image, sequential)
   ├─ Endpoint: POST {disease_detection_api_url}/api/analyze  (multipart form)
   ├─ Fields: image file + crop="" field
   │    (same API as pages/disease.php — uses $SYSTEM_SETTINGS['disease_detection_api_url'])
   ├─ HTTP 200 + success:true → extract structured analysis
   ├─ HTTP 200 + error_code:"NOT_CROP" → 400 IMAGE_NOT_CROP (user-facing error, reject)
   ├─ HTTP != 200 or API error → inject "ask for symptoms" fallback context
   └─ API URL empty → inject "ask for symptoms" fallback context (no error to user)
        │
        ▼
④ Combine analyses
   ├─ Single image → use analysis directly
   └─ Multiple images → prefix each with "--- Image N: name ---"
        │
        ▼
⑤ Auto-generate message (image-only)
   └─ If message === "": "Please analyze this crop image and provide advice..."
        │
        ▼
⑥ Build image-optimised system prompt
   ├─ Disease API results present?
   │    └─ YES → structured 6-section response format:
   │              1. Disease/Condition (confidence + severity)
   │              2. Symptoms to Confirm
   │              3. Chemical Treatment (product, dosage, timing, PHI)
   │              4. Organic/IPM Alternative
   │              5. Prevention for Next Season
   │              6. Immediate Next Steps
   └─ NO (API unavailable) → prompt AI to gather symptom descriptions
        │
        ▼
⑦ → Shared processing (Section 6)
```

### Disease Detection Context Format (injected into system prompt)

```
===== DISEASE DETECTION API ANALYSIS =====
Crop:              Rice
Disease/Condition: Brown Plant Hopper (BPH)
Bengali name:      বাদামী গাছ ফড়িং
Confidence:        94%
Severity:          high
Healthy:           NO
Uncertain:         NO

Symptoms:
Hopper burn — yellowing then browning of lower leaves...

Treatment:
Spray Imidacloprid 0.5 ml/L; drain field for 7 days...

Organic Treatment:
Release Lycosa spider predators; install light traps...

Prevention:
Use BPH-resistant varieties (BRRI dhan29, 58)...
===========================================
```

---

## 5. Case 2 — Text Pipeline

Handles `text` query type only.

```
POST /agent/api/send.php  {message: "..."}
        │
        ▼
① Session cache check (30-min TTL)
   ├─ Only for: first message in a new conversation
   ├─ Cache key: MD5(message + location + personality + lang)
   ├─ Hit → return cached reply immediately (no LLM call)
   └─ Miss → continue
        │
        ▼
② Greeting shortcut (bypass LLM entirely)
   ├─ Regex: hi, hello, hey, greetings, assalamu, salam
   ├─ Regex: হ্যালো, সালাম, আস্সালামু, ওহে
   └─ Match → return instant greeting response
        │
        ▼
③ Knowledge layers (parallel evaluation)
   ├─ RAG Knowledge Base     — 9 domain keyword scorers, top-3 injected
   ├─ Few-shot examples       — matched by keyword, top-1 injected
   ├─ Bangladesh data         — BRRI/BARI/DAE data if rice/veg detected
   ├─ FAO guidelines          — injected if food/organic/safety mentioned
   ├─ Weather (Open-Meteo)   — 5-day forecast if weather keywords detected
   ├─ NASA EONET events       — Bangladesh region hazards if disaster keywords
   └─ DuckDuckGo web search  — instant answer if price/news/current keywords
        │
        ▼
④ Build agricultural advisor system prompt
   ├─ Full expert persona (extension officer + soil scientist + pathologist)
   ├─ Personality mode instructions (general/pest/soil/market/weather)
   ├─ Language enforcement (bn / en)
   ├─ All knowledge context blocks
   └─ User memory block
        │
        ▼
⑤ → Shared processing (Section 6)
```

---

## 6. Shared Processing (Both Paths)

After path-specific processing, both paths converge here:

```
        │
        ▼
⑥ Load conversation history
   ├─ Last 8 messages (ORDER DESC LIMIT 8, then reversed)
   ├─ Injected as chat history into AI messages array
   └─ strip_tags() applied to assistant content (HTML → plain for context)
        │
        ▼
⑦ Primary AI call (dynamic provider from admin_settings DB)
   ├─ Provider: Groq / OpenAI / Gemini / Claude / DeepSeek (admin-configured)
   ├─ Model, temperature, max_tokens from admin_settings
   ├─ Response timer: hrtime() for accurate latency measurement
   └─ Failure → ⑧
        │
        ▼
⑧ Fallback: Railway API
   ├─ Sends conversation history + message as plain text
   └─ Failure → return AI_FAILURE error
        │
        ▼
⑨ Format response
   └─ formatMarkdownToHtml() — see Markdown Rendering section
        │
        ▼
⑩ Persist to DB
   ├─ saveMessages(): INSERT user message (with image JSON) + assistant HTML
   └─ logAiUsage(): INSERT into ai_usage_logs (provider, model, latency, success)
        │
        ▼
⑪ Memory extraction
   └─ extractAndSaveMemory(): parse user message for crops, district, farm size, etc.
        │
        ▼
⑫ Title + follow-ups (fast model: llama-3.1-8b-instant)
   ├─ generateConversationTitle()  — 4-6 word title (first message only)
   └─ generateFollowUps()          — 3 suggested next questions
        │
        ▼
⑬ JSON response
{
  success, reply, detectedLang, translatedQuery,
  conversation_id, title, msg_id, followUps,
  type, image_count, has_disease_data
}
```

---

## 7. Intelligence Layers

### Text path layers

| Layer | Source | Trigger | Injected as |
|-------|--------|---------|-------------|
| RAG Knowledge Base | Built-in PHP array (9 domains) | Keyword match | Top-3 scored domain blocks |
| Few-shot examples | Built-in PHP array (4 examples) | Keyword match | 1 Q&A example |
| Bangladesh research data | Built-in (BRRI/BARI/DAE) | rice or vegetable detected | Research bullet points |
| FAO guidelines | Built-in | food/organic/certif/FAO | Sustainability block |
| Weather forecast | Open-Meteo API (free, no key) | weather/rain/forecast keywords | 5-day forecast table |
| NASA EONET | NASA EONET API (free) | flood/cyclone/disaster keywords | Active natural events list |
| DuckDuckGo instant answer | DuckDuckGo API (free, no key) | price/news/current/2025 keywords | Abstract + related topics |
| User memory | agent_user_memory DB table | Every request | Profile block |

### Image path layers

| Layer | Source | Trigger | Injected as |
|-------|--------|---------|-------------|
| Disease Detection API | Local Flask service (`http://localhost:8080/api/analyze`) | Every image | Structured analysis block |
| RAG Knowledge Base | Built-in PHP array | `image_with_text` only | Relevant domain block |
| User memory | agent_user_memory DB table | Every request | Profile block |

### RAG Knowledge Base Domains

| Domain key | Matched keywords |
|-----------|-----------------|
| Rice/Paddy | rice, paddy, boro, aman, aus, ধান, বোরো, আমন, আউশ |
| Wheat | wheat, গম |
| Vegetables | vegetable, potato, tomato, brinjal, cabbage, chili, সবজি, আলু |
| Soil & Fertilizer | soil, fertilizer, urea, compost, deficiency, pH, মাটি, সার |
| Pest & Disease | pest, insect, disease, blight, blast, wilt, IPM, পোকা, রোগ |
| Irrigation & Water | irrigation, drip, AWD, drought, পানি, সেচ |
| Weather & Climate | weather, forecast, flood, cyclone, drought, আবহাওয়া |
| Market & Economics | market, price, profit, sell, বাজার, দাম |
| Government Support | government, subsidy, DAE, BADC, loan, scheme |

---

## 8. What the Agent Accepts

### Text Input

| Property | Detail |
|----------|--------|
| Bengali (বাংলা) | Full Unicode support |
| English | Full natural language |
| Mixed Bengali-English | Handles code-switching |
| Max length | 2000 chars client-side |

### Image Input

| Property | Accepted Values |
|----------|----------------|
| Formats | JPEG, PNG, WebP, GIF |
| Max size | **10 MB** per image |
| Max count | No hard limit (processed sequentially) |
| Input methods | Click button, drag-and-drop, paste (Ctrl+V) |
| Subject matter | Crop plants, leaves, soil, pest damage, disease symptoms |
| Persistence | Saved to `agent/uploads/` — visible on conversation reload |

### Topic Domains

| Domain | Examples |
|--------|---------|
| Rice / Paddy | Boro, Aman, Aus varieties, fertilizer, harvest timing |
| Wheat | Disease, yield, planting calendar |
| Vegetables | Tomato, potato, brinjal, cabbage, okra |
| Soil & Fertilizer | NPK ratios, organic compost, pH testing, urea |
| Pest & Disease | Identification, IPM, pesticides, blast, BPH |
| Irrigation | AWD technique, drip systems, solar pumps |
| Weather | Current conditions, forecast, flood/drought management |
| Market & Price | Commodity prices, selling strategies, agri-finance |
| Government Programs | DAE subsidies, BRRI recommendations, loan schemes |

---

## 9. What the Agent Rejects

### Image Rejections (with `image_invalid: true`)

| Code | Condition | User message |
|------|-----------|-------------|
| `INVALID_FORMAT` | MIME type not in allowed list | "Unsupported format. Upload JPEG, PNG, WebP, or GIF." |
| `INVALID_IMAGE_DATA` | Base64 decode fails or < 100 bytes | "Image could not be decoded. Please re-upload." |
| `IMAGE_TOO_LARGE` | Decoded size > 10 MB | "Image exceeds 10 MB limit. Please compress and re-upload." |
| `IMAGE_NOT_CROP` | Disease API returns `error_code: NOT_CROP` | "This image does not appear to be a crop or plant photo." |

When rejected: frontend removes the pending user bubble and shows a toast alert.

### Text Guardrails (System Prompt Level)

| Category | Behavior |
|----------|---------|
| Completely off-topic | "I am a farming assistant. I can only help with agriculture topics." |
| Harmful chemical advice | Refuses to recommend banned or dangerous pesticides |
| Human medical advice | Redirects — "I help with plant health, not human health" |
| Political/religious content | Politely declines |

### Rate Limiting

| Limit | Value |
|-------|-------|
| Requests per minute | 30 per PHP session |
| Image size | 10 MB per file |
| Message length | 2000 chars client-side |

---

## 10. Frontend Features

### Chat Interface

| Feature | Detail |
|---------|--------|
| Multi-conversation sidebar | Full history grouped by Today / Yesterday / Previous 7 Days |
| Sidebar search | Real-time filter by conversation title |
| Conversation rename | Via sidebar rename icon or topbar rename button |
| Conversation delete | With confirmation dialog |
| Scroll-to-bottom button | Appears when scrolled >200px from bottom |
| Message timestamps | Every message shows formatted time |
| Message search | Ctrl+F — highlight + navigate matches |
| Export | TXT / Markdown / JSON (Ctrl+E) |
| Bookmarks | Star any AI message; view all in Saved panel |
| Image lightbox | Click any message image to view full-size |
| Message stats | Live count in topbar |

### Input

| Feature | Detail |
|---------|--------|
| Auto-resize textarea | Grows to 4 lines then scrolls |
| Character counter | Appears after 100 chars; amber at 80%, red at limit |
| Keyboard send | Enter sends; Shift+Enter inserts newline |
| Voice input | Mic button → speech-to-text (Bengali + English via Web Speech API) |
| Image attachment | Click, drag-drop, or Ctrl+V paste; multiple images supported |
| Image preview strip | Thumbnails with remove button and count badge |
| Quick prompts | ⚡ panel — categorized farming template prompts |
| Emoji picker | 50+ farming-relevant emojis |

### AI Responses

| Feature | Detail |
|---------|--------|
| Instant render | Full HTML with CSS fade-in animation (`msgFadeIn`) |
| Markdown | Bold, italic, triple-bold-italic, headings (h2-h5), `<ul>`, `<ol>`, `<hr>`, code blocks |
| Ordered lists | Correctly wrapped in `<ol>` (fixed in v3) |
| Code blocks | Language label + copy button in header |
| Follow-up chips | 3 clickable suggested next questions |
| Collapse long messages | "Show more / Show less" for responses >600 chars |
| Reading time | "~N min read" badge on responses >80 words |
| Copy | Plain text copy of any message |
| Speak (per message) | TTS with toggle stop; active button pulses green |
| Regenerate | Re-sends last user message for a new AI response |
| Feedback | Thumbs up/down stored per message in DB |

### Voice / TTS

| Feature | Detail |
|---------|--------|
| Per-message TTS | Click speak icon |
| Bengali detection | >15% Bengali Unicode → bn-BD voice priority |
| Voice priority | bn-BD → bn-IN → any Bengali → en-US → first available |
| Auto-speak mode | Toggle in topbar — speaks every AI response automatically |

---

## 11. Memory System

### Auto-Extraction

Runs after every user message via `extractAndSaveMemory()`:

| Trigger Pattern | Memory Key | Example |
|----------------|-----------|---------|
| Rice type mentioned | `grows_boro_rice` / `grows_aman_rice` / `grows_aus_rice` | `Boro rice` |
| Other crop mentioned | `grows_crop` | `potato` |
| Farming type | `farming_type` | `vegetable farmer` |
| Method mentioned | `farming_method` | `organic farming` |
| Technology mentioned | `uses_technology` | `drip irrigation` |
| District mentioned | `user_district` | `Sylhet` |
| Farm size (bigha/acre/hectare) | `farm_size` | `5 bigha` |
| Bengali chars in message | `preferred_language` | `Bangla` |

### Memory Injection into Prompt

```
[USER PROFILE — personalise your response using these known facts]
- user_district: Sylhet
- grows_boro_rice: Boro rice
- farm_size: 5 bigha
- preferred_language: Bangla
- farming_method: organic farming
```

### Memory Management

| Action | Location | Detail |
|--------|----------|--------|
| View memory | Topbar → Memory (Ctrl+M) | Lists all key-value pairs with source label |
| Add manual memory | Memory panel → Key + Value | Saved with `source: manual` |
| Delete one item | Memory panel → × button | Removes single row |
| Clear all | Memory panel → Clear All | Deletes all user memory |
| Auto-save | Background after each message | `INSERT … ON DUPLICATE KEY UPDATE` |

---

## 12. Personality Modes

Selected via the topbar dropdown. Persists in `localStorage`.

| Mode | `personality` | System Prompt Focus |
|------|--------------|---------------------|
| General | `general` | Broad Bangladesh farming advice, seasonal tips, crop calendar |
| Pest Expert | `pest` | IPM, pest ID, disease diagnosis, chemical thresholds |
| Soil Scientist | `soil` | Soil pH, NPK, organic matter, amendment recommendations |
| Market Advisor | `market` | Commodity prices, selling timing, agri-finance, cooperatives |
| Weather Analyst | `weather` | Weather interpretation, irrigation scheduling, disaster response |

---

## 13. Language Handling

### Auto-Detection

- Server counts Bengali Unicode characters (`U+0980–U+09FF`) in the user message
- If > 15% of non-whitespace characters are Bengali → `$effectiveLang = 'bn'`
- Result shown as a badge (`BN` / `EN`) on the message bubble

### Force Mode

| Client setting | `lang` field sent | Behaviour |
|---------------|-------------------|-----------|
| Auto (default) | omitted / `""` | Bengali detection applies |
| Force Bengali | `"bn"` | System prompt: "Write ENTIRE response in Bengali only" |
| Force English | `"en"` | System prompt: "Write ENTIRE response in English only" |

### UI Language

Separate from response language. Toggles interface labels between Bengali and English via `POST /api/set-language.php`.

---

## 14. API Reference

### `POST /agent/api/send.php`

**Request body (JSON):**

```json
{
  "conversation_id": "abc123def456",
  "message": "আমার ধানে বাদামী গাছ ফড়িং দেখা দিয়েছে, কী করব?",
  "location": "Sylhet",
  "personality": "pest",
  "lang": "bn",
  "images": [
    {
      "data": "<base64-encoded-bytes>",
      "mime": "image/jpeg",
      "name": "rice_leaf.jpg"
    }
  ]
}
```

**Request fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `conversation_id` | string | No | Omit for new conversation |
| `message` | string | No* | *Required unless images attached |
| `location` | string | No | User location (default: `Bangladesh`) |
| `personality` | string | No | `general` \| `pest` \| `soil` \| `market` \| `weather` |
| `lang` | string | No | `bn` \| `en` to force response language |
| `images` | array | No | `[{data, mime, name?}]` — base64-encoded images |
| `image_data` | string | No | Legacy single-image base64 (still supported) |
| `image_mime` | string | No | Legacy single-image MIME type |

**Success response:**

```json
{
  "success": true,
  "reply": "<p>বাদামী গাছ ফড়িং দমনে...</p>",
  "detectedLang": "BN",
  "translatedQuery": "আমার ধানে বাদামী গাছ ফড়িং...",
  "conversation_id": "abc123def456",
  "title": "বাদামী গাছ ফড়িং দমন",
  "msg_id": 142,
  "followUps": [
    "কোন কীটনাশক ব্যবহার করব?",
    "জৈব পদ্ধতিতে কীভাবে দমন করব?",
    "এই পোকা কি অন্য ফসলেও আক্রমণ করে?"
  ],
  "type": "image_with_text",
  "image_count": 1,
  "has_disease_data": true
}
```

**New response fields (v3):**

| Field | Type | Description |
|-------|------|-------------|
| `type` | string | `text` \| `image` \| `image_with_text` |
| `image_count` | int | Number of images processed |
| `has_disease_data` | bool | Whether Disease API returned usable analysis |

**Error responses:**

| `code` | HTTP | Condition |
|--------|------|-----------|
| `AUTH_REQUIRED` | 200 | User not logged in |
| `EMPTY_REQUEST` | 200 | No message and no images |
| `RATE_LIMITED` | 200 | > 30 requests/min |
| `INVALID_FORMAT` | 200 | Image MIME type not accepted |
| `INVALID_IMAGE_DATA` | 200 | Base64 decode failure |
| `IMAGE_TOO_LARGE` | 200 | Decoded image > 10 MB |
| `IMAGE_NOT_CROP` | 200 | Disease API reports non-crop image |
| `NOT_FOUND` | 200 | Conversation not found or not owned by user |
| `AI_FAILURE` | 200 | Both primary and fallback AI providers failed |

> All errors return HTTP 200 with `success: false` to prevent browser retry loops.

---

### `POST /agent/api/conversations.php`

**Request format:** JSON body with `action` field.

| Action | Required Fields | Description |
|--------|----------------|-------------|
| `list` | — | Returns all conversations for current user (max 100, ordered by `updated_at DESC`) |
| `new` | — | Creates a new conversation, returns `conversation_id` |
| `load` | `conversation_id` | Returns conversation + all messages with `images` arrays decoded |
| `rename` | `conversation_id`, `title` | Renames a conversation |
| `delete` | `conversation_id` | Deletes conversation + all messages |
| `feedback` | `message_id`, `value` (1/-1/0) | Stores thumbs up/down on an AI message |
| `memory_list` | — | Returns all memory items for current user |
| `memory_delete` | `id` | Deletes one memory row |
| `memory_clear` | — | Deletes all memory for current user |
| `memory_save` | `key`, `value` | Saves a manual memory entry |

**`load` response — message format:**

```json
{
  "id": 141,
  "role": "user",
  "content": "আমার ধানে পোকা ধরেছে",
  "images": [
    "agent/uploads/5_1747484121_a3f2c1d4.jpg"
  ],
  "feedback": null,
  "created_at": "2026-05-17 14:20:00"
}
```

`images` is always an array (empty `[]` if no images). Paths are relative to `BASE_URL` — the frontend constructs the full URL as `BASE_URL + path`.

---

## 15. Database Schema

### `agent_conversations`

```sql
CREATE TABLE agent_conversations (
  conversation_id VARCHAR(32)  PRIMARY KEY,
  user_id         INT          NOT NULL,
  title           VARCHAR(200) DEFAULT 'New Chat',
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user    (user_id),
  INDEX idx_updated (updated_at)
);
```

### `agent_messages`

```sql
CREATE TABLE agent_messages (
  id              INT          AUTO_INCREMENT PRIMARY KEY,
  conversation_id VARCHAR(32)  NOT NULL,
  role            ENUM('user','assistant') NOT NULL,
  content         LONGTEXT     NOT NULL,     -- HTML for assistant; plain text for user
  images          JSON         NULL,         -- JSON array of relative image paths
  feedback        TINYINT      DEFAULT NULL, -- 1=helpful, -1=not helpful, NULL=no vote
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conv (conversation_id),
  FOREIGN KEY (conversation_id) REFERENCES agent_conversations(conversation_id) ON DELETE CASCADE
);
```

> `images` column added in `migration_v3.sql`:
> ```sql
> ALTER TABLE agent_messages ADD COLUMN images JSON NULL DEFAULT NULL AFTER content;
> ```

### `agent_user_memory`

```sql
CREATE TABLE agent_user_memory (
  id           INT          AUTO_INCREMENT PRIMARY KEY,
  user_id      INT          NOT NULL,
  memory_key   VARCHAR(100) NOT NULL,
  memory_value TEXT         NOT NULL,
  source       ENUM('auto','manual') DEFAULT 'auto',
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_key (user_id, memory_key)
);
```

### `ai_usage_logs`

```sql
CREATE TABLE ai_usage_logs (
  id               INT          AUTO_INCREMENT PRIMARY KEY,
  user_id          INT,
  conversation_id  VARCHAR(32),
  provider         VARCHAR(50),   -- 'groq', 'openai', 'gemini', 'railway', etc.
  model            VARCHAR(100),
  response_time_ms INT,           -- measured with hrtime()
  success          TINYINT(1),    -- 1 = success, 0 = failure
  error_message    TEXT,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);
```

---

## 16. File Structure

```
agent/
├── chat.php                    # Full-page chat UI (PHP + HTML + CSS + JS)
│
├── api/
│   ├── send.php                # Dual-path AI orchestration (v2)
│   └── conversations.php       # Conversation & memory CRUD
│
├── uploads/                    # Persisted user-uploaded images (served by Apache)
│   └── {userId}_{ts}_{hex}.{ext}
│
├── assets/
│   ├── logo.png
│   ├── css/
│   │   └── google-font.css     # Poppins font (local copy)
│   └── audio/
│       ├── message send.mp3
│       └── message-notification.mp3
│
└── migration_v3.sql            # ALTER TABLE to add images column

providers/                      # Shared AI provider drivers (project root)
├── AIProviderFactory.php
├── GroqProvider.php
├── OpenAIProvider.php
└── BaseProvider.php

disease-detection/              # Standalone Python ML service (port 8080)
├── app.py                      # Flask entry point
├── run.bat                     # Windows launcher (auto-venv + auto-install)
├── requirements.txt            # flask, torch, Pillow, transformers, numpy
├── venv/                       # Auto-created virtual environment
├── backend/
│   └── models/
│       ├── class_names.json
│       └── class_names_merged.json
└── static/
    └── uploads/disease_images/

docs/
└── agent.md                    # This file
```

---

## 17. Configuration & Environment

### Required Settings

| Source | Key | Description |
|--------|-----|-------------|
| `config/config.php` | `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL credentials |
| `admin_settings` DB | `ai_provider` | Active AI provider name |
| `admin_settings` DB | `ai_model` | Model ID |
| `admin_settings` DB | `ai_api_key_{provider}` | Per-provider API key |
| `admin_settings` DB | `ai_temperature` | Sampling temperature (default 0.7) |
| `admin_settings` DB | `ai_max_tokens` | Max response tokens (default 1200) |
| `system_settings` DB | `disease_detection_api_url` | Disease Detection API base URL (default: `http://localhost:8080`) |
| `system_settings` DB | `agent_api_url` | Railway fallback endpoint |

### AI Model Usage

| Task | Model | Max Tokens | Temperature |
|------|-------|-----------|-------------|
| Main response | Admin-configured (default: `llama-3.3-70b-versatile`) | Admin-configured | Admin-configured |
| Title generation | `llama-3.1-8b-instant` | 24 | 0.4 |
| Follow-up questions | `llama-3.1-8b-instant` | 120 | 0.7 |

### Session Cache

| Setting | Value |
|---------|-------|
| Cache TTL | 30 minutes |
| Cache scope | Text queries only; first message of new conversation |
| Cache key | `MD5(message + location + personality + lang)` |
| Storage | PHP `$_SESSION['agent_cache']` |

### Client-side `localStorage` Keys

| Key | Description |
|-----|-------------|
| `chatFontSize` | `md` / `sm` / `lg` |
| `personality` | Selected AI mode |
| `forceLang` | `bn` / `en` / `""` |
| `alwaysSpeak` | `"true"` / `"false"` |
| `voiceLang` | Selected TTS voice locale |
| `userLocation` | Geolocation result |
| `chatBookmarks` | JSON array of bookmarked message IDs |

---

## 18. Security & Rate Limiting

### Authentication

- All endpoints check `isLoggedIn()` — returns `AUTH_REQUIRED` if not logged in
- Conversation ownership verified on every `load` / `rename` / `delete`
- `agent/uploads/` served by Apache; filenames include `userId_` prefix and random hex to prevent enumeration

### Input Sanitization

| Risk | Mitigation |
|------|-----------|
| XSS in user messages | Not stored as HTML; displayed via `escHtml()` in JS |
| XSS in AI output | `formatMarkdownToHtml()` uses only safe tags; no `innerHTML` with raw API data |
| SQL injection | All queries use PDO prepared statements |
| File upload abuse | MIME whitelist + 10 MB limit + base64 integrity check |
| Prompt injection | System prompt is not visible to users; injected server-side only |

### Rate Limiting

```
30 requests per 60-second window per PHP session
Tracked in: $_SESSION['req_log'] (timestamps array, pruned each request)
On exceed: JSON {"success": false, "code": "RATE_LIMITED"} — HTTP 200
           (HTTP 200 to avoid browser fetch retry loops)
```

---

## 19. Error Handling

### Client-side Error Chain

```
fetch() fails (network error)
    └─ appendErrorMsg("Network error. Please check your connection.")

data.success = false AND data.image_invalid = true
    └─ Remove pending user message bubble
    └─ showCustomAlert(data.message)

data.success = false (other)
    └─ appendErrorMsg(data.message || "Failed to get a response.")

data.reply is empty string
    └─ appendErrorMsg("Empty response from AI.")
```

### Server-side Error Chain

```
Image validation fails
    └─ Return {success: false, code: "INVALID_FORMAT"|..., image_invalid: true}

Disease API returns NOT_CROP
    └─ Return {success: false, code: "IMAGE_NOT_CROP", image_invalid: true}

Disease API unreachable / HTTP != 200
    └─ Inject "ask for symptoms" fallback context (no error to user)
    └─ Continue to AI call

Primary AI provider fails
    └─ Try Railway fallback

Railway fallback fails
    └─ Return {success: false, code: "AI_FAILURE"}

DB write fails (saveMessages)
    └─ Log error server-side (error_log)
    └─ Return response anyway (message shown but won't persist for reload)
```

### Toast Types

| Variant | Trigger | Style |
|---------|---------|-------|
| Error (default) | `showCustomAlert(msg)` | Red border, warning icon |
| Success | `showCustomAlert(msg, 'success')` | Green border, check icon |
| Auto-dismiss | Both types | 2.5–6 seconds |

---

## 20. Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Enter` | Send message |
| `Shift + Enter` | Insert newline |
| `Ctrl + K` | New conversation |
| `Ctrl + F` | In-chat message search |
| `Ctrl + E` | Export conversation |
| `Ctrl + B` | Toggle sidebar |
| `Ctrl + M` | Open AI Memory panel |
| `Ctrl + I` | Open image file picker |
| `Ctrl + /` | Show keyboard shortcuts overlay |
| `Esc` | Close any open modal / panel |
| *Any key* | Stop active TTS speech |

---

## Appendix A: Request Flow Diagram

```
                        USER ACTION
                            │
              ┌─────────────┼──────────────────┐
              ▼             ▼                  ▼
         Type text    Click suggestion    Upload image(s)
              │             │                  │
              └─────────────┴──────────────────┘
                            │
                     sendMessage() [JS]
                            │
                    ┌───────┴───────┐
                    │ Classify type  │ text | image | image_with_text
                    └───────┬───────┘
                            │
                 ┌──────────▼──────────┐
                 │  POST /api/send.php  │
                 └──────────┬──────────┘
                            │
              ┌─────────────▼──────────────────┐
              │    queryType === 'image'?       │
              │                                │
              │  YES: validate → save to disk  │
              │       → Disease API (per img)  │
              │       → combine analyses       │
              │       → image system prompt    │
              │                                │
              │  NO (text): cache? → greeting? │
              │       → RAG + weather + web    │
              │       → text system prompt     │
              └─────────────┬──────────────────┘
                            │
              ┌─────────────▼──────────────────┐
              │         SHARED PATH            │
              │   Load history → AI call       │
              │   → Fallback → Format HTML     │
              │   → Persist → Memory extract  │
              │   → Title + Follow-ups         │
              └─────────────┬──────────────────┘
                            │
                  ┌─────────▼──────────┐
                  │    JSON Response    │
                  │  type, image_count  │
                  │  has_disease_data   │
                  └─────────┬──────────┘
                            │
              ┌─────────────▼──────────────────┐
              │         Browser Render          │
              │  appendAIMsg (HTML + fade-in)  │
              │  → enhanceCodeBlocks           │
              │  → addReadTime                 │
              │  → setupCollapse               │
              │  → displayFollowUps            │
              │  → playSound + TTS (if on)     │
              └────────────────────────────────┘
```

## Appendix B: Markdown Rendering

`formatMarkdownToHtml()` in `send.php` converts AI markdown to safe HTML:

| Markdown | HTML output |
|----------|-------------|
| ` ```lang\ncode\n``` ` | `<pre class="code-block"><code class="lang-{lang}">` |
| `` `inline` `` | `<code class="inline-code">` |
| `***text***` | `<strong><em>` |
| `**text**` | `<strong>` |
| `*text*` | `<em>` |
| `#### heading` | `<h5>` |
| `### heading` | `<h4>` |
| `## heading` | `<h3>` |
| `# heading` | `<h2>` |
| `- item` / `* item` / `• item` | `<ul><li>` (sequential lines wrapped in single `<ul>`) |
| `1. item` | `<ol><li>` (sequential lines wrapped in single `<ol>`) |
| `---` | `<hr>` |
| Blank line | `</p><p>` |

Code blocks are extracted before other rules run (placeholder substitution) to prevent their content from being processed as markdown.

---

---

## Appendix C: Disease Detection Service

The Disease Detection service is a separate Python/Flask application that runs alongside the PHP backend. It handles all ML inference for plant/crop image analysis.

### Service Details

| Property | Value |
|----------|-------|
| Port | `8080` |
| Base URL | `http://localhost:8080` |
| Entry point | `disease-detection/app.py` |
| Launcher (Windows) | `disease-detection/run.bat` |

### Exposed Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/detect` | POST | Plant detection (primary image classification) |
| `/api/analyze` | POST | Disease analysis — called by `send.php` (multipart: `image` + `crop`) |
| `/health` | GET | Health check — returns service status |

### Technology Stack

| Package | Role |
|---------|------|
| Flask | HTTP server |
| PyTorch (`torch`) | ML model inference |
| Pillow (`PIL`) | Image decoding and preprocessing |
| Transformers (HuggingFace) | Model loading |
| NumPy | Tensor/array utilities |

### Setup & Launch (Windows)

`run.bat` automates the full setup in 4 steps:

1. **Python check** — requires Python 3.10+ in `PATH`
2. **Virtual environment** — creates `disease-detection/venv/` if not present
3. **Dependency install** — installs from `requirements.txt` if any package is missing (first run: 3–10 minutes)
4. **Start** — runs `app.py`; opens `http://localhost:8080` in browser

```bat
cd disease-detection
run.bat
```

To stop: press `Ctrl+C` in the console window.

### Integration with PHP

`send.php` reads `$SYSTEM_SETTINGS['disease_detection_api_url']` and POSTs to `{url}/api/analyze` as multipart form data. Set this value in the admin panel to the service base URL (e.g. `http://localhost:8080`). If the URL is empty or the service is unreachable, the agent falls back to asking the user to describe symptoms rather than returning an error.

---

*Documentation maintained at `docs/agent.md`. Update whenever the pipeline, schema, or features change.*
