"""
Production Disease Detection Server
=====================================

Complete two-stage detection pipeline:
1. Image Validation
2. Crop vs Non-Crop Classification
3. Disease Detection with Confidence
4. Solution Recommendations

Response Format:
{
    "status": "success" | "error",
    "crop": "string",
    "disease": "string | Healthy | Uncertain",
    "confidence": "float (0-100)",
    "solution": {
        "chemical": "string",
        "organic": "string",
        "prevention": "string"
    }
}

Author: Smart Chashi Production Team
Port: 5000
"""

import os
import sys
import io
import logging
import traceback
from datetime import datetime
from typing import Dict, Any, Optional

import numpy as np
from PIL import Image
from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import uvicorn

# Import classifiers
from crop_classifier import CropClassifier
from disease_classifier import DiseaseClassifier
from multi_model_classifier import MultiModelClassifier

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


# ========================================
# Configuration
# ========================================

ALLOWED_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp', '.bmp'}
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB
MIN_IMAGE_SIZE = 50  # Minimum dimension in pixels
MAX_IMAGE_SIZE = 4096  # Maximum dimension in pixels

CROP_CONFIDENCE_THRESHOLD = 55.0  # Minimum confidence for crop detection
DISEASE_CONFIDENCE_THRESHOLD = 60.0  # Minimum confidence for disease detection


# ========================================
# FastAPI Application
# ========================================

app = FastAPI(
    title="Smart Chashi Disease Detection API",
    description="Production-ready crop disease detection using two-stage classification",
    version="2.0.0"
)

# CORS for PHP frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


# ========================================
# Global Classifiers (Lazy Loading)
# ========================================

_crop_classifier: Optional[CropClassifier] = None
_disease_classifier: Optional[DiseaseClassifier] = None
_multi_model_classifier: Optional[MultiModelClassifier] = None

# Configuration: Enable/Disable multi-model ensemble
# Disabled - using single merged model approach for better reliability
USE_MULTI_MODEL_ENSEMBLE = False  # Multi-model disabled, using merged dataset model


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


def get_multi_model_classifier() -> MultiModelClassifier:
    """Lazy load multi-model classifier"""
    global _multi_model_classifier
    if _multi_model_classifier is None:
        logger.info("Initializing Multi-Model Classifier (ensemble mode)...")
        _multi_model_classifier = MultiModelClassifier()
    return _multi_model_classifier


# ========================================
# Image Validation
# ========================================

def validate_image(file: UploadFile) -> Dict[str, Any]:
    """
    Stage 1: Validate uploaded image
    
    Checks:
    - File extension
    - File size
    - Image format validity
    - Image dimensions
    
    Returns:
        {"valid": bool, "error": str or None, "image": PIL.Image or None}
    """
    # Check filename
    if not file.filename:
        return {"valid": False, "error": "No filename provided", "image": None}
    
    # Check extension
    ext = os.path.splitext(file.filename.lower())[1]
    if ext not in ALLOWED_EXTENSIONS:
        return {
            "valid": False,
            "error": f"Invalid file type. Allowed: {', '.join(ALLOWED_EXTENSIONS)}",
            "image": None
        }
    
    try:
        # Read file content
        content = file.file.read()
        file.file.seek(0)  # Reset for potential re-read
        
        # Check file size
        if len(content) > MAX_FILE_SIZE:
            return {
                "valid": False,
                "error": f"File too large. Maximum size: {MAX_FILE_SIZE // (1024*1024)}MB",
                "image": None
            }
        
        if len(content) < 1000:
            return {
                "valid": False,
                "error": "File too small or corrupted",
                "image": None
            }
        
        # Try to open image
        image = Image.open(io.BytesIO(content))
        
        # Convert to RGB
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        # Check dimensions
        width, height = image.size
        if width < MIN_IMAGE_SIZE or height < MIN_IMAGE_SIZE:
            return {
                "valid": False,
                "error": f"Image too small. Minimum: {MIN_IMAGE_SIZE}x{MIN_IMAGE_SIZE}",
                "image": None
            }
        
        if width > MAX_IMAGE_SIZE or height > MAX_IMAGE_SIZE:
            # Resize large images
            ratio = min(MAX_IMAGE_SIZE / width, MAX_IMAGE_SIZE / height)
            new_size = (int(width * ratio), int(height * ratio))
            image = image.resize(new_size, Image.LANCZOS)
            logger.info(f"Resized image from {width}x{height} to {new_size}")
        
        return {"valid": True, "error": None, "image": image}
        
    except Exception as e:
        logger.error(f"Image validation error: {e}")
        return {
            "valid": False,
            "error": f"Invalid image file: {str(e)}",
            "image": None
        }


# ========================================
# Detection Pipeline
# ========================================

def run_detection_pipeline(image: Image.Image, selected_crop: Optional[str] = None) -> Dict[str, Any]:
    """
    Complete detection pipeline:
    
    Stage 1: (Already done) Image validation
    Stage 2: Crop vs Non-Crop detection
    Stage 3: Disease detection (if crop) - Uses multi-model ensemble
              If selected_crop is provided, filter predictions to that crop only
    Stage 4: Solution recommendations
    
    Args:
        image: PIL Image to classify
        selected_crop: Optional crop name to filter predictions (e.g., "Rice", "Tomato")
    
    Returns:
        Complete response dict
    """
    start_time = datetime.now()
    
    # ----------------------------------------
    # Stage 2: Crop vs Non-Crop Detection
    # ----------------------------------------
    logger.info("Stage 2: Crop detection...")
    
    crop_classifier = get_crop_classifier()
    crop_result = crop_classifier.classify(image)
    
    if not crop_result.get("is_crop", False):
        # Not a crop image
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
    
    # ----------------------------------------
    # Stage 3: Disease Detection (Multi-Model Ensemble)
    # ----------------------------------------
    if selected_crop:
        logger.info(f"Stage 3: Disease detection with crop filter: {selected_crop}")
    else:
        logger.info("Stage 3: Disease detection (multi-model ensemble)...")
    
    # Use multi-model classifier for cross-checking
    if USE_MULTI_MODEL_ENSEMBLE:
        classifier = get_multi_model_classifier()
        disease_result = classifier.classify(image, crop_filter=selected_crop)
    else:
        classifier = get_disease_classifier()
        disease_result = classifier.classify(image, crop_filter=selected_crop)
    
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
    
    # ----------------------------------------
    # Stage 4: Build Response
    # ----------------------------------------
    
    crop = disease_result.get("crop", "Unknown")
    disease = disease_result.get("disease", "Uncertain")
    confidence = disease_result.get("confidence", 0)
    is_healthy = disease_result.get("is_healthy", False)
    
    # Apply confidence threshold
    if confidence < DISEASE_CONFIDENCE_THRESHOLD and not is_healthy:
        disease = "Uncertain"
        logger.info(f"Confidence {confidence:.1f}% below threshold {DISEASE_CONFIDENCE_THRESHOLD}%")
    
    # Get solution
    solution = disease_result.get("solution", {})
    
    processing_time = (datetime.now() - start_time).total_seconds() * 1000
    
    # Build response with ensemble information
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
            "crop_detection_method": crop_result.get("method", "unknown"),
            "processing_time_ms": round(processing_time, 2),
            # Multi-model ensemble details
            "ensemble_method": disease_result.get("ensemble_method", "single"),
            "models_agree": disease_result.get("models_agree", False),
            "models_used": disease_result.get("models_used", []),
            "individual_predictions": disease_result.get("individual_predictions", [])
        }
    }
    
    # Log ensemble details
    if disease_result.get("models_agree"):
        logger.info(f"✓ Models AGREE: {crop} - {disease} ({confidence:.1f}%)")
    else:
        logger.info(f"Detection complete: {crop} - {disease} ({confidence:.1f}%) in {processing_time:.0f}ms")
    
    return response


# ========================================
# API Endpoints
# ========================================

@app.get("/")
async def root():
    """Health check endpoint"""
    return {
        "service": "Smart Chashi Disease Detection API",
        "version": "2.0.0",
        "status": "running",
        "endpoints": {
            "predict": "/predict (POST)",
            "health": "/health (GET)",
            "info": "/info (GET)"
        }
    }


@app.get("/health")
async def health():
    """Health check for load balancers"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat()
    }


@app.get("/info")
async def info():
    """Service information"""
    # Get model info if multi-model is enabled
    model_info = {}
    if USE_MULTI_MODEL_ENSEMBLE:
        try:
            classifier = get_multi_model_classifier()
            model_info = classifier.get_model_info()
        except Exception as e:
            logger.warning(f"Could not get model info: {e}")
    
    return {
        "service": "Smart Chashi Disease Detection",
        "version": "2.1.0",
        "multi_model_enabled": USE_MULTI_MODEL_ENSEMBLE,
        "pipeline": [
            "Image Validation",
            "Crop vs Non-Crop Classification",
            "Disease Classification (Multi-Model Ensemble)" if USE_MULTI_MODEL_ENSEMBLE else "Disease Classification",
            "Solution Recommendations"
        ],
        "supported_crops": ["Rice", "Potato", "Tomato", "Mango", "Sugarcane", "Cotton", "Apple", "Corn", "Grape"],
        "thresholds": {
            "crop_confidence": CROP_CONFIDENCE_THRESHOLD,
            "disease_confidence": DISEASE_CONFIDENCE_THRESHOLD
        },
        "models": model_info
    }


@app.post("/predict")
async def predict(file: UploadFile = File(...), crop: Optional[str] = Form(None)):
    """
    Main prediction endpoint
    
    Accepts image file upload and optional crop parameter for filtered prediction.
    
    Request:
        - file: Image file (jpg, jpeg, png, webp, bmp)
        - crop: Optional crop name to filter predictions (e.g., "Rice", "Tomato")
    
    Response:
        {
            "status": "success" | "error",
            "crop": "string",
            "disease": "string | Healthy | Uncertain",
            "confidence": "float (0-100)",
            "solution": {
                "chemical": "string",
                "organic": "string", 
                "prevention": "string"
            }
        }
    """
    try:
        selected_crop = crop.strip() if crop else None
        logger.info(f"Received prediction request: {file.filename}, crop filter: {selected_crop}")
        
        # ----------------------------------------
        # Stage 1: Image Validation
        # ----------------------------------------
        validation = validate_image(file)
        
        if not validation["valid"]:
            return JSONResponse(
                status_code=400,
                content={
                    "status": "error",
                    "error_code": "INVALID_IMAGE",
                    "message": validation["error"],
                    "crop": None,
                    "disease": None,
                    "confidence": 0,
                    "solution": None
                }
            )
        
        image = validation["image"]
        
        # ----------------------------------------
        # Run Detection Pipeline with crop filter
        # ----------------------------------------
        result = run_detection_pipeline(image, selected_crop)
        
        # Return appropriate status code
        if result.get("status") == "error":
            return JSONResponse(status_code=400, content=result)
        
        return result
        
    except Exception as e:
        logger.error(f"Prediction error: {e}")
        logger.error(traceback.format_exc())
        
        return JSONResponse(
            status_code=500,
            content={
                "status": "error",
                "error_code": "INTERNAL_ERROR",
                "message": "Internal server error. Please try again.",
                "crop": None,
                "disease": None,
                "confidence": 0,
                "solution": None
            }
        )


@app.post("/analyze")
async def analyze(file: UploadFile = File(...)):
    """
    Alias for /predict endpoint
    Maintained for backward compatibility
    """
    return await predict(file)


# ========================================
# Main Entry Point
# ========================================

if __name__ == "__main__":
    print("""
    ╔════════════════════════════════════════════════════════════╗
    ║          Smart Chashi Disease Detection Server             ║
    ║                    Production v2.1.0                       ║
    ║              Multi-Model Ensemble Enabled                  ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Pipeline:                                                 ║
    ║    1. Image Validation                                     ║
    ║    2. Crop vs Non-Crop Detection                           ║
    ║    3. Multi-Model Disease Classification                   ║
    ║       • PyTorch EfficientNet-B0 (Primary)                  ║
    ║       • TensorFlow Keras Model (Secondary)                 ║
    ║       • Color Analysis (Fallback)                          ║
    ║    4. Ensemble Voting & Confidence Boosting                ║
    ║    5. Solution Recommendations                             ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Benefits:                                                 ║
    ║    • Cross-validation between models                       ║
    ║    • Fallback if one model lacks crop data                 ║
    ║    • Higher accuracy with model agreement                  ║
    ╠════════════════════════════════════════════════════════════╣
    ║  Endpoints:                                                ║
    ║    POST /predict - Main prediction endpoint                ║
    ║    GET  /health  - Health check                            ║
    ║    GET  /info    - Service information                     ║
    ╚════════════════════════════════════════════════════════════╝
    """)
    
    # Run server
    uvicorn.run(
        app,
        host="0.0.0.0",
        port=5000,
        log_level="info"
    )
