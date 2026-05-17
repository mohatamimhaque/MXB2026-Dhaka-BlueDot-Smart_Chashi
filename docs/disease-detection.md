# Disease Detection

> Smart Chashi's disease detection module lets farmers upload a photo of a sick crop and receive an AI-powered diagnosis — disease name, severity, confidence score, causes, and full treatment recommendations — in both English and Bengali. The system runs a 4-tier detection pipeline with an optional standalone Python ML service backed by a trained EfficientNet-B0 deep learning model.

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Detection Pipeline](#detection-pipeline)
4. [Tier 1 — Python ML Service (EfficientNet-B0)](#tier-1--python-ml-service-efficientnet-b0)
5. [Tier 2 — Google Gemini Vision AI](#tier-2--google-gemini-vision-ai)
6. [Tier 3 — PHP GD Color Analysis](#tier-3--php-gd-color-analysis)
7. [Tier 4 — Mock Detection Fallback](#tier-4--mock-detection-fallback)
8. [Python ML Backend (Standalone Service)](#python-ml-backend-standalone-service)
9. [Supported Diseases (38 Classes)](#supported-diseases-38-classes)
10. [Disease Library Database](#disease-library-database)
11. [Database Tables](#database-tables)
12. [API Reference](#api-reference)
13. [File Structure](#file-structure)
14. [Setup & Running](#setup--running)
    - [Option A — One-Click `run.bat`](#option-a--one-click-launch-recommended)
    - [Option B — PHP Only](#option-b--php-integration-only-no-python)
    - [Option C — Manual](#option-c--manual-start-advanced)
15. [Flowchart](#flowchart)
16. [What Is Accepted / Rejected](#what-is-accepted--rejected)

---

## Overview

| Attribute | Value |
|-----------|-------|
| **Entry URL** | `/smartchashi/disease` |
| **Page file** | `pages/disease.php` |
| **API endpoint** | `api/disease/analyze.php` |
| **ML service** | `Disease detection/backend/production_server.py` (port 5000) |
| **Auth required** | Yes — must be logged in |
| **Input** | JPEG or PNG image, max 5 MB (PHP); max 10 MB (ML service) |
| **Output** | Disease name, severity (low/medium/high), confidence (0–1), causes, treatment, api_used |
| **Languages** | English + Bengali (bilingual response from ML service) |
| **DB tables** | `disease_library`, `disease_reports`, `disease_report_responses` |

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FARMER BROWSER                               │
│  pages/disease.php                                                  │
│  • Crop selector (from crop_data table)                             │
│  • Drag & drop / camera image upload                                │
│  • Real-time progress bar                                           │
│  • Result card: disease · severity · confidence · treatment         │
└──────────────────────────────┬──────────────────────────────────────┘
                               │ multipart/form-data
                               │ POST /api/disease/analyze.php
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│              api/disease/analyze.php (PHP)                          │
│                                                                     │
│  1. Session auth check                                              │
│  2. MIME validate (jpeg/png only)                                   │
│  3. Size check (≤ 5 MB)                                             │
│  4. Save to tmp file                                                │
│                                                                     │
│  ┌─────────────────── FALLBACK CHAIN ───────────────────────────┐   │
│  │                                                               │   │
│  │  TIER 1  detectDiseaseWithTensorFlow()                        │   │
│  │          → shell_exec python3 ml/disease_detector.py         │   │
│  │          → calls ML service on port 5000                     │   │
│  │          → EfficientNet-B0 · 38 classes · 60% threshold      │   │
│  │          → NULL if Python not found / service down           │   │
│  │                                                               │   │
│  │  TIER 2  detectDiseaseWithGemini()                            │   │
│  │          → base64 image + expert prompt                       │   │
│  │          → Gemini 1.5 Flash (gemini-1.5-flash)               │   │
│  │          → JSON response parsed into disease/severity/...    │   │
│  │          → NULL if GEMINI_API_KEY not set                     │   │
│  │                                                               │   │
│  │  TIER 3  detectDiseaseByImageAnalysis()                       │   │
│  │          → PHP GD pixel sampling (100 pixels)                │   │
│  │          → RGB color channels → yellowness / brownness /     │   │
│  │            greenness / darkness / variance metrics           │   │
│  │          → Rule-based disease mapping (crop-aware)           │   │
│  │          → NULL if image unreadable                          │   │
│  │                                                               │   │
│  │  TIER 4  detectDiseaseAdvancedMock()                          │   │
│  │          → Crop-specific disease database (rice/wheat/       │   │
│  │            tomato/potato/general)                            │   │
│  │          → Deterministic via md5_file() hash mod count       │   │
│  │          → Always returns a result                           │   │
│  │                                                               │   │
│  └───────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  5. If cropId provided → INSERT into disease_reports                │
│  6. Return JSON {success, data: {disease, severity, confidence,     │
│                  treatment, causes, api_used}}                      │
└─────────────────────────────────────────────────────────────────────┘
                               │
          ┌────────────────────┼─────────────────────────┐
          ▼                    ▼                         ▼
  ┌───────────────┐   ┌─────────────────┐   ┌──────────────────────┐
  │  ML Service   │   │  Gemini Vision  │   │  disease_reports DB  │
  │ port 5000     │   │  API (Google)   │   │  (if crop linked)    │
  │ FastAPI/uvicorn│   │  1.5 Flash      │   │                      │
  └───────────────┘   └─────────────────┘   └──────────────────────┘
```

---

## Detection Pipeline

The PHP handler runs a **strict 4-tier fallback chain**. Each tier is tried in sequence; if it returns `null` the next tier is attempted. The chain guarantees a result.

```
Upload received
      │
      ▼
[Validate: JPEG/PNG, ≤5MB, session]
      │
      ├──► FAIL → 400 error JSON
      │
      ▼
[TIER 1] Python ML Service (EfficientNet-B0)
      │
      ├──► SUCCESS → return result ──────────────────────────────────┐
      │                                                              │
      ├──► NULL (Python not found / service down)                    │
      │                                                              │
      ▼                                                              │
[TIER 2] Google Gemini Vision AI                                     │
      │                                                              │
      ├──► SUCCESS → return result ───────────────────────────────── ┤
      │                                                              │
      ├──► NULL (no API key / quota exceeded / HTTP error)           │
      │                                                              │
      ▼                                                              │
[TIER 3] PHP GD Color Analysis                                       │
      │                                                              │
      ├──► SUCCESS → return result ───────────────────────────────── ┤
      │                                                              │
      ├──► NULL (GD extension not available)                         │
      │                                                              │
      ▼                                                              │
[TIER 4] Mock Detection (crop-specific disease DB)                   │
      │                                                              │
      └──► ALWAYS returns result ──────────────────────────────────── ┘
                                                │
                                                ▼
                              [IF crop_id given → INSERT disease_reports]
                                                │
                                                ▼
                              JSON response → browser
```

---

## Tier 1 — Python ML Service (EfficientNet-B0)

**Function:** `detectDiseaseWithTensorFlow($imagePath, $cropId)`

This tier delegates to the Python ML service via `shell_exec`:

```php
$command = "python3 ml/disease_detector.py <image_path> <crop_name>"
$output = shell_exec($command);
$result = json_decode($output, true);
```

### What the ML service does

The `Disease detection/backend/production_server.py` is a **FastAPI server** (port 5000) with a 3-stage internal pipeline:

```
POST /api/detect (multipart/form-data)
        │
        ▼
[Stage 1] validate_image()
  • Check extension: jpg/jpeg/png/webp/bmp
  • Check size: 1 KB – 10 MB
  • Check dimensions: 50×50 – 4096×4096
  • Convert to RGB
  • Auto-resize if > 4096 on either axis
        │
        ▼
[Stage 2] CropClassifier.classify()
  • Color analysis: % green pixels (> 15% = plant)
  • Texture variance (> 500 = detailed pattern)
  • Optional TF model (if model file present)
  • Returns: is_crop (bool) + confidence
  • Threshold: 55% — images below this are rejected as non-plant
        │
        ├──► NOT A CROP → {"status": "error", "message": "Not a plant image"}
        │
        ▼
[Stage 3] DiseaseClassifier.classify()
  • Load: disease_model_pytorch.pth (or disease_model_merged_best.pth)
  • Architecture: EfficientNet-B0 backbone
  •   + Dropout(0.3) → Linear(in_features, 512) → ReLU
  •   + Dropout(0.3) → Linear(512, num_classes)
  • Preprocessing: Resize(224,224) → ToTensor → Normalize([0.485,0.456,0.406],[0.229,0.224,0.225])
  • Inference: model.eval() + torch.no_grad()
  • Output: softmax probabilities over 38 classes
  • Confidence threshold: 60% — below this returns "Uncertain"
  • Device: CUDA (GPU) if available, else CPU
        │
        ▼
[Stage 4] Look up solution in solutions_db (bilingual EN/BN)
  • Returns chemical, organic, prevention
  • Full Bengali (বাংলা) translations for all fields
```

### Response format from ML service

```json
{
  "status": "success",
  "crop": "Tomato",
  "disease": "Early Blight",
  "disease_bn": "প্রাথমিক ধ্বসা রোগ",
  "confidence": 87.3,
  "solution": {
    "chemical": "Apply Mancozeb 75% WP @ 2.5g/L weekly...",
    "chemical_bn": "মানকোজেব ৭৫% WP @ ২.৫ গ্রাম/লিটার প্রতি সপ্তাহে প্রয়োগ করুন...",
    "organic": "Use Bacillus subtilis, remove infected parts...",
    "organic_bn": "ব্যাসিলাস সাবটিলিস ব্যবহার করুন, আক্রান্ত অংশ সরিয়ে ফেলুন...",
    "prevention": "Remove lower leaves, ensure good air circulation...",
    "prevention_bn": "নিচের পাতা সরিয়ে ফেলুন, ভালো বায়ু চলাচল নিশ্চিত করুন..."
  }
}
```

---

## Tier 2 — Google Gemini Vision AI

**Function:** `detectDiseaseWithGemini($imagePath, $cropId)`

Used when the Python ML service is unavailable.

### How it works

1. Read and base64-encode the image
2. Build an expert agricultural pathologist prompt:
   ```
   "You are an expert agricultural pathologist. Analyze this {crop_name} image..."
   "Provide JSON: {disease, severity, confidence, causes, treatment, symptoms}"
   ```
3. POST to `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent`
4. Parse JSON from `candidates[0].content.parts[0].text`
5. Strip markdown code fences (`\`\`\`json`) if present
6. Validate and normalize: severity clamp to `low/medium/high`, confidence clamp to `0–1`

### Config required

```php
define('GEMINI_API_KEY', 'AIza...');  // in config/config.php
```

**Free tier:** 60 requests/minute, 1,500 requests/day  
**Timeout:** 30 seconds (cURL)

---

## Tier 3 — PHP GD Color Analysis

**Function:** `detectDiseaseByImageAnalysis($imagePath, $cropId)`

No external API required. Uses PHP's built-in GD library.

### Color metrics extracted

```
Sample 100 pixels across the full image (step = sqrt(W*H/100))
For each pixel: R, G, B values

avgR, avgG, avgB = average channel values

yellowness = (avgR + avgG) / 2 - avgB      # High = yellow/rust symptoms
brownness  = min(avgR, avgG, avgB)          # High = brown discoloration
greenness  = avgG - (avgR + avgB) / 2      # High = healthy plant
darkness   = (avgR + avgG + avgB) / 3      # Low = severe damage

variance   = mean squared RGB deviation     # High (>2000) = spots/patterns
```

### Detection rules

| Condition | Detected Disease |
|-----------|-----------------|
| `greenness > 30` and no spots and `darkness > 80` | Healthy Plant |
| `yellowness > 40` and spots and crop is rice | Rice Brown Spot |
| `yellowness > 40` and spots (other crops) | Fungal Leaf Spot |
| `yellowness > 50` and crop is wheat | Wheat Yellow Rust |
| `yellowness > 50` (other crops) | Nitrogen Deficiency |
| `brownness > 50` and `darkness < 100` and tomato/potato | Late Blight |
| `brownness > 50` and `darkness < 100` (other crops) | Leaf Blight |
| `greenness < 20` and `darkness > 120` | Powdery Mildew |
| `darkness < 80` | Severe Plant Stress |
| Default | Moderate Plant Stress |

---

## Tier 4 — Mock Detection Fallback

**Function:** `detectDiseaseAdvancedMock($imagePath, $cropId)`

Always returns a result. Used only when all other tiers fail.

### Disease database by crop

| Crop Match | Diseases in Database |
|------------|---------------------|
| rice / paddy | Rice Blast Disease, Brown Spot |
| wheat | Yellow Rust (Stripe Rust), Powdery Mildew |
| tomato | Early Blight, Late Blight, Bacterial Wilt |
| potato | Late Blight |
| general (all others) | Fungal Leaf Spot, Bacterial Leaf Spot, Nutrient Deficiency |

**Selection method:** `md5_file($imagePath)` → first 8 hex chars → `hexdec() % count($diseases)` — same image always gets the same disease from the crop's list.

---

## Python ML Backend (Standalone Service)

Located in `Disease detection/backend/`. This is an independent FastAPI microservice that the PHP tier 1 calls via `shell_exec`.

### Architecture

```
Disease detection/
├── backend/
│   ├── production_server.py   ← FastAPI app (port 5000)
│   ├── crop_classifier.py     ← Stage 2: Is this a plant?
│   ├── disease_classifier.py  ← Stage 3: What disease?
│   ├── multi_model_classifier.py ← Ensemble mode (currently disabled)
│   └── models/
│       ├── disease_model_pytorch.pth     ← Primary EfficientNet-B0
│       ├── disease_model_merged_best.pth ← Alternative merged model
│       └── class_names.json              ← 38 class names
├── templates/
│   └── disease.html           ← Standalone web UI (Flask template)
├── static/
│   └── uploads/disease_images/ ← Detection history images
├── venv/                       ← Python virtual environment
└── README.md
```

### ML Model Architecture

```
EfficientNet-B0 (ImageNet pretrained backbone, frozen)
        │
        ▼
Dropout(p=0.3)
        │
        ▼
Linear(1280 → 512)
        │
        ▼
ReLU
        │
        ▼
Dropout(p=0.3)
        │
        ▼
Linear(512 → 38)     ← num_classes = 38
        │
        ▼
Softmax probabilities
```

**Input preprocessing:**
```python
transforms.Compose([
    transforms.Resize((224, 224)),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],  # ImageNet mean
        std =[0.229, 0.224, 0.225]   # ImageNet std
    )
])
```

**Training dataset:** [vipoooool/new-plant-diseases-dataset](https://www.kaggle.com/datasets/vipoooool/new-plant-diseases-dataset) — 87,000+ plant leaf images across 38 classes.

### Ensemble Mode (MultiModelClassifier)

Currently disabled (`USE_MULTI_MODEL_ENSEMBLE = False`). When enabled:

```
Primary:    PyTorch EfficientNet-B0
Secondary:  TensorFlow/Keras model
Fallback:   Color/Pattern analysis

Ensemble strategy:
  • All models above MIN_CONFIDENCE_FOR_ENSEMBLE (40%) contribute
  • Agreement boost: +10% confidence when models agree on same class
  • Weighted average of probabilities
  • Final class = argmax of weighted ensemble
```

### FastAPI Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/` | GET | Service health check |
| `/api/detect` | POST | Main detection (multipart/form-data: `file`, optional `crop_type`) |
| `/api/health` | GET | Detailed health: models loaded, device, class count |

---

## Supported Diseases (38 Classes)

The ML model is trained on these 38 classes from the PlantVillage dataset:

| # | Crop | Disease | Bengali Name |
|---|------|---------|-------------|
| 1 | Apple | Apple Scab | আপেল স্ক্যাব |
| 2 | Apple | Black Rot | ব্ল্যাক রট |
| 3 | Apple | Cedar Apple Rust | সিডার আপেল রাস্ট |
| 4 | Apple | Healthy | সুস্থ |
| 5 | Blueberry | Healthy | সুস্থ |
| 6 | Cherry | Powdery Mildew | পাউডারি মিলডিউ |
| 7 | Cherry | Healthy | সুস্থ |
| 8 | Corn/Maize | Cercospora Leaf Spot / Gray Leaf Spot | সার্কোস্পোরা লিফ স্পট |
| 9 | Corn/Maize | Common Rust | সাধারণ মরিচা |
| 10 | Corn/Maize | Northern Leaf Blight | উত্তরীয় পাতার ঝলসানো |
| 11 | Corn/Maize | Healthy | সুস্থ |
| 12 | Grape | Black Rot | কালো পচন |
| 13 | Grape | Esca (Black Measles) | এস্কা রোগ |
| 14 | Grape | Leaf Blight (Isariopsis Leaf Spot) | পাতার ঝলসানো |
| 15 | Grape | Healthy | সুস্থ |
| 16 | Orange | Haunglongbing (Citrus Greening) | সাইট্রাস গ্রিনিং |
| 17 | Peach | Bacterial Spot | ব্যাকটেরিয়াল দাগ |
| 18 | Peach | Healthy | সুস্থ |
| 19 | Pepper (Bell) | Bacterial Spot | ব্যাকটেরিয়াল দাগ |
| 20 | Pepper (Bell) | Healthy | সুস্থ |
| 21 | Potato | Early Blight | প্রাথমিক ধ্বসা |
| 22 | Potato | Late Blight | দেরি ধ্বসা |
| 23 | Potato | Healthy | সুস্থ |
| 24 | Raspberry | Healthy | সুস্থ |
| 25 | Soybean | Healthy | সুস্থ |
| 26 | Squash | Powdery Mildew | পাউডারি মিলডিউ |
| 27 | Strawberry | Leaf Scorch | পাতার পোড়া রোগ |
| 28 | Strawberry | Healthy | সুস্থ |
| 29 | Tomato | Bacterial Spot | ব্যাকটেরিয়াল দাগ |
| 30 | Tomato | Early Blight | প্রাথমিক ধ্বসা রোগ |
| 31 | Tomato | Late Blight | দেরিতে আসা ধ্বসা |
| 32 | Tomato | Leaf Mold | পাতার ছাঁচ |
| 33 | Tomato | Septoria Leaf Spot | সেপ্টোরিয়া দাগ |
| 34 | Tomato | Spider Mites (Two-spotted Spider Mite) | মাকড়সা মাইট |
| 35 | Tomato | Target Spot | টার্গেট দাগ |
| 36 | Tomato | Tomato Yellow Leaf Curl Virus | টমেটো হলুদ পাতা কোঁকড়া ভাইরাস |
| 37 | Tomato | Tomato Mosaic Virus | টমেটো মোজাইক ভাইরাস |
| 38 | Tomato | Healthy | সুস্থ |

---

## Disease Library Database

The `disease_library` table is a curated knowledge base with **22 entries** covering common Bangladeshi crop diseases and pests. This is used by the advisory and learning systems.

| # | Disease (EN) | Disease (BN) | Affected Crops | Severity |
|---|-------------|-------------|----------------|---------|
| 1 | Rice Blast | ধানের বিস্ট রোগ | Rice | High |
| 2 | Rice Brown Spot | বাদামী দাগ রোগ | Rice | Medium |
| 3 | Rice Sheath Blight | পাতার আবরণ পচা রোগ | Rice | High |
| 4 | Powdery Mildew | সাদা গুঁড়ো রোগ | Wheat, Vegetables | Low |
| 5 | Leaf Spot | পাতায় দাগ রোগ | Vegetables, Crops | Medium |
| 6 | Early Blight | প্রাথমিক ধ্বসা রোগ | Tomato | Medium |
| 7 | Late Blight | দেরিতে আসা ধ্বসা রোগ | Tomato, Potato | High |
| 8 | Bacterial Wilt | ব্যাকটেরিয়াল ঢলে পড়া | Tomato, Potato | High |
| 9 | Mosaic Virus | মোজাইক ভাইরাস | Vegetables, Crops | High |
| 10 | Rust | মরিচা রোগ | Wheat, Vegetables | Medium |
| 11 | Anthracnose | অ্যানথ্রাকনোজ রোগ | Pepper, Vegetables | Medium |
| 12 | Damping Off | গাছের শুকিয়ে পড়া | All Crops | High |
| 13 | Leaf Miner | পাতার খোদাইকারী | Vegetables | Low |
| 14 | Aphids | জাসিড | All Vegetables | Medium |
| 15 | Whitefly | সাদা মাছি | Tomato, Vegetables | Medium |
| 16 | Fruit Fly | ফল উড়াল | Fruits | High |
| 17 | Stem Borer | কান্ড ছেদকারী | Rice, Corn | High |
| 18 | Slug & Snail | শামুক ও স্লাগ | Vegetables | Low |
| 19 | Caterpillar | শুঁয়োপোকা | Vegetables, Cabbage | Medium |
| 20 | Gall Midge | গল মিজ | Rice | Medium |
| 21 | Grain Moth | শস্য পোকা | Rice, Grains | Medium |
| 22 | Spider Mite | মাকড়সা মাইট | Vegetables, Cotton | Medium |

Each entry includes: `symptoms`, `symptoms_bn`, `causes`, `prevention`, `treatment` (chemical), `organic_treatment`, `severity_level`.

---

## Database Tables

### `disease_reports`

Records every AI detection linked to a farmer's crop:

| Column | Type | Description |
|--------|------|-------------|
| `detection_id` | int PK | Auto-increment |
| `user_id` | int | Farmer who uploaded |
| `crop_id` | int | FK → `crop_data.crop_id` (nullable) |
| `disease_name` | varchar(100) | Detected disease name |
| `disease_type` | varchar(100) | Category (fungal/bacterial/viral/pest) |
| `severity` | enum | `low` \| `medium` \| `high` |
| `confidence_score` | decimal(5,2) | AI confidence percentage |
| `image_url` | varchar(255) | Saved detection image path |
| `symptoms` | text | Observed symptoms noted by farmer |
| `detected_date` | timestamp | Detection timestamp |
| `treatment_recommended` | text | Recommended treatment (from AI) |
| `treatment_applied` | text | Treatment farmer actually applied |
| `treatment_cost` | decimal(10,2) | Cost in Taka (farmer-reported) |
| `status` | enum | `detected` → `treating` → `cured` / `failed` |
| `verified_by` | int | Officer user_id who reviewed (nullable) |
| `verified_at` | timestamp | Officer review timestamp (nullable) |

### `disease_report_responses`

Officer responses to farmer disease reports:

| Column | Type | Description |
|--------|------|-------------|
| `response_id` | int PK | Auto-increment |
| `report_id` | int | FK → `disease_reports.detection_id` |
| `officer_id` | int | FK → `users.user_id` |
| `message` | text | Officer's response message |
| `recommended_action` | text | Specific action recommended |
| `status` | enum | `pending` \| `reviewed` \| `resolved` |

### `disease_library`

Knowledge base for the advisory system (22 seeded entries):

| Column | Type | Description |
|--------|------|-------------|
| `disease_id` | int PK | Auto-increment |
| `disease_name` | varchar(100) | English name |
| `disease_name_bn` | varchar(100) | Bengali name |
| `common_name` | varchar(100) | Common name |
| `scientific_name` | varchar(255) | Scientific/pathogen name |
| `affected_crops` | text | Comma-separated crops |
| `symptoms` | text | Visual symptom description |
| `causes` | text | Causative agent / conditions |
| `prevention` | text | Preventive measures |
| `treatment` | text | Chemical treatment |
| `organic_treatment` | text | Organic/biological treatment |
| `severity_level` | enum | `low` \| `medium` \| `high` |

---

## API Reference

### `POST api/disease/analyze.php`

**Auth required:** Yes (PHP session)  
**Content-Type:** `multipart/form-data`

**Request:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `image` | file | Yes | JPEG or PNG, max 5 MB |
| `cropId` | int | No | `crop_data.crop_id` — links result to a crop record |

**Success response:**
```json
{
  "success": true,
  "data": {
    "disease": "Rice Blast Disease",
    "severity": "high",
    "confidence": 0.87,
    "treatment": "IMMEDIATE TREATMENT REQUIRED:\n\nCHEMICAL CONTROL:\n• Apply Tricyclazole 75% WP @ 0.6g/L...",
    "causes": "Caused by fungus Magnaporthe oryzae. Thrives in humid conditions with high nitrogen.",
    "api_used": "TensorFlow + YOLO3 Deep Learning"
  }
}
```

**`api_used` values:**

| Value | Source |
|-------|--------|
| `TensorFlow + YOLO3 Deep Learning` | Python ML service (Tier 1) |
| `Google Gemini AI` | Gemini Vision API (Tier 2) |
| `Smart Image Analysis` | PHP GD color analysis (Tier 3) |
| `Advanced Mock Detection (...)` | Fallback database (Tier 4) |

**Error responses:**

```json
{ "success": false, "message": "Unauthorized" }
{ "success": false, "message": "No image uploaded" }
{ "success": false, "message": "Invalid file type. Only JPG and PNG allowed" }
{ "success": false, "message": "File too large. Maximum 5MB allowed" }
```

**Severity values:** `low` | `medium` | `high`

---

## File Structure

```
smartchashi/
│
├── pages/disease.php              ← User-facing disease detection page
│   (UI: crop selector, upload zone, result card, camera integration)
│
├── api/disease/
│   ├── analyze.php               ← Main detection API handler (4-tier pipeline)
│   ├── test_functions.php        ← Developer: unit tests for detection functions
│   └── test_upload.php           ← Developer: manual upload test endpoint
│
├── Disease detection/             ← Standalone Python ML service
│   ├── run.bat                   ← ★ One-click launcher (auto-installs, starts both services)
│   ├── app.py                    ← Unified Flask web app  (port 8080, EfficientNet-B0)
│   ├── plant_detection_api.py    ← ML REST API           (port 5000, ViT model)
│   ├── requirements.txt          ← All Python dependencies
│   ├── backend/
│   │   ├── production_server.py  ← FastAPI app (port 5000, uvicorn — alternative backend)
│   │   ├── crop_classifier.py    ← Stage 2: plant/non-plant classifier
│   │   ├── disease_classifier.py ← Stage 3: EfficientNet-B0 38-class model
│   │   ├── multi_model_classifier.py ← Ensemble mode (disabled)
│   │   └── models/
│   │       ├── disease_model_pytorch.pth      ← Primary PyTorch model weights
│   │       ├── disease_model_merged_best.pth  ← Alternative merged model
│   │       └── class_names.json               ← 38 class labels
│   ├── simple-plant-detection/   ← ViT model files (used by plant_detection_api.py)
│   │   ├── model.safetensors     ← ViT weights (~327 MB)
│   │   ├── config.json           ← Model config
│   │   └── preprocessor_config.json ← Image processor config
│   ├── templates/disease.html    ← Standalone HTML UI (Flask/Jinja2 for app.py)
│   ├── static/uploads/disease_images/ ← Detection history images
│   └── venv/                     ← Python virtual environment (auto-created by run.bat)
│
└── uploads/disease_reports/      ← Uploaded images saved by disease_reports
```

---

## Setup & Running

### Option A — One-Click Launch (Recommended)

Double-click **`run.bat`** inside `Disease detection\`. It handles everything automatically:

```
Disease detection\
└── run.bat   ← double-click this
```

**What `run.bat` does (5 steps):**

| Step | Action |
|------|--------|
| 1 | Checks Python 3.10+ is installed and in PATH |
| 2 | Creates `venv\` virtual environment if it doesn't exist |
| 3 | Installs all packages from `requirements.txt` + `transformers` if missing |
| 4 | Starts **ML Backend** (`plant_detection_api.py`) on port 5000 in a background window; polls until ready |
| 5 | Starts **Web App** (`app.py`) on port 8080 and opens browser |

**First run** takes 3–10 minutes for package installation and 30–60 seconds for model loading.  
**Subsequent runs** skip installation and start in ~30 seconds.

**Services started:**

| Service | File | Port | Model |
|---------|------|------|-------|
| ML Backend REST API | `plant_detection_api.py` | `5000` | ViT (`simple-plant-detection/`) — 30 plant types |
| Web App (UI + EfficientNet) | `app.py` | `8080` | EfficientNet-B0 — 38 disease classes |

---

### Option B — PHP Integration Only (No Python)

The main Smart Chashi platform (`http://localhost/smartchashi/disease`) uses a 4-tier fallback chain. Tiers 2–4 work out of the box with XAMPP. Only add a Gemini key to enable Tier 2:

```php
// config/config.php
define('GEMINI_API_KEY', 'AIza...');   // aistudio.google.com — free tier
```

No Python required. The system automatically falls through to Tier 3 (PHP GD color analysis) and Tier 4 (mock database) if neither Python nor Gemini are available.

---

### Option C — Manual Start (Advanced)

#### Prerequisites

| Requirement | Version | Notes |
|-------------|---------|-------|
| Python | 3.10+ | Add to PATH during install |
| RAM | 4 GB min / 8 GB recommended | ViT model uses ~1.5 GB |
| GPU/CUDA | Optional | CPU works; CUDA 11.8+ for acceleration |

#### Step 1 — Create and activate venv

```bat
cd "Disease detection"
python -m venv venv
venv\Scripts\activate
```

#### Step 2 — Install packages

```bat
pip install -r requirements.txt
```

This installs: Flask, PyTorch, torchvision, Transformers (ViT), Pillow, NumPy, FastAPI, uvicorn.

#### Step 3 — Verify model files

```
Disease detection/
├── simple-plant-detection/
│   └── model.safetensors     ← ViT model (~327 MB) — used by plant_detection_api.py
└── backend/models/
    ├── disease_model_pytorch.pth   ← EfficientNet-B0 — used by app.py
    └── class_names.json            ← 38 class labels
```

#### Step 4 — Start ML Backend (port 5000)

```bat
venv\Scripts\python.exe plant_detection_api.py
```

Startup takes ~30–60 seconds (ViT model loads into memory on first request).

Verify:
```bash
curl http://localhost:5000/health
# → {"status":"healthy","service":"Simple Plant Detection API",...}

curl http://localhost:5000/info
# → lists 30 supported plant types
```

#### Step 5 — Start Web App (port 8080)

Open a second terminal:

```bat
cd "Disease detection"
venv\Scripts\activate
python app.py
```

Visit: `http://localhost:8080`

---

### Troubleshooting

| Problem | Likely Cause | Fix |
|---------|-------------|-----|
| `ModuleNotFoundError: No module named 'transformers'` | Package not installed | `pip install transformers` |
| `ModuleNotFoundError: No module named 'flask'` | venv not activated | Run `run.bat` or activate venv first |
| ML Backend window closes immediately | Python error on startup | Open a terminal and run `python plant_detection_api.py` directly to see the error |
| Port 5000 already in use | Previous instance still running | `taskkill /IM python.exe /F` then retry |
| Browser opens but page is blank | `app.py` still loading models | Wait 30s and refresh |
| `torch` import error on Python 3.13 | Torch version mismatch | `pip install torch --upgrade` |
| ViT model not found | `simple-plant-detection/` folder missing | Re-download model or check folder exists |

---

## Flowchart

```
                        ┌──────────────────────────┐
                        │    FARMER OPENS           │
                        │   /disease PAGE           │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  Select crop from         │
                        │  dropdown (optional)      │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  Upload photo             │
                        │  (drag/drop or camera)    │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  Client-side validation   │
                        │  • JPEG or PNG?           │
                        │  • ≤ 5 MB?                │
                        └──────────┬───────────────┘
                                   │
                               FAIL? ──► show error, re-upload
                                   │ PASS
                        ┌──────────▼───────────────┐
                        │  POST to analyze.php      │
                        │  (multipart/form-data)    │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  Server validates again   │
                        │  • Session auth check     │
                        │  • MIME type check        │
                        │  • File size check        │
                        └──────────┬───────────────┘
                                   │
                               FAIL? ──► 400 JSON error
                                   │ PASS
                        ┌──────────▼───────────────┐
                        │  TIER 1: Python ML        │
                        │  EfficientNet-B0          │
                        │  38-class PlantVillage    │
                        └──────────┬───────────────┘
                                   │
                        ML returns?──► YES ─────────────────────────┐
                                   │ NO (Python unavailable)        │
                        ┌──────────▼───────────────┐               │
                        │  TIER 2: Gemini Vision    │               │
                        │  gemini-1.5-flash         │               │
                        │  Expert pathologist prompt│               │
                        └──────────┬───────────────┘               │
                                   │                               │
                       Gemini returns?──► YES ──────────────────────┤
                                   │ NO (no key or quota)          │
                        ┌──────────▼───────────────┐               │
                        │  TIER 3: PHP GD           │               │
                        │  RGB color analysis       │               │
                        │  (no external API)        │               │
                        └──────────┬───────────────┘               │
                                   │                               │
                        GD works?──► YES ─────────────────────────  ┤
                                   │ NO (GD missing)               │
                        ┌──────────▼───────────────┐               │
                        │  TIER 4: Mock DB          │               │
                        │  crop-specific diseases   │               │
                        │  (always returns result)  │               │
                        └──────────┬───────────────┘               │
                                   │                               │
                                   └───────────────────────────────┘
                                                   │ result
                        ┌──────────▼───────────────┐
                        │  crop_id given?           │
                        │  YES → INSERT disease_reports │
                        │  NO  → skip DB insert     │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  Return JSON to browser   │
                        │  {disease, severity,      │
                        │   confidence, treatment,  │
                        │   causes, api_used}       │
                        └──────────┬───────────────┘
                                   │
                        ┌──────────▼───────────────┐
                        │  UI renders result card   │
                        │  • Disease name + badge   │
                        │  • Confidence meter       │
                        │  • Severity indicator     │
                        │  • Treatment steps        │
                        │  • Causes section         │
                        └──────────────────────────┘
```

---

## What Is Accepted / Rejected

### Image Input

| Input | Accepted | Reason |
|-------|----------|--------|
| JPEG (.jpg, .jpeg) | Yes | Standard photo format |
| PNG (.png) | Yes | Standard format |
| WebP (.webp) | Yes (ML service only) | Not accepted by PHP tier |
| BMP (.bmp) | Yes (ML service only) | Not accepted by PHP tier |
| GIF, PDF, video | No | MIME type check rejects |
| File size ≤ 5 MB | Yes (PHP) | Server limit |
| File size 5–10 MB | Yes (ML service only) | ML service allows larger files |
| File size > 10 MB | No | Rejected by ML service |
| Image dimensions < 50×50 px | No | ML service minimum |
| Image dimensions > 4096×4096 px | Auto-resized | ML service resizes with LANCZOS |
| Corrupted file (< 1 KB) | No | ML service min size check |
| Non-plant / landscape photo | Uncertain | ML service flags as not a crop (<55% confidence) |

### Detection Outcomes

| Scenario | Result |
|----------|--------|
| Clear leaf image with visible disease | High-confidence disease detection |
| Healthy plant | "Healthy Plant" returned (not forced into a disease) |
| Blurry or low-quality image | Lower confidence score; may fall to Tier 3/4 |
| Non-plant image (food, person, object) | ML service rejects; PHP tiers may still return a result |
| Unknown disease not in 38 classes | "Uncertain" if confidence < 60% from ML |
| Python service down | Automatic fallback to Tier 2 → 3 → 4 |
| No Gemini API key | Tier 2 skipped; falls to Tier 3 |

### Treatment Response

| Scenario | Included |
|----------|---------|
| Chemical treatment | Always (specific fungicide/pesticide names + dosages) |
| Organic alternatives | Always |
| Prevention tips | Always |
| Bengali translation | Only from ML service (Tier 1) |
| Severity warning banner | Yes — HIGH: ⚠️, MEDIUM: ⚡ prefix added |
| Officer escalation note | Included in Tier 3/4 for severe cases |

---

*Courtesy: **mohatamim** — [facebook.com/mohatamim44](https://www.facebook.com/mohatamim44)*
