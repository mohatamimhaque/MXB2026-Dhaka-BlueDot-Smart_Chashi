---
title: Smart Chashi - AI Disease Detection
emoji: 🌿
colorFrom: green
colorTo: yellow
sdk: docker
app_file: app.py
pinned: false
---

Check out the configuration reference at https://huggingface.co/docs/hub/spaces-config-reference

# Smart Chashi - AI Disease Detection

A Python Flask web application for detecting crop diseases using AI.

## Project Structure

```
smartcashi/
├── app.py                 # Main Flask application
├── requirements.txt       # Python dependencies
├── run.bat               # Windows startup script
├── venv/                 # Python virtual environment
├── templates/
│   └── disease.html      # Main page template (Jinja2)
├── static/
│   ├── css/
│   │   └── style.css     # Styles
│   └── uploads/
│       └── disease_images/  # Uploaded images
├── backend/              # ML Backend (FastAPI)
│   ├── production_server.py
│   ├── disease_classifier.py
│   ├── multi_model_classifier.py
│   └── models/           # PyTorch models
└── DISEASE_DETECTION_SETUP.md
```

## Setup

### 1. Install Dependencies

```bash
# Activate virtual environment
.\venv\Scripts\activate  # Windows
source venv/bin/activate # Linux/Mac

# Install Flask dependencies
pip install -r requirements.txt
```

### 2. Start ML Backend (Port 5000)

```bash
cd backend
pip install -r requirements.txt
python production_server.py
```

### 3. Start Flask Web Server (Port 8080)

```bash
# Option 1: Use the batch file (Windows)
run.bat

# Option 2: Manual
.\venv\Scripts\activate
python app.py
```

### 4. Open in Browser

Navigate to: http://localhost:8080

## Features

- 🌾 Crop disease detection using AI
- 🖼️ Drag & drop image upload
- 🌐 Bilingual support (English/বাংলা)
- 📊 Confidence scoring
- 💊 Treatment recommendations
- 🌱 Organic treatment options
- 🛡️ Prevention tips

## API Endpoints

- `GET /` - Main disease detection page
- `GET /set-language/<lang>` - Set language (en/bn)
- `POST /api/analyze` - Analyze disease image
  - Body: `multipart/form-data`
  - Fields: `image` (file), `crop` (string)

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript, Jinja2 Templates
- **Backend**: Python Flask
- **ML**: PyTorch, FastAPI
- **Models**: Custom trained disease detection models
