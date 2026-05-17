"""
Simple Plant Detection API
===========================
A simple API that uses the simple-plant-detection model to classify
whether an uploaded image is a crop/plant or something else.

Endpoints:
- POST /detect - Upload an image to detect if it's a plant/crop
- GET /health - Health check
- GET /info - API information
"""

import os
import io
import logging
from datetime import datetime
from typing import Dict, Any, Optional, List

import numpy as np
from PIL import Image
from flask import Flask, request, jsonify
from flask_cors import CORS

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
APP_NAME = "Simple Plant Detection API"
APP_VERSION = "1.0.0"
MODEL_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'simple-plant-detection')
ALLOWED_EXTENSIONS = {'png', 'jpg', 'jpeg', 'webp', 'bmp'}
MAX_CONTENT_LENGTH = 10 * 1024 * 1024  # 10MB
CONFIDENCE_THRESHOLD = 50.0  # Minimum confidence to classify as plant

app.config['MAX_CONTENT_LENGTH'] = MAX_CONTENT_LENGTH

# ========================================
# Plant Types from Model
# ========================================

PLANT_TYPES = [
    "guava", "galangal", "bilimbi", "paddy", "eggplant", "cucumber",
    "cassava", "papaya", "banana", "orange", "cantaloupe", "coconut",
    "soybeans", "pomelo", "pineapple", "melon", "shallot", "peperchili",
    "spinach", "tobacco", "aloevera", "curcuma", "corn", "ginger",
    "sweetpotatoes", "kale", "longbeans", "watermelon", "mango", "waterapple"
]

# ========================================
# ML Classifier (Lazy Loading)
# ========================================

_classifier = None


def get_classifier():
    """Lazy load the plant classifier"""
    global _classifier
    if _classifier is None:
        logger.info("Initializing Plant Classifier...")
        _classifier = PlantClassifier(MODEL_PATH)
    return _classifier


class PlantClassifier:
    """Plant classification using the simple-plant-detection ViT model"""
    
    def __init__(self, model_path: str):
        self.model_path = model_path
        self.model = None
        self.processor = None
        self.device = None
        self._load_model()
    
    def _load_model(self):
        """Load the ViT model"""
        try:
            import torch
            from transformers import ViTForImageClassification, ViTImageProcessor
            
            logger.info(f"Loading model from: {self.model_path}")
            
            # Determine device
            self.device = "cuda" if torch.cuda.is_available() else "cpu"
            logger.info(f"Using device: {self.device}")
            
            # Load model and processor
            self.model = ViTForImageClassification.from_pretrained(self.model_path)
            self.processor = ViTImageProcessor.from_pretrained(self.model_path)
            
            self.model.to(self.device)
            self.model.eval()
            
            logger.info("Model loaded successfully!")
            
        except Exception as e:
            logger.error(f"Failed to load model: {e}")
            raise
    
    def classify(self, image: Image.Image) -> Dict[str, Any]:
        """
        Classify an image to determine if it's a plant/crop.
        
        Returns:
            Dict with keys:
            - is_plant: bool - Whether the image is a plant/crop
            - confidence: float - Confidence percentage
            - plant_type: str - Detected plant type (if is_plant is True)
            - top_predictions: list - Top 3 predictions with confidence
        """
        try:
            import torch
            
            # Ensure RGB
            if image.mode != 'RGB':
                image = image.convert('RGB')
            
            # Process image
            inputs = self.processor(images=image, return_tensors="pt")
            inputs = {k: v.to(self.device) for k, v in inputs.items()}
            
            # Inference
            with torch.no_grad():
                outputs = self.model(**inputs)
                logits = outputs.logits
                probs = torch.nn.functional.softmax(logits, dim=-1)
            
            # Get predictions
            probs_np = probs.cpu().numpy()[0]
            top_indices = np.argsort(probs_np)[::-1][:3]
            
            top_predictions = []
            for idx in top_indices:
                top_predictions.append({
                    "plant_type": PLANT_TYPES[idx],
                    "confidence": round(float(probs_np[idx]) * 100, 2)
                })
            
            # Best prediction
            best_idx = top_indices[0]
            best_confidence = float(probs_np[best_idx]) * 100
            best_plant = PLANT_TYPES[best_idx]
            
            # Determine if it's a plant (based on confidence)
            is_plant = best_confidence >= CONFIDENCE_THRESHOLD
            
            return {
                "is_plant": is_plant,
                "confidence": round(best_confidence, 2),
                "plant_type": best_plant if is_plant else None,
                "top_predictions": top_predictions,
                "status": "success"
            }
            
        except Exception as e:
            logger.error(f"Classification error: {e}")
            return {
                "is_plant": False,
                "confidence": 0,
                "plant_type": None,
                "top_predictions": [],
                "status": "error",
                "error": str(e)
            }


# ========================================
# Helper Functions
# ========================================

def allowed_file(filename: str) -> bool:
    """Check if file extension is allowed"""
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


def validate_image(file_storage) -> Dict[str, Any]:
    """Validate uploaded image"""
    try:
        content = file_storage.read()
        file_storage.seek(0)
        
        if len(content) > MAX_CONTENT_LENGTH:
            return {"valid": False, "error": "File too large (max 10MB)", "image": None}
        
        if len(content) < 1000:
            return {"valid": False, "error": "File too small or corrupted", "image": None}
        
        image = Image.open(io.BytesIO(content))
        
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        return {"valid": True, "error": None, "image": image}
        
    except Exception as e:
        logger.error(f"Image validation error: {e}")
        return {"valid": False, "error": f"Invalid image: {str(e)}", "image": None}


# ========================================
# API Routes
# ========================================

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        "status": "healthy",
        "service": APP_NAME,
        "version": APP_VERSION,
        "timestamp": datetime.now().isoformat()
    })


@app.route('/info', methods=['GET'])
def api_info():
    """API information"""
    return jsonify({
        "service": APP_NAME,
        "version": APP_VERSION,
        "description": "A simple API to detect if an image contains a plant/crop or something else",
        "endpoints": {
            "POST /detect": "Upload an image to detect if it's a plant/crop",
            "GET /health": "Health check",
            "GET /info": "API information"
        },
        "supported_plants": PLANT_TYPES,
        "confidence_threshold": CONFIDENCE_THRESHOLD,
        "max_file_size_mb": MAX_CONTENT_LENGTH // (1024 * 1024)
    })


@app.route('/detect', methods=['POST'])
def detect_plant():
    """
    Main detection endpoint.
    
    Accepts:
    - Form data with 'image' or 'file' key
    
    Returns:
    - is_plant: bool - True if image is a plant/crop, False otherwise
    - classification: str - "crop/plant" or "other"
    - confidence: float - Confidence percentage
    - plant_type: str - Specific plant type detected (if is_plant)
    - top_predictions: list - Top 3 plant type predictions
    """
    # Check for file
    if 'image' not in request.files and 'file' not in request.files:
        return jsonify({
            'success': False,
            'error': 'No image file uploaded. Use "image" or "file" as the form field name.',
            'classification': None
        }), 400
    
    file = request.files.get('image') or request.files.get('file')
    
    if file.filename == '':
        return jsonify({
            'success': False,
            'error': 'No file selected',
            'classification': None
        }), 400
    
    if not allowed_file(file.filename):
        return jsonify({
            'success': False,
            'error': f'Invalid file type. Allowed: {", ".join(ALLOWED_EXTENSIONS)}',
            'classification': None
        }), 400
    
    # Validate image
    validation = validate_image(file)
    if not validation["valid"]:
        return jsonify({
            'success': False,
            'error': validation["error"],
            'classification': None
        }), 400
    
    image = validation["image"]
    
    try:
        # Run classification
        start_time = datetime.now()
        classifier = get_classifier()
        result = classifier.classify(image)
        processing_time = (datetime.now() - start_time).total_seconds() * 1000
        
        if result["status"] == "error":
            return jsonify({
                'success': False,
                'error': result.get("error", "Classification failed"),
                'classification': None
            }), 500
        
        # Build response
        is_plant = result["is_plant"]
        classification = "crop/plant" if is_plant else "other"
        
        response = {
            'success': True,
            'is_plant': is_plant,
            'classification': classification,
            'confidence': result["confidence"],
            'message': f"This image is classified as: {classification.upper()}"
        }
        
        if is_plant:
            response['plant_type'] = result["plant_type"]
            response['message'] = f"Detected plant: {result['plant_type'].title()} with {result['confidence']}% confidence"
        
        response['top_predictions'] = result["top_predictions"]
        response['processing_time_ms'] = round(processing_time, 2)
        
        return jsonify(response)
        
    except Exception as e:
        logger.error(f"Detection error: {e}")
        import traceback
        logger.error(traceback.format_exc())
        
        return jsonify({
            'success': False,
            'error': str(e),
            'classification': None
        }), 500


# ========================================
# Main Entry Point
# ========================================

if __name__ == '__main__':
    print("""
    ================================================================
    |          Simple Plant Detection API v1.0.0                   |
    ================================================================
    |  Endpoints:                                                  |
    |    POST /detect  - Detect if image is a plant/crop           |
    |    GET  /health  - Health check                              |
    |    GET  /info    - API information                           |
    ----------------------------------------------------------------
    |  Running on: http://localhost:5000                           |
    ================================================================
    """)
    
    # Pre-load the model
    logger.info("Pre-loading model...")
    get_classifier()
    logger.info("Model loaded! Starting server...")
    
    app.run(host='0.0.0.0', port=5000, debug=True)
