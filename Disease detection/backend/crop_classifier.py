"""
Crop vs Non-Crop Classifier
First stage classifier to determine if an image contains a crop/plant

This module uses:
1. Pre-trained model for crop detection
2. Color analysis as fallback
3. Texture analysis for plant patterns
"""

import numpy as np
from PIL import Image
import logging
from typing import Tuple, Dict, Any
import os

logger = logging.getLogger(__name__)

# Crop detection thresholds
CROP_CONFIDENCE_THRESHOLD = 0.55
GREEN_RATIO_THRESHOLD = 0.15
TEXTURE_VARIANCE_THRESHOLD = 500


class CropClassifier:
    """
    Stage 1: Classify if image contains a crop/plant or not
    
    Uses multiple methods:
    1. TensorFlow model (if available)
    2. Color-based plant detection
    3. Texture analysis
    """
    
    def __init__(self, model_path: str = None):
        """
        Initialize crop classifier
        
        Args:
            model_path: Path to pre-trained crop detection model
        """
        self.model = None
        self.model_loaded = False
        
        # Try to load TensorFlow model
        if model_path and os.path.exists(model_path):
            try:
                import tensorflow as tf
                self.model = tf.keras.models.load_model(model_path)
                self.model_loaded = True
                logger.info(f"Crop classifier model loaded from {model_path}")
            except Exception as e:
                logger.warning(f"Could not load crop model: {e}")
        
        logger.info("CropClassifier initialized")
    
    def classify(self, image: Image.Image) -> Dict[str, Any]:
        """
        Classify if image contains a crop/plant
        
        Args:
            image: PIL Image object (RGB)
            
        Returns:
            {
                "is_crop": bool,
                "confidence": float (0-100),
                "method": str,
                "details": dict
            }
        """
        # Ensure RGB
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        results = []
        
        # Method 1: Color-based detection (always run)
        color_result = self._analyze_colors(image)
        results.append(("color", color_result))
        
        # Method 2: Texture analysis
        texture_result = self._analyze_texture(image)
        results.append(("texture", texture_result))
        
        # Method 3: TensorFlow model (if available)
        if self.model_loaded:
            model_result = self._model_predict(image)
            results.append(("model", model_result))
        
        # Combine results with weighted voting
        final_result = self._combine_results(results)
        
        logger.info(f"Crop classification: is_crop={final_result['is_crop']}, "
                   f"confidence={final_result['confidence']:.1f}%")
        
        return final_result
    
    def _analyze_colors(self, image: Image.Image) -> Dict[str, Any]:
        """
        Analyze image colors to detect plant/vegetation
        
        Plants typically have:
        - High green channel
        - Specific green-to-red ratio
        - Chlorophyll color patterns
        """
        # Resize for faster processing
        img = image.resize((100, 100))
        img_array = np.array(img, dtype=np.float32)
        
        # Extract color channels
        r = img_array[:, :, 0]
        g = img_array[:, :, 1]
        b = img_array[:, :, 2]
        
        # Calculate vegetation indices
        # Excess Green Index (ExG)
        exg = 2 * g - r - b
        exg_mean = np.mean(exg)
        
        # Green ratio
        total = r + g + b + 1e-6  # Avoid division by zero
        green_ratio = np.mean(g / total)
        
        # Calculate percentage of "green" pixels
        # A pixel is considered green if G > R and G > B
        green_pixels = np.sum((g > r) & (g > b))
        total_pixels = img_array.shape[0] * img_array.shape[1]
        green_percentage = green_pixels / total_pixels
        
        # Brown/yellow detection (diseased leaves)
        brown_mask = (r > g) & (r > 100) & (g > 50) & (b < 100)
        brown_percentage = np.sum(brown_mask) / total_pixels
        
        # Determine if it's a plant
        is_plant = False
        confidence = 0.0
        
        if green_percentage > GREEN_RATIO_THRESHOLD:
            is_plant = True
            confidence = min(float(green_percentage) * 200, 95)
        elif brown_percentage > 0.1 and green_percentage > 0.05:
            # Could be a diseased/brown leaf
            is_plant = True
            confidence = min(float(brown_percentage + green_percentage) * 150, 80)
        elif exg_mean > 20:
            is_plant = True
            confidence = min(float(exg_mean) / 2, 70)
        
        return {
            "is_crop": bool(is_plant),
            "confidence": float(confidence),
            "details": {
                "green_percentage": float(round(green_percentage * 100, 2)),
                "brown_percentage": float(round(brown_percentage * 100, 2)),
                "exg_mean": float(round(exg_mean, 2)),
                "green_ratio": float(round(green_ratio, 4))
            }
        }
    
    def _analyze_texture(self, image: Image.Image) -> Dict[str, Any]:
        """
        Analyze texture patterns typical of plant leaves
        
        Leaves have:
        - Specific edge patterns (veins)
        - Organic texture variance
        - Non-uniform surfaces
        """
        # Convert to grayscale and resize
        gray = image.convert('L').resize((100, 100))
        gray_array = np.array(gray, dtype=np.float32)
        
        # Calculate texture variance (leaves have moderate variance)
        variance = np.var(gray_array)
        
        # Calculate edge density using simple gradient
        # Use same slicing to ensure matching shapes
        gx = np.abs(gray_array[:, 1:] - gray_array[:, :-1])  # Horizontal gradient
        gy = np.abs(gray_array[1:, :] - gray_array[:-1, :])  # Vertical gradient
        
        # Get the overlapping region for both gradients
        gx_crop = gx[:-1, :]  # Shape: (99, 99)
        gy_crop = gy[:, :-1]  # Shape: (99, 99)
        
        edge_magnitude = np.sqrt(gx_crop**2 + gy_crop**2)
        edge_density = np.mean(edge_magnitude)
        
        # Leaves typically have moderate variance and edge density
        is_plant = False
        confidence = 0.0
        
        # Natural textures have specific variance ranges
        if TEXTURE_VARIANCE_THRESHOLD < variance < 5000:
            if 10 < edge_density < 50:
                is_plant = True
                confidence = 70.0
            elif 5 < edge_density < 60:
                is_plant = True
                confidence = 55.0
        elif 200 < variance < 8000 and edge_density > 5:
            is_plant = True
            confidence = 50.0
        
        return {
            "is_crop": bool(is_plant),
            "confidence": float(confidence),
            "details": {
                "variance": float(round(variance, 2)),
                "edge_density": float(round(edge_density, 2))
            }
        }
    
    def _model_predict(self, image: Image.Image) -> Dict[str, Any]:
        """
        Use TensorFlow model for crop detection
        """
        try:
            # Preprocess
            img = image.resize((224, 224))
            img_array = np.array(img, dtype=np.float32) / 255.0
            img_array = np.expand_dims(img_array, axis=0)
            
            # Predict
            predictions = self.model.predict(img_array, verbose=0)
            
            # Assuming binary classification: [not_crop, crop]
            if predictions.shape[-1] >= 2:
                crop_prob = float(predictions[0][1])
            else:
                crop_prob = float(predictions[0][0])
            
            return {
                "is_crop": crop_prob > 0.5,
                "confidence": crop_prob * 100,
                "details": {"raw_probability": crop_prob}
            }
        except Exception as e:
            logger.error(f"Model prediction error: {e}")
            return {"is_crop": False, "confidence": 0, "details": {"error": str(e)}}
    
    def _combine_results(self, results: list) -> Dict[str, Any]:
        """
        Combine results from multiple methods using weighted voting
        """
        weights = {
            "model": 0.5,
            "color": 0.35,
            "texture": 0.15
        }
        
        total_weight = 0
        weighted_confidence = 0
        is_crop_votes = 0
        total_votes = 0
        
        details = {}
        
        for method, result in results:
            weight = weights.get(method, 0.2)
            total_weight += weight
            
            if result["is_crop"]:
                is_crop_votes += weight
                weighted_confidence += result["confidence"] * weight
            
            total_votes += weight
            details[f"{method}_result"] = result
        
        # Final decision
        is_crop = is_crop_votes > (total_votes * 0.4)  # 40% threshold
        
        if is_crop and is_crop_votes > 0:
            final_confidence = float(weighted_confidence / is_crop_votes)
        elif not is_crop:
            # For non-crops, report how confident we are it's NOT a crop
            if total_weight > 0 and weighted_confidence > 0:
                final_confidence = float(100 - (weighted_confidence / total_weight))
            else:
                final_confidence = 100.0  # Very confident it's not a crop
            final_confidence = float(max(final_confidence, 70))  # At least 70% confident it's not a crop
        else:
            final_confidence = 50.0  # Uncertain
        
        return {
            "is_crop": bool(is_crop),
            "confidence": float(round(final_confidence, 2)),
            "method": "combined",
            "details": details
        }


def test_classifier():
    """Test the crop classifier"""
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python crop_classifier.py <image_path>")
        return
    
    image_path = sys.argv[1]
    image = Image.open(image_path).convert('RGB')
    
    classifier = CropClassifier()
    result = classifier.classify(image)
    
    print("\n" + "=" * 50)
    print("CROP CLASSIFICATION RESULT")
    print("=" * 50)
    print(f"Is Crop/Plant: {result['is_crop']}")
    print(f"Confidence: {result['confidence']:.1f}%")
    print(f"Method: {result['method']}")
    print(f"\nDetails: {result['details']}")


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_classifier()
