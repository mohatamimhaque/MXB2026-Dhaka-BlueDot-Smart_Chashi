# Disease Detection API Usage Guide

## API Endpoint
```
POST http://192.168.1.103:8080/api/analyze
```

## CORS Support
✅ CORS is already enabled - you can call this API from any origin

## Request Format

### Headers
- No special headers required
- `Content-Type: multipart/form-data` (automatically set by browser)

### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `image` | File | Yes | Image file (JPEG, PNG, WebP) - Max 10MB |
| `crop` | String | Optional | Crop type filter (e.g., "Rice", "Potato", "Tomato") |

## Example Usage

### JavaScript Fetch API
```javascript
// Create form data
const formData = new FormData();
formData.append('image', fileInputElement.files[0]);
formData.append('crop', 'Tomato'); // Optional

// Make API call
fetch('http://192.168.1.103:8080/api/analyze', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Analysis successful:', data.data);
        // Access disease information
        console.log('Disease:', data.data.disease_name);
        console.log('Confidence:', data.data.confidence);
        console.log('Treatment:', data.data.treatment);
    } else {
        console.error('Analysis failed:', data.message);
    }
})
.catch(error => {
    console.error('API Error:', error);
});
```

### Using Axios
```javascript
import axios from 'axios';

const formData = new FormData();
formData.append('image', file);
formData.append('crop', 'Rice');

axios.post('http://192.168.1.103:8080/api/analyze', formData)
    .then(response => {
        const data = response.data;
        if (data.success) {
            console.log('Disease:', data.data.disease_name);
        }
    })
    .catch(error => {
        console.error('Error:', error.response?.data || error.message);
    });
```

### React Example
```javascript
function DiseaseDetector() {
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);

    const analyzeImage = async (file, crop = '') => {
        setLoading(true);
        
        const formData = new FormData();
        formData.append('image', file);
        if (crop) formData.append('crop', crop);

        try {
            const response = await fetch('http://192.168.1.103:8080/api/analyze', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                setResult(data.data);
            } else {
                console.error('Error:', data.message);
            }
        } catch (error) {
            console.error('API Error:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div>
            <input 
                type="file" 
                onChange={(e) => analyzeImage(e.target.files[0], 'Tomato')} 
            />
            {loading && <p>Analyzing...</p>}
            {result && (
                <div>
                    <h3>{result.disease_name}</h3>
                    <p>Confidence: {result.confidence}%</p>
                    <p>Treatment: {result.treatment}</p>
                </div>
            )}
        </div>
    );
}
```

## Response Format

### Success Response
```json
{
    "success": true,
    "status": "success",
    "message": "Disease detected",
    "data": {
        "crop": "Tomato",
        "disease_name": "Tomato Leaf Blight",
        "disease_name_bn": "টমেটো পাতা ঝলসানো রোগ",
        "confidence": 85.6,
        "severity": "high",
        "is_healthy": false,
        "is_uncertain": false,
        "image_url": "/static/uploads/disease_images/disease_abc123.jpg",
        "symptoms": "Brown spots on leaves, yellowing around edges",
        "symptoms_bn": "পাতায় বাদামী দাগ, প্রান্তে হলুদ হয়ে যাওয়া",
        "treatment": "Apply copper-based fungicide...",
        "treatment_bn": "তামা-ভিত্তিক ছত্রাকনাশক প্রয়োগ করুন...",
        "organic_treatment": "Neem oil spray, remove affected leaves",
        "organic_treatment_bn": "নিম তেল স্প্রে করুন, আক্রান্ত পাতা সরান",
        "prevention": "Maintain proper spacing, avoid overhead watering",
        "prevention_bn": "সঠিক দূরত্ব বজায় রাখুন, মাথার উপর জল দেওয়া এড়িয়ে চলুন",
        "detected_at": "2026-01-11 09:45:23",
        "processing_time_ms": 234.5
    }
}
```

### Error Response - Not a Crop
```json
{
    "success": false,
    "error_code": "NOT_CROP",
    "message": "This image does not appear to be a crop or plant",
    "message_bn": "এই ছবিটি ফসল বা উদ্ভিদের মতো দেখাচ্ছে না",
    "image_url": "/static/uploads/disease_images/disease_xyz789.jpg"
}
```

### Error Response - Invalid File
```json
{
    "success": false,
    "status": "error",
    "message": "Invalid file type. Allowed: JPEG, PNG, WebP",
    "error_code": "INVALID_FILE_TYPE"
}
```

### Error Response - No File Uploaded
```json
{
    "success": false,
    "status": "error",
    "message": "No file was uploaded",
    "error_code": "UPLOAD_ERROR"
}
```

## Response Fields Explained

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Whether the request was successful |
| `status` | string | Status: "success" or "error" |
| `message` | string | Human-readable message |
| `data.crop` | string | Detected or provided crop type |
| `data.disease_name` | string | Disease name in English |
| `data.disease_name_bn` | string | Disease name in Bengali |
| `data.confidence` | number | Detection confidence (0-100) |
| `data.severity` | string | Severity level: "high", "medium", or "low" |
| `data.is_healthy` | boolean | Whether the plant is healthy |
| `data.is_uncertain` | boolean | Whether detection is uncertain |
| `data.symptoms` | string | Disease symptoms description |
| `data.treatment` | string | Chemical treatment recommendation |
| `data.organic_treatment` | string | Organic treatment options |
| `data.prevention` | string | Prevention measures |
| `data.detected_at` | string | Timestamp of detection |
| `data.processing_time_ms` | number | Processing time in milliseconds |

## Supported Crops
- Rice (ধান)
- Potato (আলু)
- Tomato (টমেটো)
- Mango (আম)
- Sugarcane (আখ)
- Cotton (তুলা)
- Apple (আপেল)
- Corn (ভুট্টা)
- Grape (আঙ্গুর)

## Error Codes
- `UPLOAD_ERROR` - No file uploaded or file not selected
- `INVALID_FILE_TYPE` - Unsupported file format
- `INVALID_IMAGE` - Corrupted or invalid image
- `NOT_CROP` - Image doesn't contain a crop/plant
- `DETECTION_ERROR` - Internal processing error

## Testing with cURL
```bash
curl -X POST http://192.168.1.103:8080/api/analyze \
  -F "image=@/path/to/image.jpg" \
  -F "crop=Tomato"
```

## Notes
- Maximum file size: 10MB
- Supported formats: JPEG, PNG, WebP, BMP
- Minimum image size: 50x50 pixels
- Maximum image size: 4096x4096 pixels (auto-resized if larger)
- Images are automatically converted to RGB format
- Bilingual support: English and Bengali

## Health Check Endpoint
```
GET http://192.168.1.103:8080/api/health
```

Returns server status and model information.

## API Info Endpoint
```
GET http://192.168.1.103:8080/api/info
```

Returns detailed API information including supported crops and thresholds.
