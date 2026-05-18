"""
Smart Chashi - AI Disease Detection
=====================================
Unified Web Application (Frontend + ML Backend)
Single Port: 8080

Combines:
- Flask Web Frontend (HTML templates, static files)
- Disease Detection ML Pipeline (PyTorch models)
"""

import os
import sys
import io
import uuid
import logging
from datetime import datetime
from typing import Dict, Any, Optional

import numpy as np
from PIL import Image
from flask import Flask, render_template, request, jsonify, session, redirect, url_for, make_response
from flask_cors import CORS
from werkzeug.utils import secure_filename

# Add backend to path for imports
BACKEND_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'backend')
sys.path.insert(0, BACKEND_DIR)

# Import ML classifiers from backend
from crop_classifier import CropClassifier
from disease_classifier import DiseaseClassifier

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# ========================================
# Flask Application
# ========================================

app = Flask(__name__)
app.secret_key = os.urandom(24)
CORS(app)

# Configuration
APP_NAME = "Smart Chashi - AI Disease Detection"
APP_VERSION = "2.0.0"
DEFAULT_LANGUAGE = "en"
UPLOAD_FOLDER = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'static', 'uploads', 'disease_images')
ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'webp', 'bmp'}
MAX_CONTENT_LENGTH = 10 * 1024 * 1024  # 10MB

# ML Configuration
CROP_CONFIDENCE_THRESHOLD = 55.0
DISEASE_CONFIDENCE_THRESHOLD = 60.0
MIN_IMAGE_SIZE = 50
MAX_IMAGE_SIZE = 4096

# Ensure upload folder exists
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER
app.config['MAX_CONTENT_LENGTH'] = MAX_CONTENT_LENGTH

# ========================================
# Global ML Classifiers (Lazy Loading)
# ========================================

_crop_classifier: Optional[CropClassifier] = None
_disease_classifier: Optional[DiseaseClassifier] = None


def get_crop_classifier() -> CropClassifier:
    """Lazy load crop classifier"""
    global _crop_classifier
    if _crop_classifier is None:
        logger.info("Initializing Crop Classifier...")
        _crop_classifier = CropClassifier()
    return _crop_classifier


def get_disease_classifier() -> DiseaseClassifier:
    """Lazy load disease classifier"""
    global _disease_classifier
    if _disease_classifier is None:
        logger.info("Initializing Disease Classifier...")
        _disease_classifier = DiseaseClassifier()
    return _disease_classifier


# ========================================
# Translations
# ========================================

TRANSLATIONS = {
    'en': {
        'disease_detection': 'Disease Detection',
        'detect_crop_diseases': 'Detect crop diseases using AI',
        'upload_crop_image': 'Upload Crop Image',
        'drag_drop_image': 'Drag & drop your image here',
        'or_click_to_browse': 'or click to browse',
        'jpg_png_max': 'JPG, PNG, WebP - Max 10MB',
        'analyze_image': 'Analyze Image',
        'cancel': 'Cancel',
        'analyzing_image': 'Analyzing Image...',
        'please_wait': 'Please wait while AI analyzes your crop',
        'select_crop': 'Select Your Crop',
        'select_crop_desc': 'Select your crop first for accurate disease detection',
        'crop_type': 'Crop Type',
        'select_a_crop': '-- Select a Crop --',
        'bangladesh_crops': 'Bangladesh Crops',
        'common_crops': 'Common Crops',
        'other_type': '🔍 Other (Type name)',
        'enter_crop_name': 'Enter Crop Name',
        'tips_title': 'Tips for Better Results',
        'tip_1': '🌞 Take photo in <strong>good lighting</strong>',
        'tip_2': '🍃 <strong>Focus on the affected leaf</strong> closely',
        'tip_3': '📷 Ensure <strong>clear and sharp</strong> image',
        'tip_4': '🎯 <strong>Highlight disease symptoms</strong>',
        'crop': 'Crop',
        'symptoms': 'Symptoms',
        'treatment': 'Treatment',
        'organic_treatment': 'Organic Treatment',
        'prevention': 'Prevention',
        'healthy_plant': 'Your Plant is Healthy!',
        'not_crop_title': 'Not a Crop Image',
        'not_crop_desc': 'Please upload an image of a crop or plant leaf.',
    },
    'bn': {
        'disease_detection': 'রোগ সনাক্তকরণ',
        'detect_crop_diseases': 'AI দিয়ে ফসলের রোগ সনাক্ত করুন',
        'upload_crop_image': 'ফসলের ছবি আপলোড করুন',
        'drag_drop_image': 'এখানে ছবি টেনে আনুন',
        'or_click_to_browse': 'অথবা ব্রাউজ করতে ক্লিক করুন',
        'jpg_png_max': 'JPG, PNG, WebP - সর্বোচ্চ ১০MB',
        'analyze_image': 'ছবি বিশ্লেষণ করুন',
        'cancel': 'বাতিল',
        'analyzing_image': 'ছবি বিশ্লেষণ হচ্ছে...',
        'please_wait': 'অনুগ্রহ করে অপেক্ষা করুন',
        'select_crop': 'আপনার ফসল নির্বাচন করুন',
        'select_crop_desc': 'সঠিক রোগ নির্ণয়ের জন্য প্রথমে আপনার ফসল নির্বাচন করুন',
        'crop_type': 'ফসলের ধরন',
        'select_a_crop': '-- ফসল নির্বাচন করুন --',
        'bangladesh_crops': 'বাংলাদেশী ফসল',
        'common_crops': 'সাধারণ ফসল',
        'other_type': '🔍 অন্যান্য (টাইপ করুন)',
        'enter_crop_name': 'ফসলের নাম লিখুন',
        'tips_title': 'ভালো ফলাফলের জন্য টিপস',
        'tip_1': '🌞 <strong>ভালো আলোতে</strong> ছবি তুলুন',
        'tip_2': '🍃 <strong>আক্রান্ত পাতা</strong> কাছ থেকে তুলুন',
        'tip_3': '📷 <strong>পরিষ্কার ও স্পষ্ট</strong> ছবি তুলুন',
        'tip_4': '🎯 <strong>রোগের লক্ষণ</strong> ফোকাস করুন',
        'crop': 'ফসল',
        'symptoms': 'লক্ষণসমূহ',
        'treatment': 'চিকিৎসা',
        'organic_treatment': 'জৈব চিকিৎসা',
        'prevention': 'প্রতিরোধ',
        'healthy_plant': 'আপনার গাছ সুস্থ!',
        'not_crop_title': 'এটি ফসলের ছবি নয়',
        'not_crop_desc': 'অনুগ্রহ করে ফসলের পাতার ছবি আপলোড করুন।',
    }
}

# Crop options
CROPS = {
    'bangladesh': [
        ('Rice', 'ধান'),
        ('Mango', 'আম'),
        ('Sugarcane', 'আখ'),
        ('Cotton', 'তুলা'),
        ('Jackfruit', 'কাঁঠাল'),
        ('Cauliflower', 'ফুলকপি'),
        ('Pumpkin', 'কুমড়া'),
    ],
    'common': [
        ('Apple', 'আপেল'),
        ('Grape', 'আঙ্গুর'),
        ('Tomato', 'টমেটো'),
        ('Potato', 'আলু'),
        ('Corn_(maize)', 'ভুট্টা'),
        ('Pepper,_bell', 'মরিচ'),
        ('Strawberry', 'স্ট্রবেরি'),
        ('Cherry_(including_sour)', 'চেরি'),
        ('Peach', 'পীচ'),
        ('Orange', 'কমলা'),
        ('Soybean', 'সয়াবিন'),
    ]
}


# ========================================
# Helper Functions
# ========================================

def get_language():
    """Get current language from cookie or session"""
    return request.cookies.get('language', session.get('language', DEFAULT_LANGUAGE))


def translate(key):
    """Get translation for a key"""
    lang = get_language()
    return TRANSLATIONS.get(lang, TRANSLATIONS['en']).get(key, TRANSLATIONS['en'].get(key, key))


def allowed_file(filename):
    """Check if file extension is allowed"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


def validate_image(file_storage) -> Dict[str, Any]:
    """Validate uploaded image"""
    try:
        # Read file content
        content = file_storage.read()
        file_storage.seek(0)
        
        # Check file size
        if len(content) > MAX_CONTENT_LENGTH:
            return {"valid": False, "error": f"File too large. Maximum size: {MAX_CONTENT_LENGTH // (1024*1024)}MB", "image": None}
        
        if len(content) < 1000:
            return {"valid": False, "error": "File too small or corrupted", "image": None}
        
        # Try to open image
        image = Image.open(io.BytesIO(content))
        
        # Convert to RGB
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        # Check dimensions
        width, height = image.size
        if width < MIN_IMAGE_SIZE or height < MIN_IMAGE_SIZE:
            return {"valid": False, "error": f"Image too small. Minimum: {MIN_IMAGE_SIZE}x{MIN_IMAGE_SIZE}", "image": None}
        
        if width > MAX_IMAGE_SIZE or height > MAX_IMAGE_SIZE:
            ratio = min(MAX_IMAGE_SIZE / width, MAX_IMAGE_SIZE / height)
            new_size = (int(width * ratio), int(height * ratio))
            image = image.resize(new_size, Image.LANCZOS)
            logger.info(f"Resized image from {width}x{height} to {new_size}")
        
        return {"valid": True, "error": None, "image": image}
        
    except Exception as e:
        logger.error(f"Image validation error: {e}")
        return {"valid": False, "error": f"Invalid image file: {str(e)}", "image": None}


def run_detection_pipeline(image: Image.Image, selected_crop: Optional[str] = None) -> Dict[str, Any]:
    """
    Complete detection pipeline:
    1. Crop vs Non-Crop detection
    2. Disease detection
    3. Solution recommendations
    """
    start_time = datetime.now()
    
    # Stage 1: Crop Detection
    logger.info("Stage 1: Crop detection...")
    crop_classifier = get_crop_classifier()
    crop_result = crop_classifier.classify(image)
    
    if not crop_result.get("is_crop", False):
        confidence = crop_result.get("confidence", 0)
        processing_time = (datetime.now() - start_time).total_seconds() * 1000
        return {
            "status": "error",
            "error_code": "NOT_CROP",
            "message": "This image does not appear to be a crop or plant",
            "message_bn": "এই ছবিটি ফসল বা উদ্ভিদের মতো দেখাচ্ছে না",
            "crop": None,
            "disease": None,
            "confidence": round(100 - confidence, 2) if confidence < 100 else 0,
            "solution": None,
            "details": {
                "crop_detection": crop_result,
                "processing_time_ms": round(processing_time, 2)
            }
        }
    
    logger.info(f"Crop detected with {crop_result.get('confidence', 0):.1f}% confidence")
    
    # Stage 2: Disease Detection
    logger.info(f"Stage 2: Disease detection (crop filter: {selected_crop})...")
    disease_classifier = get_disease_classifier()
    disease_result = disease_classifier.classify(image, crop_filter=selected_crop)
    
    if disease_result.get("status") == "error":
        return {
            "status": "error",
            "error_code": "CLASSIFICATION_ERROR",
            "message": disease_result.get("message", "Classification failed"),
            "crop": None,
            "disease": None,
            "confidence": 0,
            "solution": None
        }
    
    # Stage 3: Build Response
    crop = disease_result.get("crop", "Unknown")
    disease = disease_result.get("disease", "Uncertain")
    confidence = disease_result.get("confidence", 0)
    is_healthy = disease_result.get("is_healthy", False)
    
    if confidence < DISEASE_CONFIDENCE_THRESHOLD and not is_healthy:
        disease = "Uncertain"
        logger.info(f"Confidence {confidence:.1f}% below threshold {DISEASE_CONFIDENCE_THRESHOLD}%")
    
    solution = disease_result.get("solution", {})
    processing_time = (datetime.now() - start_time).total_seconds() * 1000
    
    response = {
        "status": "success",
        "crop": crop,
        "disease": disease,
        "disease_bn": disease_result.get("disease_bn", ""),
        "symptoms": disease_result.get("symptoms", "No specific symptoms available."),
        "symptoms_bn": disease_result.get("symptoms_bn", "নির্দিষ্ট লক্ষণ পাওয়া যায়নি।"),
        "confidence": round(confidence, 2),
        "is_healthy": is_healthy,
        "is_uncertain": disease == "Uncertain",
        "solution": {
            "chemical": solution.get("chemical", "Consult agricultural expert."),
            "chemical_bn": solution.get("chemical_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
            "organic": solution.get("organic", "Consult agricultural expert."),
            "organic_bn": solution.get("organic_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
            "prevention": solution.get("prevention", "Regular monitoring recommended."),
            "prevention_bn": solution.get("prevention_bn", "নিয়মিত পর্যবেক্ষণ করুন।")
        },
        "details": {
            "raw_class": disease_result.get("raw_class", ""),
            "top_predictions": disease_result.get("top_3_predictions", []),
            "crop_detection_confidence": crop_result.get("confidence", 0),
            "processing_time_ms": round(processing_time, 2)
        }
    }
    
    logger.info(f"Detection complete: {crop} - {disease} ({confidence:.1f}%) in {processing_time:.0f}ms")
    return response


# ========================================
# Template Context
# ========================================

@app.context_processor
def inject_globals():
    """Inject global variables into templates"""
    return {
        'app_name': APP_NAME,
        'app_version': APP_VERSION,
        'lang': get_language(),
        '_': translate,
        'crops': CROPS,
    }


# ========================================
# Web Routes
# ========================================

@app.route('/')
def index():
    """Main disease detection page"""
    return render_template('disease.html')


@app.route('/set-language/<lang>')
def set_language(lang):
    """Set language preference"""
    if lang in ['en', 'bn']:
        session['language'] = lang
        response = make_response(redirect(request.referrer or url_for('index')))
        response.set_cookie('language', lang, max_age=31536000)
        return response
    return redirect(url_for('index'))


# ========================================
# API Routes
# ========================================

@app.route('/api/health')
def api_health():
    """Health check endpoint"""
    return jsonify({
        "status": "healthy",
        "service": APP_NAME,
        "version": APP_VERSION,
        "timestamp": datetime.now().isoformat()
    })


@app.route('/api/info')
def api_info():
    """Service information"""
    return jsonify({
        "service": APP_NAME,
        "version": APP_VERSION,
        "pipeline": [
            "Image Validation",
            "Crop vs Non-Crop Classification",
            "Disease Classification",
            "Solution Recommendations"
        ],
        "supported_crops": ["Rice", "Potato", "Tomato", "Mango", "Sugarcane", "Cotton", "Apple", "Corn", "Grape"],
        "thresholds": {
            "crop_confidence": CROP_CONFIDENCE_THRESHOLD,
            "disease_confidence": DISEASE_CONFIDENCE_THRESHOLD
        }
    })


@app.route('/api/analyze', methods=['POST'])
@app.route('/predict', methods=['POST'])
def analyze_disease():
    """API endpoint for disease analysis - Works for both /api/analyze and /predict"""
    # Check if file was uploaded
    if 'image' not in request.files and 'file' not in request.files:
        return jsonify({
            'success': False,
            'status': 'error',
            'message': 'No file was uploaded',
            'error_code': 'UPLOAD_ERROR'
        }), 400
    
    file = request.files.get('image') or request.files.get('file')
    
    if file.filename == '':
        return jsonify({
            'success': False,
            'status': 'error',
            'message': 'No file selected',
            'error_code': 'UPLOAD_ERROR'
        }), 400
    
    if not allowed_file(file.filename):
        return jsonify({
            'success': False,
            'status': 'error',
            'message': 'Invalid file type. Allowed: JPEG, PNG, WebP',
            'error_code': 'INVALID_FILE_TYPE'
        }), 400
    
    # Validate image
    validation = validate_image(file)
    if not validation["valid"]:
        return jsonify({
            'success': False,
            'status': 'error',
            'message': validation["error"],
            'error_code': 'INVALID_IMAGE'
        }), 400
    
    image = validation["image"]
    
    # Save file
    file_ext = file.filename.rsplit('.', 1)[1].lower()
    unique_filename = f"disease_{uuid.uuid4().hex}.{file_ext}"
    filepath = os.path.join(app.config['UPLOAD_FOLDER'], unique_filename)
    
    try:
        image.save(filepath)
    except Exception as e:
        logger.error(f"Failed to save file: {e}")
    
    relative_url = f'/static/uploads/disease_images/{unique_filename}'
    
    # Get crop parameter
    selected_crop = request.form.get('crop', '').strip()
    
    try:
        # Run ML detection pipeline
        result = run_detection_pipeline(image, selected_crop if selected_crop else None)
        
        # Handle NOT_CROP
        if result.get("status") == "error" and result.get("error_code") == "NOT_CROP":
            return jsonify({
                'success': False,
                'error_code': 'NOT_CROP',
                'message': result.get('message', 'This image does not appear to be a crop or plant'),
                'message_bn': result.get('message_bn', 'এই ছবিটি ফসল বা উদ্ভিদের মতো দেখাচ্ছে না'),
                'image_url': relative_url
            }), 400
        
        # Handle other errors
        if result.get("status") == "error":
            return jsonify({
                'success': False,
                'status': 'error',
                'message': result.get('message', 'Detection failed'),
                'error_code': result.get('error_code', 'DETECTION_ERROR'),
                'image_url': relative_url
            }), 500
        
        # Success response
        response_data = {
            'crop': result.get('crop'),
            'disease_name': result.get('disease'),
            'disease_name_bn': result.get('disease_bn', ''),
            'confidence': result.get('confidence', 0),
            'severity': 'high' if result.get('confidence', 0) >= 80 else ('medium' if result.get('confidence', 0) >= 60 else 'low'),
            'is_healthy': result.get('is_healthy', False),
            'is_uncertain': result.get('is_uncertain', False),
            'image_url': relative_url,
            'symptoms': result.get('symptoms'),
            'symptoms_bn': result.get('symptoms_bn'),
            'treatment': result.get('solution', {}).get('chemical'),
            'treatment_bn': result.get('solution', {}).get('chemical_bn'),
            'organic_treatment': result.get('solution', {}).get('organic'),
            'organic_treatment_bn': result.get('solution', {}).get('organic_bn'),
            'prevention': result.get('solution', {}).get('prevention'),
            'prevention_bn': result.get('solution', {}).get('prevention_bn'),
            'detected_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'processing_time_ms': result.get('details', {}).get('processing_time_ms')
        }
        
        # Add warning for uncertain
        if result.get('is_uncertain'):
            response_data['warning'] = 'Detection confidence is low. Please upload a clearer image or consult an agricultural expert.'
            response_data['warning_bn'] = 'সনাক্তকরণের নির্ভুলতা কম। অনুগ্রহ করে আরও স্পষ্ট ছবি আপলোড করুন অথবা কৃষি বিশেষজ্ঞের পরামর্শ নিন।'
        elif result.get('confidence', 0) < 70:
            response_data['warning'] = 'Moderate confidence. Consider consulting an expert for confirmation.'
        
        message = 'Plant appears healthy' if result.get('is_healthy') else ('Unable to determine with certainty' if result.get('is_uncertain') else 'Disease detected')
        
        return jsonify({
            'success': True,
            'status': 'success',
            'message': message,
            'data': response_data
        })
        
    except Exception as e:
        logger.error(f"Detection error: {e}")
        import traceback
        logger.error(traceback.format_exc())
        
        return jsonify({
            'success': False,
            'status': 'error',
            'message': str(e),
            'error_code': 'DETECTION_ERROR',
            'image_url': relative_url
        }), 500


# ========================================
# Main Entry Point
# ========================================

if __name__ == '__main__':
    print("""
    ╔════════════════════════════════════════════════════════════╗
    ║          Smart Chashi - AI Disease Detection               ║
    ║              Unified Application v2.0.0                    ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Frontend + ML Backend on Single Port                      ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Pipeline:                                                 ║
    ║    1. Image Validation                                     ║
    ║    2. Crop vs Non-Crop Detection                           ║
    ║    3. Disease Classification (PyTorch EfficientNet-B0)     ║
    ║    4. Solution Recommendations                             ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Endpoints:                                                ║
    ║    GET  /            - Main Web Interface                  ║
    ║    POST /api/analyze - Disease Analysis API                ║
    ║    POST /predict     - Disease Analysis API (alias)        ║
    ║    GET  /api/health  - Health Check                        ║
    ║    GET  /api/info    - Service Information                 ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Running on: http://localhost:8080                         ║
    ╚════════════════════════════════════════════════════════════╝
    """)
    
    app.run(host='0.0.0.0', port=8080, debug=True, use_reloader=True, reloader_type='stat')
