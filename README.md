# 🌾 Smart Chashi (Chashi Bhai) - AI-Powered Smart Farming Ecosystem

## Project Overview

**Smart Chashi** is a comprehensive AI-powered smart farming ecosystem designed to empower farmers with intelligent advice, real-time information, and data-driven insights. Named after the protagonist Chashi Bhai, this system brings together cutting-edge AI, weather analytics, disease detection, and agricultural innovation into one unified platform.

---

## 📋 Table of Contents

1. [Features](#features)
2. [Data Flow Architecture](#-data-flow-architecture)
3. [System Workflows & User Flows](#-system-workflows--user-flows)
4. [Technology Stack](#-technology-stack)
5. [System Architecture](#️-system-architecture)
6. [Database Design & Schema](#️-database-design--schema)
7. [API & Data Flow Examples](#-api--data-flow-examples)
8. [Data Security & Authentication](#-data-security--authentication)
9. [Work Plan & Timeline](#-work-plan--timeline)
10. [Color Scheme & Design](#-color-scheme--design)
11. [Monetization & Sustainability](#monetization--sustainability)
12. [Social Impact](#social-impact)
13. [Getting Started](#getting-started)

---

## 🚀 Features

### A. Core Application Features

#### 1. User Account & Identity System
- Sign Up / Sign In (Email + Phone)
- Forgot Password (OTP / Email)
- Farmer Profile with crop type, land size, location, experience level
- Role-based access (Farmer, Agriculture Officer, Admin)

#### 2. Auto Location Detection
- Auto-detect user location using browser JavaScript
- Reverse geocoding (district, upazila)
- Weather + soil auto-mapped to location

### B. AI-Powered Features

#### 3. AI Chat Assistant – "Chashi Bhai"
- Ask questions in Bangla & English
- Farming guidance, pest control, fertilizer suggestions
- Speech-to-text (Bangla), Text-to-speech (Bangla voice)

#### 4. Crop Disease Detection (Image Analysis)
- Upload crop photo
- AI analyzes disease with severity
- Provides treatment steps and preventive measures

#### 5. Crop Health & Yield Prediction
- Satellite imagery analysis
- Weather data integration
- Yield forecasts and risk alerts

### C. Information & Advisory Modules

#### 6. Weather & Disaster Alert System
- Live weather info, flood/cyclone alerts
- SMS/app notifications
- Voice alert in Bangla

#### 7. Smart Fertilizer & Irrigation Recommendation
- Soil + weather + crop stage analysis
- AI-suggested fertilizer type, quantity, timing
- Irrigation schedule optimization

### D. Agriculture Innovation Modules

#### 8. New Agriculture Methods Hub
- Smart irrigation, organic farming, climate-resilient crops
- Articles, videos, AI summaries

#### 9. Farm-to-Kitchen Marketplace
- Farmers list produce, buyers place demand
- Price transparency, local delivery coordination

#### 10. Community & Demand System
- Farmer community forum
- Crop demand forecasting
- Regional price trends, AI demand prediction

#### 11. Gaming Simulation (Youth Attraction)
- Virtual farm simulator
- Climate challenge mode
- AI decision-based scoring

#### 12. Video Learning Platform
- Farming tutorials, government announcements
- AI-generated summaries

### E. Dashboard & Governance

#### 13. Agriculture Officer Dashboard
- Regional crop health maps
- Disease outbreak heatmaps
- Yield forecasts, farmer activity analytics

---

## � Data Flow Architecture

### High-Level Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FRONTEND (PHP Pages)                        │
│  ┌──────────┬──────────┬─────────┬──────────┬─────────┐           │
│  │Dashboard │  Crops   │ Disease │   Chat   │ Weather │  ...       │
│  └──────────┴──────────┴─────────┴──────────┴─────────┘           │
└─────────────────────────┬───────────────────────────────────────────┘
                          │
                   AJAX/JSON Requests
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     API HANDLER (api/handler.php)                   │
│  ┌─────────┬─────────┬────────┬─────────┬──────────┐              │
│  │  Auth   │  Crop   │Disease │  Chat   │ Weather  │              │
│  │Handlers │Handlers │Handlers│Handlers │Handlers  │              │
│  └─────────┴─────────┴────────┴─────────┴──────────┘              │
└─────────────────────────┬───────────────────────────────────────────┘
                          │
                  Database Queries
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    MySQL Database (smartcashi_db)                   │
│  ┌──────────┬───────────┬──────────┬──────────┬─────────┐         │
│  │  Users   │   Crops   │Diseases  │  Weather │ Market  │  ...     │
│  │ Profiles │ Activities│ Reports  │  Alerts  │ Prices  │         │
│  └──────────┴───────────┴──────────┴──────────┴─────────┘         │
└─────────────────────────────────────────────────────────────────────┘
```

### Detailed Request/Response Flow

```
User Action → Frontend Form
      │
      ▼
JavaScript AJAX Request → api/handler.php?action=...
      │
      ▼
API Handler Validates Request
      │
      ▼
Route to Specific Handler (auth/crop/disease/etc)
      │
      ├─→ Authenticate User
      ├─→ Validate Input Data
      ├─→ Execute Database Query via Database Class
      │
      ▼
Database Returns Results
      │
      ▼
Process & Format Response (JSON)
      │
      ▼
Send JSON Response to Frontend
      │
      ▼
JavaScript Updates DOM/UI
      │
      ▼
User Sees Updated Content
```

---
## 🔄 System Workflows & User Flows

### 1. **User Registration & Login Flow**

```
START
  │
  ├─→ User visits registration page
  │    │
  │    ▼
  │   Fill form (email, phone, password, name)
  │    │
  │    ▼
  │   Submit → api/handler.php?action=register
  │    │
  │    ▼
  │   Validate email/phone uniqueness
  │    │
  │    ├─ Valid? ─→ Hash password
  │    │              │
  │    │              ▼
  │    │            Save to users table
  │    │              │
  │    │              ▼
  │    │            Return success JSON
  │    │              │
  │    │              ▼
  │    │            Redirect to profile setup
  │    │
  │    └─ Invalid? ─→ Return error message
  │
  └─→ User logs in with email/phone + password
       │
       ▼
      Verify credentials against users table
       │
       ├─ Valid? ─→ Create session
       │              │
       │              ▼
       │            Set $_SESSION['user_id']
       │              │
       │              ▼
       │            Redirect to dashboard
       │
       └─ Invalid? ─→ Show error
END
```

### 2. **Crop Management Flow**

```
START
  │
  ├─→ View Crops Page
  │    │
  │    ▼
  │   Load crops.php
  │    │
  │    ▼
  │   AJAX: fetch_crops (api/handler.php?action=get-crops)
  │    │
  │    ▼
  │   Query crop_data WHERE farmer_id = current_user_id
  │    │
  │    ▼
  │   Return JSON array of crops
  │    │
  │    ▼
  │   Display crop cards on frontend
  │
  ├─→ Add New Crop
  │    │
  │    ▼
  │   Click "Add Crop" button
  │    │
  │    ▼
  │   Show form modal
  │    │
  │    ▼
  │   Fill: crop_name, variety, area, planting_date, etc
  │    │
  │    ▼
  │   AJAX: submit to api/handler.php?action=add-crop
  │    │
  │    ├─ Validate input
  │    │
  │    ▼
  │   Insert into crop_data
  │    │
  │    ▼
  │   Return new crop object
  │    │
  │    ▼
  │   Add to crop list in UI
  │
  └─→ Track Crop Activities
       │
       ▼
      Activities stored in crop_activities
       │
       ▼
      Updates crop status (planning → growing → harvesting)
END
```

### 3. **Disease Detection Flow**

```
START
  │
  ├─→ Navigate to Disease Detection
  │    │
  │    ▼
  │   Load disease.php
  │    │
  │    ▼
  │   Upload crop image or take photo
  │    │
  │    ▼
  │   AJAX: POST image to api/handler.php?action=analyze-disease
  │    │
  │    ▼
  │   Backend saves image to public/uploads/disease_images/
  │    │
  │    ▼
  │   Call Python AI service (FastAPI/Flask)
  │    │
  │    ├─→ Python service runs TensorFlow/PyTorch model
  │    │    │
  │    │    ▼
  │    │   Model returns: [disease_name, severity, confidence_score]
  │    │    │
  │    │    ▼
  │    │   Python service returns JSON
  │    │
  │    ▼
  │   Store detection in disease_reports
  │    │
  │    ▼
  │   Fetch treatment from disease_library
  │    │
  │    ▼
  │   Return results + recommendations to frontend
  │    │
  │    ▼
  │   Display: disease name, severity, symptoms, treatment steps
  │    │
  │    ▼
  │   Option to mark as treated/cured
  │    │
  │    ▼
  │   Officer can verify detection
  │
  └─→ Track treatment progress in disease_reports
END
```

### 4. **AI Chat Assistant (Chashi Bhai) Flow**

```
START
  │
  ├─→ Open Chat Interface
  │    │
  │    ▼
  │   Load chat.php
  │    │
  │    ▼
  │   Show chat history from ai_chat_logs
  │
  ├─→ User Types Question (in Bangla or English)
  │    │
  │    ▼
  │   Optional: Speech-to-text (Google Speech API)
  │    │
  │    ▼
  │   AJAX: POST message to api/handler.php?action=send-message
  │    │
  │    ▼
  │   Save user message to ai_chat_logs
  │    │
  │    ▼
  │   Call Python NLP service with message + user context
  │    │
  │    ├─→ Python service:
  │    │    ├─ Extract intent (crop_advice, disease, weather, market, general)
  │    │    ├─ Get user's crops/region from DB
  │    │    ├─ Retrieve relevant data
  │    │    ├─ Generate response using GPT/NLP model
  │    │    └─ Translate to user's language if needed
  │    │
  │    ▼
  │   Python returns AI response + metadata
  │    │
  │    ▼
  │   Save AI response to ai_chat_logs
  │    │
  │    ▼
  │   Optional: Text-to-speech (Google TTS)
  │    │
  │    ▼
  │   Display response in chat UI
  │    │
  │    ▼
  │   User can rate response (1-5 stars)
  │
  └─→ Continuous conversation thread
END
```

### 5. **Weather Alert Flow**

```
START
  │
  ├─→ Weather API (OpenWeatherMap) sends data
  │    │
  │    ▼
  │   Store in weather_data table
  │
  ├─→ System checks for alerts (flood, cyclone, heatwave, etc)
  │    │
  │    ▼
  │   Match user regions with alert zones
  │    │
  │    ▼
  │   Create entries in weather_alerts
  │    │
  │    ├─→ Fetch affected farmer users
  │    │    │
  │    │    ▼
  │    │   Create entries in alerts table
  │    │    │
  │    │    ▼
  │    │   Send via app, email, SMS
  │    │
  │    └─→ Priority: HIGH → Send immediately
  │         Priority: MEDIUM → Queue for batch send
  │         Priority: LOW → Show in feed only
  │
  ├─→ User receives alert notification
  │    │
  │    ▼
  │   User clicks alert
  │    │
  │    ▼
  │   Load weather.php with detailed info
  │    │
  │    ▼
  │   Mark alert as read in alerts table
  │
  └─→ Officer can issue additional advisory
       │
       ▼
      Save to advisories table
       │
       ▼
      Broadcast to farmers in region
END
```

### 6. **Marketplace Flow**

```
START
  │
  ├─→ FARMER: List Product for Sale
  │    │
  │    ▼
  │   Navigate to marketplace.php
  │    │
  │    ▼
  │   Click "Add Product"
  │    │
  │    ▼
  │   Fill: product_name, description, price, quantity, images
  │    │
  │    ▼
  │   AJAX: POST to api/handler.php?action=add-product
  │    │
  │    ▼
  │   Upload images to public/uploads/marketplace/
  │    │
  │    ▼
  │   Store in marketplace_products
  │    │
  │    ▼
  │   Product appears in marketplace feed
  │
  ├─→ BUYER: Browse Products
  │    │
  │    ▼
  │   Load marketplace.php
  │    │
  │    ▼
  │   AJAX: filter by crop_type, location, price range
  │    │
  │    ▼
  │   Display product listings
  │    │
  │    ▼
  │   Click product → view details, seller info
  │
  ├─→ BUYER: Make Inquiry
  │    │
  │    ▼
  │   Click "Ask Seller"
  │    │
  │    ▼
  │   Save to product_inquiries
  │    │
  │    ▼
  │   Seller notified
  │    │
  │    ▼
  │   Seller responds
  │    │
  │    ▼
  │   Buyer receives response
  │
  └─→ BUYER: Place Order
       │
       ▼
      Click "Buy"
       │
       ▼
      Specify quantity, delivery address
       │
       ▼
      Select payment method (cash/bKash/Nagad/bank)
       │
       ▼
      Create marketplace_orders entry
       │
       ▼
      Notification to seller
       │
       ▼
      Seller confirms/ships
       │
       ▼
      Update order_status → delivered
       │
       ▼
      Buyer confirms receipt
END
```

### 7. **Community Forum Flow**

```
START
  │
  ├─→ View Community Posts
  │    │
  │    ▼
  │   Load community.php
  │    │
  │    ▼
  │   AJAX: GET community_posts (filtered by category)
  │    │
  │    ▼
  │   Display posts with author info, category, likes, comments
  │
  ├─→ Create New Post
  │    │
  │    ▼
  │   Click "Ask Question" / "Share Tip"
  │    │
  │    ▼
  │   Fill: title, content, category, tags, optional image
  │    │
  │    ▼
  │   AJAX: POST to api/handler.php?action=add-post
  │    │
  │    ▼
  │   Save to community_posts
  │    │
  │    ▼
  │   Post appears in feed (pending approval if needed)
  │
  ├─→ Like Post
  │    │
  │    ▼
  │   Click like button
  │    │
  │    ▼
  │   AJAX: POST to like-post endpoint
  │    │
  │    ▼
  │   Check if user already liked (unique constraint)
  │    │
  │    ├─ Not liked? → Insert into post_likes, increment likes counter
  │    │
  │    └─ Already liked? → Remove from post_likes, decrement counter
  │
  └─→ Comment on Post
       │
       ▼
      Click "Add Comment"
       │
       ▼
      Type comment message
       │
       ▼
      AJAX: POST to comment endpoint
       │
       ▼
      Save to post_comments
       │
       ▼
      Display comment with author, timestamp
       │
       ▼
      Support nested replies (parent_comment_id)
END
```

---
## �💻 Technology Stack

| **Layer** | **Technology** | **Purpose** |
|-----------|---|---|
| **Frontend (Pages)** | PHP files with HTML5, CSS3, jQuery AJAX | Server-side page rendering with clean URLs |
| **Frontend (UI)** | HTML5, CSS3 (Mobile-First) | Responsive interface, touch-optimized |
| **Frontend (Interactions)** | jQuery AJAX, JavaScript ES6+ | Async server communication, dynamic updates |
| **URL Routing** | Apache mod_rewrite (.htaccess) | Clean SEO-friendly URLs without query strings |
| **Backend** | PHP 7.4+ | Session-based authentication, AJAX handlers, data processing |
| **Database** | MySQL 8.0+ | Relational data storage (3NF normalized) |
| **File Serving** | PHP blob server (file-blob.php) | Secure file download/stream with permissions |
| **Caching** | File-based JSON | Session data, temporary processing results |
| **Storage** | File System (public/uploads/) | User-uploaded images, documents |
| **AI/ML** | Python 3.8+, TensorFlow, PyTorch, OpenCV | Image classification, NLP, yield predictions |
| **AI Serving** | FastAPI / Flask | REST API endpoints for Python models |
| **Maps** | Google Maps API | Location services, farmer locator |
| **Weather** | OpenWeatherMap API | Real-time weather, alerts |
| **Satellite** | Google Earth Engine | NDVI, crop health monitoring |
| **Vision** | Google Vision API | Image recognition, alternate to local models |
| **Notifications** | SMS Gateway, Email SMTP | Alerts, messages, market updates |

---

## 🏗️ System Architecture

```
┌──────────────────────────────────────────────────┐
│   Mobile-First Web Interface (Client-Side)       │
│   (HTML5/CSS3/jQuery AJAX)                       │
│   • Responsive Design (320px-480px)              │
│   • Touch-Optimized Components                   │
│   • jQuery AJAX for server communication         │
│   • Local Storage for offline data               │
│         ↓                                        │
└──────────────────────────────────────────────────┘
                      ↓
┌──────────────────────────────────────────────────┐
│   Backend Server (PHP Session-Based)             │
│   • User Authentication (Sessions)               │
│   • AJAX request handlers                        │
│   • Data Management (MySQL)                      │
│   • File uploads & processing                    │
│   • Python service integration                   │
│         ↓                                        │
└──────────────────────────────────────────────────┘
                      ↓
┌──────────────────────────────────────────────────┐
│   AI/ML Services (Python)                        │
│   • Disease detection models                     │
│   • NLP & text processing                        │
│   • Yield predictions                            │
│         ↓                                        │
└──────────────────────────────────────────────────┘
                      ↓
┌───────────────────────────────────────────────────┐
│         Data Layer                                │
│  ┌──────────────┐  ┌──────────────┐              │
│  │   MySQL      │  │ File Storage │              │
│  │ (Relational) │  │ (Images/JSON)│              │
│  └──────────────┘  └──────────────┘              │
└───────────────────────────────────────────────────┘
```

### Client-Server Communication Flow:
```
[Browser] --jQuery AJAX--> [PHP AJAX Handler] --PHP Code--> [MySQL/Python]
              |                    |                              |
              |<--- JSON Response---|
```

---

## 📱 Mobile-First Design Approach

### Responsive Web Application Strategy
Smart Chashi is built as a mobile-first responsive web application, optimized for smartphones (320px-480px), tablets (768px), and desktops. This approach provides:

- **Zero friction deployment:** No app store requirements
- **Instant updates:** Users always access latest version
- **Cross-platform:** Works on all devices (iOS, Android, Web)
- **Progressive enhancement:** Works offline with service workers
- **Native-like experience:** Full-screen mode, home screen shortcut, touch gestures

### Future Flutter Native Wrapper (Optional)
In a future phase, a Flutter WebView wrapper can be added to provide:
- Native app distribution through app stores
- Push notifications via FCM
- Direct device feature access (camera, GPS, storage)
- Enhanced offline capabilities

**Current Phase:** Pure responsive web application. Flutter integration will be added as Phase 3 (separate project).

---

## 🗄️ Database Design & Schema

### Database Overview

**Database Name:** `smartcashi_db`  
**Engine:** InnoDB  
**Character Set:** utf8mb4_unicode_ci  
**Normalization:** 3NF (Third Normal Form)  
**Total Tables:** 35+  

### Entity Relationship Diagram (Conceptual)

```
                        ┌──────────────┐
                        │    USERS     │
                        │   (Core)     │
                        └──────┬───────┘
                               │
                    ┌──────────┼──────────┐
                    │          │          │
                    ▼          ▼          ▼
            ┌─────────────┐ ┌──────────┐ ┌──────────┐
            │  FARMER     │ │ OFFICER  │ │   ADMIN  │
            │ PROFILES    │ │PROFILES  │ │          │
            └─────────────┘ └──────────┘ └──────────┘
                    │
                    ▼
            ┌─────────────────┐
            │   CROP DATA     │
            │ (Per Farmer)    │
            └────────┬────────┘
                     │
            ┌────────┼────────┐
            │        │        │
            ▼        ▼        ▼
        ┌────────┐ ┌──────┐ ┌──────────────┐
        │DISEASE │ │CROP  │ │ FERTILIZER   │
        │REPORTS │ │ACT.  │ │ RECOMMENDS   │
        └────────┘ └──────┘ └──────────────┘

    ┌──────────────────┬──────────────────┐
    │   ENVIRONMENT    │     MARKET       │
    │                  │                  │
┌───▼────┐ ┌────────┐  │  ┌────────────┐  │
│WEATHER │ │WEATHER │  │  │  MARKET    │  │
│ DATA   │ │ALERTS  │  │  │  PRICES    │  │
└────────┘ └────────┘  │  └────────────┘  │
                       └──────────────────┘

    ┌──────────────────┬──────────────────┐
    │  SOCIAL          │    COMMERCE      │
    │                  │                  │
┌───▼────────┐ ┌────┐  │ ┌──────────────┐ │
│ COMMUNITY  │ │ AI │  │ │ MARKETPLACE  │ │
│   POSTS    │ │CHAT│  │ │  PRODUCTS    │ │
└────────────┘ └────┘  │ └──────────────┘ │
                       └──────────────────┘
```

### Complete Table List & Categories

#### **A. User Management Tables (4)**
1. `users` - Core user accounts
2. `farmer_profiles` - Farmer-specific data
3. `officer_profiles` - Officer-specific data
4. `user_sessions` - Session management

#### **B. Crop Management Tables (2)**
1. `crop_data` - Crops grown by farmers
2. `crop_activities` - Activities on crops (planting, irrigation, etc.)

#### **C. Disease Management Tables (2)**
1. `disease_reports` - Detection reports
2. `disease_library` - Knowledge base

#### **D. Communication Tables (2)**
1. `ai_chat_logs` - AI assistant conversations
2. `chat_messages` - Direct messages between users

#### **E. Weather & Alerts Tables (2)**
1. `weather_data` - Weather information
2. `weather_alerts` - Severe weather alerts

#### **F. Market & Pricing Tables (1)**
1. `market_prices` - Agricultural product prices

#### **G. Notification Tables (2)**
1. `alerts` - User alerts/notifications
2. `notification_preferences` - User preferences

#### **H. Community Tables (3)**
1. `community_posts` - Forum posts
2. `post_comments` - Comments on posts
3. `post_likes` & `comment_likes` - Social engagement

#### **I. Recommendations Tables (3)**
1. `fertilizer_recommendations` - Fertilizer suggestions
2. `advisories` - Officer advisories
3. `ai_recommendations` - AI-generated suggestions

#### **J. Marketplace Tables (3)**
1. `marketplace_products` - Listed products
2. `product_inquiries` - Buyer inquiries
3. `marketplace_orders` - Completed orders

#### **K. Education Tables (2)**
1. `video_content` - Educational videos
2. `articles` - Blog articles

#### **L. Officer Activities Tables (1)**
1. `field_visits` - Officer field visit logs

#### **M. System Tables (4)**
1. `system_logs` - Activity logs
2. `file_uploads` - File management
3. `settings` - Configuration
4. `file_uploads` - Upload tracking

---

### Key Tables Detail

#### **1. users** (Core Authentication)
```
┌─────────────────────────────────────┐
│ users                               │
├─────────────────────────────────────┤
│ user_id (PK)                        │
│ email (UNIQUE)                      │
│ phone (UNIQUE)                      │
│ password_hash                       │
│ first_name, last_name               │
│ role: 'farmer'|'officer'|'admin'    │
│ is_active, is_verified              │
│ last_login, created_at, updated_at  │
└─────────────────────────────────────┘
```

#### **2. crop_data** (Crop Lifecycle)
```
┌──────────────────────────────────────┐
│ crop_data                            │
├──────────────────────────────────────┤
│ crop_id (PK)                         │
│ farmer_id (FK → users)               │
│ crop_name, variety                   │
│ planting_date, expected_harvest      │
│ area_hectares                        │
│ status: 'planning'|'growing'|...     │
│ expected_yield, actual_yield         │
│ created_at, updated_at               │
└──────────────────────────────────────┘
```

#### **3. disease_reports** (Disease Detection)
```
┌──────────────────────────────────────┐
│ disease_reports                      │
├──────────────────────────────────────┤
│ report_id (PK)                       │
│ user_id (FK → users)                 │
│ crop_id (FK → crop_data)             │
│ disease_name                         │
│ severity: 'low'|'medium'|'high'      │
│ confidence_score                     │
│ image_url                            │
│ treatment_recommended                │
│ status: 'detected'|'treating'|...    │
│ verified_by (FK → users - officer)   │
│ created_at, updated_at               │
└──────────────────────────────────────┘
```

#### **4. ai_chat_logs** (AI Interactions)
```
┌──────────────────────────────────────┐
│ ai_chat_logs                         │
├──────────────────────────────────────┤
│ log_id (PK)                          │
│ user_id (FK → users)                 │
│ user_message                         │
│ ai_response                          │
│ message_type: 'general'|'crop'|...   │
│ language: 'bangla'|'english'         │
│ rating (1-5)                         │
│ created_at                           │
└──────────────────────────────────────┘
```

#### **5. marketplace_products** (E-Commerce)
```
┌──────────────────────────────────────┐
│ marketplace_products                 │
├──────────────────────────────────────┤
│ product_id (PK)                      │
│ seller_id (FK → users)               │
│ product_name, description            │
│ price, quantity_available            │
│ status: 'available'|'sold'|...       │
│ image_url                            │
│ is_verified, verified_by             │
│ views, created_at                    │
└──────────────────────────────────────┘
```

#### **6. community_posts** (Social Features)
```
┌──────────────────────────────────────┐
│ community_posts                      │
├──────────────────────────────────────┤
│ post_id (PK)                         │
│ user_id (FK → users)                 │
│ title, content                       │
│ category, tags                       │
│ post_type: 'question'|'tip'|...      │
│ likes, views                         │
│ is_approved, is_featured             │
│ created_at, updated_at               │
└──────────────────────────────────────┘
```

#### **7. weather_data** (Environmental Data)
```
┌──────────────────────────────────────┐
│ weather_data                         │
├──────────────────────────────────────┤
│ weather_id (PK)                      │
│ location, district, region           │
│ latitude, longitude                  │
│ temperature, humidity, rainfall      │
│ wind_speed, wind_direction           │
│ weather_condition                    │
│ recorded_date, forecast_date         │
│ is_forecast (BOOLEAN)                │
└──────────────────────────────────────┘
```

#### **8. alerts** (Notifications)
```
┌──────────────────────────────────────┐
│ alerts                               │
├──────────────────────────────────────┤
│ alert_id (PK)                        │
│ user_id (FK → users)                 │
│ alert_type: 'weather'|'disease'|...  │
│ title, message                       │
│ priority: 'low'|'high'|'critical'    │
│ is_read, read_at                     │
│ sent_via: 'app'|'email'|'sms'|'all'  │
│ created_by (FK → users - officer)    │
│ expires_at                           │
└──────────────────────────────────────┘
```

---

### Database Relationships

**One-to-Many Relationships:**
- `users` → `farmer_profiles` (1:1)
- `users` → `crop_data` (1:M)
- `users` → `ai_chat_logs` (1:M)
- `users` → `community_posts` (1:M)
- `users` → `alerts` (1:M)
- `crop_data` → `disease_reports` (1:M)
- `crop_data` → `crop_activities` (1:M)
- `community_posts` → `post_comments` (1:M)
- `users` → `marketplace_products` (1:M seller)
- `users` → `marketplace_products` (1:M buyer - through orders)

**Many-to-Many Relationships:**
- `users` ↔ `community_posts` (through post_likes)
- `users` ↔ `post_comments` (through comment_likes)
- `users` ↔ `marketplace_products` (through marketplace_orders)

---

## � API & Data Flow Examples

### API Architecture Overview

```
Frontend (jQuery AJAX)
    ↓
POST/GET api/handler.php?action=...
    ↓
Route to Specific Handler (auth/crop/disease/etc)
    ↓
Validate Input & Check Authentication
    ↓
Database Operations (PDO Query)
    ↓
Format JSON Response
    ↓
Return to Frontend
    ↓
JavaScript Updates DOM
```

### Common API Endpoints & Data Flow

#### **1. User Registration**
**Endpoint:** `POST /api/handler.php?action=register`

**Request (Frontend):**
```javascript
const userData = {
    email: 'farmer@example.com',
    phone: '01712345678',
    password: 'securePass123',
    first_name: 'Ahmad',
    last_name: 'Rahman',
    role: 'farmer'
};

$.ajax({
    url: '/api/handler.php?action=register',
    type: 'POST',
    dataType: 'json',
    data: userData,
    success: function(response) {
        // response.success, response.user_id, response.message
        window.location.href = '/index.php?page=profile';
    }
});
```

**Backend Processing:**
```
1. Validate email format & uniqueness (SELECT FROM users WHERE email)
2. Validate phone format & uniqueness (SELECT FROM users WHERE phone)
3. Hash password using password_hash()
4. INSERT INTO users (email, phone, password_hash, first_name, ...)
5. Get inserted user_id
6. Return JSON: { success: true, user_id: 123, message: '...' }
```

**Database Entry:**
```
users table:
├─ user_id: 123 (AUTO_INCREMENT)
├─ email: 'farmer@example.com'
├─ phone: '01712345678'
├─ password_hash: '$2y$10$...' (bcrypt)
├─ first_name: 'Ahmad'
├─ last_name: 'Rahman'
├─ role: 'farmer'
├─ is_active: 1
├─ is_verified: 0
└─ created_at: 2025-12-18 10:30:45
```

#### **2. Get User's Crops**
**Endpoint:** `GET /api/handler.php?action=get-crops`

**Request Flow:**
```
Frontend → Load crops.php
    ↓
JavaScript: $.ajax({
    url: '/api/handler.php?action=get-crops',
    type: 'GET',
    dataType: 'json'
})
    ↓
Backend Checks Session: $_SESSION['user_id']
    ↓
Query: SELECT * FROM crop_data WHERE farmer_id = {user_id}
    ↓
Join with crop_activities (optional):
    SELECT cd.*, COUNT(ca.activity_id) as activity_count
    FROM crop_data cd
    LEFT JOIN crop_activities ca ON cd.crop_id = ca.crop_id
    WHERE cd.farmer_id = {user_id}
    GROUP BY cd.crop_id
    ↓
Format to JSON Array
```

**Response (JSON):**
```json
{
  "success": true,
  "crops": [
    {
      "crop_id": 1,
      "crop_name": "Rice",
      "variety": "BRRI 28",
      "area_hectares": 2.5,
      "planting_date": "2025-06-15",
      "expected_harvest": "2025-10-15",
      "status": "growing",
      "expected_yield": 12500,
      "activity_count": 5
    },
    {
      "crop_id": 2,
      "crop_name": "Wheat",
      "variety": "Shatabdi",
      "area_hectares": 1.5,
      "planting_date": "2025-11-01",
      "expected_harvest": "2026-03-15",
      "status": "planning",
      "expected_yield": 6000,
      "activity_count": 0
    }
  ]
}
```

**Frontend Processing:**
```javascript
response.crops.forEach(crop => {
    // Create crop card HTML
    // Display crop_name, status, area, expected_harvest
    // Add click handlers for edit/delete/view-activities
});
```

#### **3. Disease Detection Upload**
**Endpoint:** `POST /api/handler.php?action=analyze-disease`

**Request Flow:**
```
Frontend: User selects image file
    ↓
JavaScript FormData with image
    ↓
POST /api/handler.php?action=analyze-disease
    ↓
Backend: Validate uploaded file
    ├─ Check file size < MAX_UPLOAD_SIZE (50MB)
    ├─ Check file type: image/jpeg, image/png
    └─ Create unique filename: disease_{crop_id}_{timestamp}.jpg
    ↓
Save to: public/uploads/disease_images/
    ↓
Call Python AI Service (HTTP POST to http://localhost:5000/predict)
    ├─ Request: { image_path: '...', crop_id: 123 }
    │
    ├─ Python Model Processing:
    │  ├─ Load image using OpenCV
    │  ├─ Preprocess image (resize, normalize)
    │  ├─ Run TensorFlow/PyTorch model
    │  └─ Get predictions: disease_name, severity, confidence_score
    │
    └─ Response: {
         disease: 'Leaf Blast',
         severity: 'high',
         confidence: 0.87,
         symptoms: [...],
         treatment: [...]
       }
    ↓
Query disease_library for detailed info
    ├─ SELECT * FROM disease_library WHERE disease_name = 'Leaf Blast'
    └─ Get treatment options, prevention, organic remedies
    ↓
INSERT INTO disease_reports (crop_id, disease_name, severity, ...)
    ↓
Return JSON with detection results & recommendations
```

**Database Entry:**
```
disease_reports:
├─ report_id: 456 (AUTO_INCREMENT)
├─ user_id: 123 (farmer)
├─ crop_id: 1 (rice)
├─ disease_name: 'Leaf Blast'
├─ severity: 'high'
├─ confidence_score: 87.0
├─ image_url: '/public/uploads/disease_images/disease_1_1671438645.jpg'
├─ symptoms: 'Gray-brown spots on leaves...'
├─ treatment_recommended: 'Apply fungicide within 24 hours...'
├─ status: 'detected'
├─ verified_by: NULL (waiting for officer verification)
└─ created_at: 2025-12-18 10:35:20
```

#### **4. AI Chat Assistant**
**Endpoint:** `POST /api/handler.php?action=send-message`

**Request & Response Flow:**
```
User Types: "আমার ধান ক্ষেতে এই সমস্যা পাচ্ছি..."  (In Bangla)
    ↓
JavaScript AJAX:
POST /api/handler.php?action=send-message
{
    message: "আমার ধান ক্ষেতে এই সমস্যা পাচ্ছি...",
    language: "bangla",
    context: {
        crop_id: 1,
        crop_name: "Rice",
        region: "Mymensingh"
    }
}
    ↓
Backend:
1. Validate user session
2. INSERT INTO ai_chat_logs (user_id, user_message, language, ...)
3. Extract message intent using NLP
4. Get user context (crops, location, history)
    ↓
Call Python NLP Service:
{
    message: "আমার ধান ক্ষেতে এই সমস্যা পাচ্ছি...",
    language: "bangla",
    user_context: {
        crops: ["Rice"],
        region: "Mymensingh",
        experience: "beginner",
        previous_diseases: [...]
    }
}
    ↓
Python Service:
├─ Translate Bangla to English (optional)
├─ Analyze sentiment & intent
├─ Query relevant knowledge base:
│  ├─ For rice diseases in Mymensingh
│  ├─ Weather data for region
│  ├─ Market prices
│  └─ Community forum solutions
├─ Generate response using LLM/NLP model
├─ Translate response back to Bangla
└─ Return: { response: "...", intent: "disease", confidence: 0.95 }
    ↓
Backend:
1. UPDATE ai_chat_logs SET ai_response = '...'
2. Optional: Text-to-speech conversion
3. Return JSON response
    ↓
Frontend:
├─ Display response in chat bubble
├─ Play audio if TTS enabled
├─ Show feedback buttons (helpful, not helpful)
└─ Store for rating
```

**Database Entry:**
```
ai_chat_logs:
├─ log_id: 789 (AUTO_INCREMENT)
├─ user_id: 123
├─ user_message: "আমার ধান ক্ষেতে এই সমস্যা পাচ্ছি..."
├─ ai_response: "আপনার ধানের সমস্যা দেখে মনে হচ্ছে লিফ ব্লাস্ট..."
├─ message_type: 'disease'
├─ language: 'bangla'
├─ sentiment: 'concerned'
├─ rating: 5 (user rated after)
└─ created_at: 2025-12-18 10:40:15
```

#### **5. Marketplace Product Listing**
**Endpoint:** `GET /api/handler.php?action=get-marketplace`

**Request & Response:**
```
Frontend: Load marketplace.php with filters
    ↓
Query Parameters:
?action=get-marketplace
&crop_type=rice
&region=mymensingh
&price_min=500
&price_max=1500
&sort=newest
    ↓
Backend Query:
SELECT mp.*, u.first_name, u.last_name, fp.region
FROM marketplace_products mp
JOIN users u ON mp.seller_id = u.user_id
JOIN farmer_profiles fp ON u.user_id = fp.user_id
WHERE mp.status = 'available'
  AND mp.product_type = 'rice'
  AND fp.region = 'mymensingh'
  AND mp.price BETWEEN 500 AND 1500
ORDER BY mp.created_at DESC
    ↓
Format JSON Response with product details
    ↓
Frontend: Display product cards with:
├─ Product image & name
├─ Seller name & rating
├─ Price & quantity
├─ Location & delivery info
└─ Action buttons: View Details, Ask Seller, Buy
```

**Response Example:**
```json
{
  "success": true,
  "products": [
    {
      "product_id": 1,
      "product_name": "BRRI 28 Rice",
      "description": "High-quality rice, harvested 2 weeks ago",
      "price": 750,
      "price_unit": "per 20kg sack",
      "quantity_available": 50,
      "seller_name": "Farmer Ahmad",
      "seller_region": "Mymensingh",
      "image_url": "/uploads/marketplace/rice_1.jpg",
      "quality_grade": "A",
      "status": "available",
      "views": 245
    }
  ],
  "total": 1,
  "filters_applied": {
    "crop_type": "rice",
    "region": "mymensingh",
    "price_range": [500, 1500]
  }
}
```

---

## 🔐 Data Security & Authentication

### Session-Based Authentication Flow

```
┌─ User Login
│   └─→ Verify credentials
│       └─→ Generate Session ID
│           └─→ Set $_SESSION['user_id'] = 123
│               └─→ Set $_SESSION['user_role'] = 'farmer'
│                   └─→ Store in server-side storage
│
└─ Subsequent Requests
    └─→ Check if session exists
        └─→ Verify session timeout (3600 seconds)
            └─→ Load user data if valid
                └─→ Proceed with request
```

### Data Protection Measures

1. **Password Security:**
   - Hashed with `password_hash()` (bcrypt)
   - Never stored as plain text
   - Min length: 8 characters
   - Check strength on frontend

2. **Session Security:**
   - 1-hour timeout
   - HttpOnly cookies (prevent XSS)
   - SameSite=Lax (prevent CSRF)
   - Secure flag for HTTPS

3. **Database Security:**
   - Prepared statements (PDO) prevent SQL injection
   - User input sanitized before queries
   - Role-based access control (farmer/officer/admin)
   - Indexes on frequently queried columns

4. **File Security:**
   - Uploaded files validated by type & size
   - Stored outside web root when possible
   - Path traversal protection
   - Permission checks before download

---

## �📊 Work Plan & Timeline

### Phase 1: Foundation (Months 1-2)
**Focus:** Backend Setup, Database, Core Authentication

- Week 1-2: Server setup, database configuration, user authentication system
- Week 3-4: API handlers for login/register, profile management
- Week 5-6: Farmer profile creation, location auto-detection
- Week 7-8: Testing, bug fixes, documentation

**Deliverables:** User authentication, profile system, database ready

### Phase 2: Core Features (Months 3-5)
**Focus:** AI Integration, Weather, Disease Detection

- Month 3:
  - AI Chat (Chashi Bhai) integration
  - Weather API integration
  - Real-time alerts system
  
- Month 4:
  - Disease detection model training
  - Image upload/processing
  - Results display system
  
- Month 5:
  - Yield prediction models
  - Marketplace backend
  - Community forum setup

**Deliverables:** Functional AI chat, disease detection, marketplace

### Phase 3: Enhancement (Months 6-8)
**Focus:** Advanced Features, Optimization, Flutter Wrapper

- Month 6:
  - Fertilizer/irrigation recommendations
  - Video platform backend
  - Officer dashboard
  
- Month 7:
  - Flutter WebView wrapper (optional)
  - Push notifications setup
  - Performance optimization
  
- Month 8:
  - Testing & bug fixes
  - User documentation
  - Deployment preparation

**Deliverables:** Complete platform, Flutter app, deployment ready

---

## 🎨 Color Scheme & Design

### Primary Colors
- **Primary Green:** #557A46 (Agricultural, trustworthy)
- **Secondary Green:** #8FBC46 (Fresh, vibrant)
- **Accent Orange:** #FF8C00 (Energy, call-to-action)

### Neutral Colors
- **Text:** #4F4F4F (Dark gray, readable)
- **Light Background:** #FAFAF8 (Off-white, eye-friendly)
- **Border:** #E0E0E0 (Light gray)

### Component Sizes (Mobile-First)
- **Minimum touch target:** 44px × 44px
- **Header:** 56px (mobile), 64px (desktop)
- **Bottom navigation:** 56px
- **Card padding:** 16px
- **Font sizes:** 14px (body), 16px (inputs), 18-28px (headings)

---

## 💰 Monetization & Sustainability

### Revenue Streams

1. **Government Partnerships**
   - Data insights for policy making
   - Extension officer licensing
   - Integration with agricultural programs

2. **Freemium Farmer Services**
   - Free: Basic chat, weather, alerts
   - Premium: Advanced recommendations ($3-5/month)
   - Enterprise: Cooperative solutions

3. **Marketplace Commission** (3-5%)
   - Featured product listings
   - Quality certification premium

4. **Data Insights & Analytics**
   - Anonymized crop health trends
   - Demand predictions
   - Regional agricultural reports

5. **NGO & UN SDG Funding**
   - Sustainable Development Goal alignment
   - Climate adaptation funding
   - Agricultural innovation grants

---

## 🌍 Social Impact

### Sustainable Development Goals (SDGs) Alignment
- **SDG 2 (Zero Hunger):** Improve productivity & food security  
- **SDG 8 (Decent Work):** Create employment in agri-tech
- **SDG 13 (Climate Action):** Climate-resilient agriculture support

### Key Impact Metrics
- **Farmer Adoption:** Target 50,000+ farmers in Year 1
- **Yield Improvement:** Average 20-30% increase
- **Cost Reduction:** 15-25% reduction in input costs
- **Women Farmers:** 40% of user base by Year 2
- **Youth Engagement:** 10,000+ youth attracted

### Ethical Considerations
- **Data Privacy:** End-to-end encryption, GDPR-compliant
- **Explainable AI:** Farmers understand recommendations
- **Bangla-First Design:** Native language support
- **Inclusive Access:** Low-bandwidth friendly
- **No Lock-in:** Data portability, open standards

---

## 🚀 Getting Started

### Prerequisites

**Backend Server:**
- Web Server: Apache/Nginx (Apache with mod_rewrite enabled for clean URLs)
- PHP: 7.4 or higher
- MySQL: 5.7 or higher
- Python: 3.8+

**Local Development:**
- PHP Development Server or XAMPP/WAMP
- Chrome/Firefox DevTools (test mobile view)
- Node.js for frontend build tools (optional)

### Frontend File Structure & Routing

The frontend uses PHP files with clean URLs for better SEO and user experience.

**File Structure:**
```
smart-chashi/
├── .htaccess                 (URL rewriting rules)
├── public/
│   ├── css/
│   ├── js/
│   ├── images/
│   ├── uploads/             (User uploads - dynamically served)
│   └── api/                 (File blob serving)
├── index.php                (Main entry - routes all requests)
├── dashboard.php            (Farmer dashboard)
├── crops.php                (Crop management)
├── disease.php              (Disease detection)
├── chat.php                 (AI Chat interface)
├── profile.php              (User profile)
├── alerts.php               (Weather & alerts)
├── marketplace.php          (Marketplace)
├── community.php            (Community forum)
├── videos.php               (Video platform)
├── admin-dashboard.php      (Officer dashboard)
└── config/
    └── config.php           (Database & app config)
```

### .htaccess Configuration

Create `.htaccess` in the project root for clean URL routing:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /smart-chashi/

    # Redirect HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # Skip .htaccess
    RewriteRule ^\.htaccess$ - [F]

    # Skip real directories and files
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d

    # Route to index.php for page requests
    RewriteRule ^([a-zA-Z0-9_-]+)/?$ index.php?page=$1 [QSA,L]
    RewriteRule ^([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)/?$ index.php?page=$1&id=$2 [QSA,L]

    # API endpoints
    RewriteRule ^api/([a-zA-Z0-9_-]+)/?$ api/handler.php?action=$1 [QSA,L]

    # File blob serving
    RewriteRule ^files/([a-zA-Z0-9_-]+)/?$ public/api/file-blob.php?file=$1 [QSA,L]

    # Cache control headers
    <FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|svg|webp)$">
        Header set Cache-Control "max-age=31536000, public"
    </FilesMatch>
</IfModule>
```

### Clean URL Examples

| Old URL | Clean URL | PHP File |
|---------|-----------|----------|
| `index.php?page=dashboard` | `/dashboard` | `dashboard.php` |
| `crops.php?id=5` | `/crops/5` | `crops.php` |
| `disease.php?crop_id=3` | `/disease/3` | `disease.php` |
| `user.php?id=user123` | `/profile/user123` | `profile.php` |
| `api/handler.php?action=login` | `/api/login` | `api/handler.php` |
| `upload/serve.php?id=abc123` | `/files/abc123` | `public/api/file-blob.php` |

### File Blob URL Serving

**File Storage & Serving Structure:**

```php
// File stored in: public/uploads/user_123/disease_abc123.jpg
// Access via clean URL: /files/abc123

// public/api/file-blob.php
<?php
session_start();
include '../../../config/config.php';

$file_id = $_GET['file'] ?? null;

if (!$file_id) {
    http_response_code(404);
    die('File not found');
}

// Retrieve file path from database
$db = new Database();
$file_info = $db->single('SELECT * FROM file_uploads WHERE file_id = ?', [$file_id]);

if (!$file_info) {
    http_response_code(404);
    die('File not found');
}

$file_path = __DIR__ . '/../../uploads/' . $file_info['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Check user permissions
if ($file_info['user_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    die('Unauthorized');
}

// Serve file with proper headers
header('Content-Type: ' . $file_info['mime_type']);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Cache-Control: max-age=31536000');

readfile($file_path);
?>
```

### Installation

**Backend Setup:**
1. Clone the repository to your Apache web root (e.g., `htdocs/smart-chashi/`)
2. Copy `.htaccess` file to project root
3. Configure `.env` file with database credentials (or use `config/config.php`)
4. Run database migrations: `php config/migrate.php`
5. Install Python dependencies: `pip install -r requirements.txt`
6. Enable Apache mod_rewrite: `a2enmod rewrite` (Linux)
7. Restart Apache: `systemctl restart apache2` (Linux) or restart XAMPP
8. Start Python AI service: `python app.py` (runs on :5000)

**Frontend Testing (Web/Browser):**
1. Navigate to `http://localhost/smart-chashi/` or configured domain
2. Test clean URLs: Visit `/dashboard`, `/crops/5`, `/disease/3`
3. Test responsive view: Press F12 → Toggle Device Toolbar
4. Test file uploads & blob serving: Upload image, verify `/files/xxx` URL works
5. Verify offline support: DevTools → Network → Offline
6. Check performance: DevTools → Lighthouse → Mobile

### Configuration

**Backend (config/config.php):**
```php
<?php
// Database
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'smart_chashi');
define('DB_USER', 'root');
define('DB_PASS', 'password');

// App Settings
define('APP_NAME', 'Smart Chashi');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development');
define('APP_DEBUG', true);

// Paths
define('PROJECT_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', PROJECT_ROOT . '/public/uploads/');
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB

// Session
define('SESSION_TIMEOUT', 3600);
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'secure' => false,      // Set true for HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_TIMEOUT', 3600);

// External APIs
define('GOOGLE_MAPS_API_KEY', 'your_key_here');
define('OPENWEATHER_API_KEY', 'your_key_here');
define('OPENAI_API_KEY', 'your_key_here');

// Python Service
define('PYTHON_SERVICE_URL', 'http://localhost:5000');

// File serving
define('FILE_BLOB_EXPIRY', 86400); // 24 hours

function getCleanUrl($page, $params = []) {
    $url = '/' . $page;
    if (!empty($params)) {
        $url .= '/' . implode('/', $params);
    }
    return $url;
}
?>
```

---

## 📞 Support & Documentation

- **User Documentation:** Wiki pages with guides
- **Developer Documentation:** API documentation
- **Video Tutorials:** In-app learning content
- **Community Forum:** Peer support
- **Technical Support:** support@cashiibhai.com

---

## 📄 License

This project is licensed under the MIT License - see LICENSE file for details.

---

## 👥 Team & Contributors

- **Project Lead:** [Your Name]
- **Frontend Developer:** [Name]
- **Backend Developer:** [Name]
- **AI/ML Engineer:** [Name]
- **Database Administrator:** [Name]
- **UI/UX Designer:** [Name]

---

## 📞 Contact & Links

- **Website:** www.cashiibhai.com
- **Email:** info@cashiibhai.com
- **GitHub:** github.com/cachhibhai
- **LinkedIn:** [Company Page]

---

**Last Updated:** December 2025
**Version:** 1.0.0 - jQuery AJAX + PHP Architecture

---

### 🌾 Vision Statement

> To empower every farmer in South Asia with AI-driven intelligence, transforming agriculture from tradition-based to data-driven, sustainable, and profitable.

**Smart Chashi - Where Technology Meets Tradition in Farming** 🌾🤖
