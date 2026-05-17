# Crop Disease Detection Module - Complete Setup Guide

## Quick Start

### 1. Install Python Dependencies

```bash
cd backend
pip install -r requirements.txt
```

### 2. Start the Python API

```bash
cd backend
python main.py
```

The API will start on `http://localhost:5000`

### 3. Test the Python API

In a new terminal:

```bash
cd backend
python test_api.py
```

### 4. Access the Web Interface

The PHP server should already be running. Navigate to:
```
http://localhost:8000/disease
```

## What Was Built

### ✅ Python Backend
- **FastAPI server** with disease detection endpoint
- **Model loader** supporting both real and placeholder models
- **Image preprocessing** (resize to 224x224, normalization)
- **29 disease classes** from PlantVillage dataset
- **CORS support** for PHP integration

### ✅ PHP Integration
- **Enhanced API handler** (`api/disease/analyze.php`)
- **cURL integration** to Python API
- **Image upload** and validation
- **Database storage** of detections
- **Treatment lookup** from disease library

### ✅ Frontend
- **Drag-and-drop** image upload
- **Image preview** before analysis
- **Loading overlay** during analysis
- **Result card** with comprehensive information
- **Recent detections** history
- **Bengali language** support

### ✅ Database
- **Disease library** populated with 15+ diseases
- **Bengali translations** for disease names
- **Treatment recommendations** (organic + chemical)
- **Prevention tips** and symptoms

## Testing the Complete Flow

### Test 1: Python API Health Check

```bash
curl http://localhost:5000/
```

Expected response:
```json
{
  "status": "online",
  "service": "Smart Chashi Disease Detection API",
  "version": "1.0.0",
  "model_loaded": true
}
```

### Test 2: Upload Image via Browser

1. Navigate to `http://localhost:8000/disease`
2. Login if not already logged in
3. Drag and drop an image or click to browse
4. Click "Analyze Image"
5. Wait for results

Expected: Disease name, confidence, severity, and treatment recommendations

### Test 3: Check Database

```sql
-- Check recent detections
SELECT * FROM disease_reports ORDER BY detected_date DESC LIMIT 5;

-- Check disease library
SELECT disease_name, disease_name_bn FROM disease_library;
```

## Troubleshooting

### Python API Not Starting

**Error:** `ModuleNotFoundError: No module named 'fastapi'`

**Solution:**
```bash
cd backend
pip install -r requirements.txt
```

### Connection Refused Error

**Error:** `cURL Error: Failed to connect to localhost port 5000`

**Solution:** Make sure the Python API is running:
```bash
cd backend
python main.py
```

### Image Upload Fails

**Error:** `Failed to save uploaded file`

**Solution:** Check directory permissions:
```bash
# Windows
icacls public\uploads\disease_images /grant Everyone:F

# Linux/Mac
chmod 755 public/uploads/disease_images
```

### No Disease Information

**Error:** Disease detected but no treatment shown

**Solution:** Populate the disease library:
```bash
Get-Content backend\populate_diseases.sql | C:\xampp\mysql\bin\mysql.exe -u root smartcashi_db
```

## Using a Real Model

To use a trained TensorFlow model instead of the placeholder:

1. **Download or train a model** on the PlantVillage dataset
2. **Save the model** as `backend/models/disease_model.h5`
3. **Restart the Python API**

The system will automatically detect and load the real model.

### Recommended Models

- **MobileNetV2** - Fast, mobile-friendly
- **ResNet50** - Higher accuracy
- **EfficientNet** - Best balance

## API Endpoints

### Python API (localhost:5000)

#### GET /
Health check

#### POST /predict
Predict disease from image

**Request:**
- Content-Type: `multipart/form-data`
- Body: `file` (image file)

**Response:**
```json
{
  "success": true,
  "prediction": {
    "disease_name": "Late blight",
    "crop": "Tomato",
    "confidence": 95.67,
    "severity": "high"
  }
}
```

#### GET /classes
Get list of supported disease classes

### PHP API (localhost:8000/api/)

#### POST /api/handler.php?action=analyze-disease
Analyze disease image

**Request:**
- Content-Type: `multipart/form-data`
- Body: `image` (file), `crop_id` (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "disease_name": "Tomato Late Blight",
    "disease_name_bn": "টমেটোর দেরী ধ্বংস",
    "confidence": 95.67,
    "severity": "high",
    "symptoms": "...",
    "treatment": "...",
    "prevention": "..."
  }
}
```

## Language Support

The system supports both English and Bengali:

- **Disease names**: English + Bengali (বাংলা)
- **UI labels**: Fully translated
- **Treatment info**: Available in both languages

To switch language, use the language selector in the navigation bar.

## Performance Tips

### For Better Accuracy

1. **Use clear, well-lit photos**
2. **Focus on affected area**
3. **Avoid blurry images**
4. **Take photos in daylight**

### For Faster Processing

1. **Resize images** before upload (max 1920x1080)
2. **Use JPEG** instead of PNG when possible
3. **Compress images** to reduce file size

## Next Steps

1. **Train a real model** on PlantVillage dataset
2. **Add more diseases** to the library
3. **Implement caching** for faster repeated predictions
4. **Add batch processing** for multiple images
5. **Create mobile app** using the same API

## Support

For issues or questions:
- Check the logs in `backend/` directory
- Review PHP error logs
- Test API endpoints individually
- Verify database connections

---

**Built with ❤️ for Smart Chashi**
