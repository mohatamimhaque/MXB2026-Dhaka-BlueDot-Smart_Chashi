"""
Multi-Model Disease Classifier
==============================

Uses multiple models for cross-checking detection results:
1. PyTorch EfficientNet-B0 (primary model)
2. TensorFlow/Keras model (secondary model)
3. Color/Pattern analysis (fallback)

Benefits:
- Cross-validation between models for higher accuracy
- Fallback mechanism if one model lacks data for a crop
- Ensemble voting for more reliable predictions
- Confidence boosting when models agree

Author: Smart Chashi Production Team
"""

import numpy as np
from PIL import Image
import json
import os
import logging
from typing import Dict, Any, List, Tuple, Optional
from dataclasses import dataclass
from enum import Enum

# PyTorch imports
import torch
import torch.nn as nn
from torchvision import transforms, models

logger = logging.getLogger(__name__)

# Configuration
CONFIDENCE_THRESHOLD = 60.0
INPUT_SHAPE = (224, 224)
AGREEMENT_BOOST = 10.0  # Confidence boost when models agree
MIN_CONFIDENCE_FOR_ENSEMBLE = 40.0  # Minimum confidence to include in ensemble

# Device configuration
DEVICE = torch.device('cuda' if torch.cuda.is_available() else 'cpu')


class ModelType(Enum):
    """Types of models supported"""
    PYTORCH = "pytorch"
    TENSORFLOW = "tensorflow"
    COLOR_ANALYSIS = "color_analysis"


@dataclass
class ModelPrediction:
    """Prediction result from a single model"""
    model_name: str
    model_type: ModelType
    class_name: str
    class_index: int
    confidence: float
    all_probabilities: np.ndarray
    top_3: List[Dict]
    available: bool = True


class PlantDiseaseModel(nn.Module):
    """PyTorch model architecture for disease detection"""
    def __init__(self, num_classes):
        super(PlantDiseaseModel, self).__init__()
        self.backbone = models.efficientnet_b0(weights=None)
        in_features = self.backbone.classifier[1].in_features
        self.backbone.classifier = nn.Sequential(
            nn.Dropout(p=0.3),
            nn.Linear(in_features, 512),
            nn.ReLU(),
            nn.Dropout(p=0.3),
            nn.Linear(512, num_classes)
        )
    
    def forward(self, x):
        return self.backbone(x)


class MultiModelClassifier:
    """
    Multi-Model Disease Classification System
    
    Uses ensemble of models:
    1. PyTorch EfficientNet-B0 V1 (primary - vipoooool/new-plant-diseases-dataset)
    2. PyTorch EfficientNet-B0 V2 (secondary - rashidthihan/plant-disease-dataset)
    3. TensorFlow Keras model (tertiary)
    4. Color-based analysis (fallback)
    
    Cross-checks predictions and returns highest confidence result
    with agreement boosting when models concur.
    Provides fallback when one model doesn't have data for a crop.
    """
    
    def __init__(self, 
                 pytorch_model_path: str = None,
                 pytorch_model_v2_path: str = None,
                 keras_model_path: str = None,
                 class_names_path: str = None,
                 class_names_v2_path: str = None):
        """
        Initialize multi-model classifier
        
        Args:
            pytorch_model_path: Path to PyTorch model V1 (.pth)
            pytorch_model_v2_path: Path to PyTorch model V2 (.pth) - additional dataset
            keras_model_path: Path to Keras model (.h5 or .keras)
            class_names_path: Path to class names JSON for V1
            class_names_v2_path: Path to class names JSON for V2
        """
        self.models = {}
        self.class_names = []
        self.class_names_v2 = []
        self.all_class_names = []  # Combined unique class names
        self.device = DEVICE
        self.solutions_db = self._load_solutions_database()
        
        # PyTorch transform
        self.pytorch_transform = transforms.Compose([
            transforms.Resize((INPUT_SHAPE[0], INPUT_SHAPE[1])),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        
        # Default paths
        base_dir = os.path.dirname(__file__)
        if pytorch_model_path is None:
            pytorch_model_path = os.path.join(base_dir, "models", "disease_model_pytorch.pth")
        if pytorch_model_v2_path is None:
            pytorch_model_v2_path = os.path.join(base_dir, "models", "disease_model_v2_pytorch.pth")
        if keras_model_path is None:
            keras_model_path = os.path.join(base_dir, "models", "disease_model.keras")
        if class_names_path is None:
            class_names_path = os.path.join(base_dir, "models", "class_names.json")
        if class_names_v2_path is None:
            class_names_v2_path = os.path.join(base_dir, "models", "class_names_v2.json")
        
        # Load class names for both models
        self._load_class_names(class_names_path)
        self._load_class_names_v2(class_names_v2_path)
        self._build_combined_class_names()
        
        # Load all available models
        self._load_pytorch_model(pytorch_model_path)
        self._load_pytorch_model_v2(pytorch_model_v2_path)
        self._load_keras_model(keras_model_path)
        
        logger.info(f"MultiModelClassifier initialized with {len(self.models)} models, "
                   f"{len(self.class_names)} V1 classes, {len(self.class_names_v2)} V2 classes")
    
    def _load_class_names(self, path: str):
        """Load class names from JSON file for V1 model"""
        try:
            if os.path.exists(path):
                with open(path, 'r', encoding='utf-8') as f:
                    self.class_names = json.load(f)
                logger.info(f"Loaded {len(self.class_names)} V1 class names")
            else:
                logger.warning(f"V1 class names file not found: {path}")
                self.class_names = []
        except Exception as e:
            logger.error(f"Error loading V1 class names: {e}")
            self.class_names = []
    
    def _load_class_names_v2(self, path: str):
        """Load class names from JSON file for V2 model"""
        try:
            if os.path.exists(path):
                with open(path, 'r', encoding='utf-8') as f:
                    self.class_names_v2 = json.load(f)
                logger.info(f"Loaded {len(self.class_names_v2)} V2 class names")
            else:
                logger.warning(f"V2 class names file not found: {path}")
                self.class_names_v2 = []
        except Exception as e:
            logger.error(f"Error loading V2 class names: {e}")
            self.class_names_v2 = []
    
    def _build_combined_class_names(self):
        """Build combined class names from both models for unified output"""
        # Create a set of unique class names
        all_names = set(self.class_names) | set(self.class_names_v2)
        self.all_class_names = sorted(list(all_names))
        logger.info(f"Combined {len(self.all_class_names)} unique class names from both models")
    
    def _load_pytorch_model(self, path: str):
        """Load PyTorch model V1 (primary model)"""
        try:
            if os.path.exists(path):
                checkpoint = torch.load(path, map_location=self.device, weights_only=False)
                num_classes = len(self.class_names) if self.class_names else 38
                
                model = PlantDiseaseModel(num_classes)
                if 'model_state_dict' in checkpoint:
                    model.load_state_dict(checkpoint['model_state_dict'])
                else:
                    model.load_state_dict(checkpoint)
                
                model.to(self.device)
                model.eval()
                
                self.models['pytorch_v1'] = {
                    'model': model,
                    'type': ModelType.PYTORCH,
                    'weight': 0.40,
                    'name': 'PyTorch V1 (vipoooool dataset)',
                    'class_names': self.class_names
                }
                logger.info(f"PyTorch V1 model loaded: {path}")
            else:
                logger.warning(f"PyTorch V1 model not found: {path}")
        except Exception as e:
            logger.error(f"Error loading PyTorch V1 model: {e}")
    
    def _load_pytorch_model_v2(self, path: str):
        """Load PyTorch model V2 (additional dataset model)"""
        try:
            if os.path.exists(path):
                checkpoint = torch.load(path, map_location=self.device, weights_only=False)
                
                # V2 model may have different number of classes
                if 'class_names' in checkpoint:
                    v2_class_names = checkpoint['class_names']
                    num_classes = len(v2_class_names)
                else:
                    num_classes = len(self.class_names_v2) if self.class_names_v2 else 38
                    v2_class_names = self.class_names_v2
                
                model = PlantDiseaseModel(num_classes)
                if 'model_state_dict' in checkpoint:
                    model.load_state_dict(checkpoint['model_state_dict'])
                else:
                    model.load_state_dict(checkpoint)
                
                model.to(self.device)
                model.eval()
                
                self.models['pytorch_v2'] = {
                    'model': model,
                    'type': ModelType.PYTORCH,
                    'weight': 0.35,
                    'name': 'PyTorch V2 (rashidthihan dataset)',
                    'class_names': v2_class_names
                }
                logger.info(f"PyTorch V2 model loaded: {path} ({num_classes} classes)")
            else:
                logger.info(f"PyTorch V2 model not found (optional): {path}")
        except Exception as e:
            logger.error(f"Error loading PyTorch V2 model: {e}")
    
    def _load_keras_model(self, path: str):
        """Load TensorFlow/Keras model"""
        try:
            # Try to import TensorFlow
            import tensorflow as tf
            
            # Try multiple possible paths
            possible_paths = [
                path,
                path.replace('.keras', '.h5'),
                os.path.join(os.path.dirname(path), 'disease_model.h5'),
            ]
            
            loaded = False
            for model_path in possible_paths:
                if os.path.exists(model_path):
                    try:
                        # Suppress TF warnings
                        tf.get_logger().setLevel('ERROR')
                        model = tf.keras.models.load_model(model_path, compile=False)
                        
                        self.models['keras_model'] = {
                            'model': model,
                            'type': ModelType.TENSORFLOW,
                            'weight': 0.40,
                            'name': 'TensorFlow Keras Model'
                        }
                        logger.info(f"Keras model loaded: {model_path}")
                        loaded = True
                        break
                    except Exception as inner_e:
                        logger.warning(f"Could not load Keras model from {model_path}: {inner_e}")
                        continue
            
            if not loaded:
                logger.warning("No Keras model could be loaded")
                
        except ImportError:
            logger.warning("TensorFlow not available, Keras model will not be used")
        except Exception as e:
            logger.error(f"Error loading Keras model: {e}")
    
    def _predict_pytorch(self, image: Image.Image, model_key: str = 'pytorch_v1') -> Optional[ModelPrediction]:
        """Run prediction with PyTorch model (V1 or V2)"""
        if model_key not in self.models:
            return None
        
        try:
            model_info = self.models[model_key]
            model = model_info['model']
            model_class_names = model_info.get('class_names', self.class_names)
            
            # Preprocess
            input_tensor = self.pytorch_transform(image)
            input_tensor = input_tensor.unsqueeze(0).to(self.device)
            
            # Predict
            with torch.no_grad():
                outputs = model(input_tensor)
                probabilities = torch.softmax(outputs, dim=1)
                predictions = probabilities.cpu().numpy()[0]
            
            # Get top prediction
            top_idx = int(np.argmax(predictions))
            confidence = float(predictions[top_idx]) * 100
            
            class_name = model_class_names[top_idx] if top_idx < len(model_class_names) else f"Class_{top_idx}"
            
            return ModelPrediction(
                model_name=model_info['name'],
                model_type=ModelType.PYTORCH,
                class_name=class_name,
                class_index=top_idx,
                confidence=confidence,
                all_probabilities=predictions,
                top_3=self._get_top_n_for_model(predictions, model_class_names, 3)
            )
        except Exception as e:
            logger.error(f"{model_key} prediction error: {e}")
            return None
    
    def _get_top_n_for_model(self, predictions: np.ndarray, class_names: List[str], n: int = 3) -> List[Dict]:
        """Get top N predictions with names and confidences for specific model"""
        top_indices = np.argsort(predictions)[-n:][::-1]
        
        results = []
        for idx in top_indices:
            if idx < len(class_names):
                name = class_names[idx]
            else:
                name = f"Class_{idx}"
            
            results.append({
                "class": name,
                "confidence": round(float(predictions[idx]) * 100, 2)
            })
        
        return results
    
    def _predict_keras(self, image: Image.Image) -> Optional[ModelPrediction]:
        """Run prediction with Keras model"""
        if 'keras_model' not in self.models:
            return None
        
        try:
            model_info = self.models['keras_model']
            model = model_info['model']
            
            # Preprocess for Keras (ImageNet style)
            img = image.resize(INPUT_SHAPE)
            img_array = np.array(img, dtype=np.float32)
            img_array = img_array / 255.0  # Normalize to [0, 1]
            img_array = np.expand_dims(img_array, axis=0)
            
            # Predict
            predictions = model.predict(img_array, verbose=0)[0]
            
            # Apply softmax if needed
            if predictions.min() < 0 or predictions.max() > 1.01:
                exp_preds = np.exp(predictions - np.max(predictions))
                predictions = exp_preds / exp_preds.sum()
            
            # Get top prediction
            top_idx = int(np.argmax(predictions))
            confidence = float(predictions[top_idx]) * 100
            
            class_name = self.class_names[top_idx] if top_idx < len(self.class_names) else f"Class_{top_idx}"
            
            return ModelPrediction(
                model_name=model_info['name'],
                model_type=ModelType.TENSORFLOW,
                class_name=class_name,
                class_index=top_idx,
                confidence=confidence,
                all_probabilities=predictions,
                top_3=self._get_top_n(predictions, 3)
            )
        except Exception as e:
            logger.error(f"Keras prediction error: {e}")
            return None
    
    def _predict_color_analysis(self, image: Image.Image) -> Optional[ModelPrediction]:
        """Fallback color-based prediction"""
        try:
            img = image.resize((100, 100))
            img_array = np.array(img, dtype=np.float32)
            
            r = img_array[:, :, 0]
            g = img_array[:, :, 1]
            b = img_array[:, :, 2]
            
            # Color analysis
            green_ratio = np.mean(g) / (np.mean(r) + np.mean(g) + np.mean(b) + 1e-6)
            brown_mask = (r > g) & (r > 100) & (g > 50) & (b < 100)
            brown_ratio = np.sum(brown_mask) / (img_array.shape[0] * img_array.shape[1])
            yellow_mask = (r > 150) & (g > 150) & (b < 100)
            yellow_ratio = np.sum(yellow_mask) / (img_array.shape[0] * img_array.shape[1])
            
            # Create pseudo-probabilities
            num_classes = max(len(self.class_names), 38)
            probs = np.full(num_classes, 0.01)
            
            # Healthy detection
            if green_ratio > 0.38:
                for i, name in enumerate(self.class_names):
                    if "healthy" in name.lower():
                        probs[i] = 0.5 + green_ratio * 0.4
                        break
            
            # Brown spot / blight detection
            elif brown_ratio > 0.12:
                for i, name in enumerate(self.class_names):
                    if "spot" in name.lower() or "blight" in name.lower():
                        probs[i] = 0.4 + brown_ratio * 0.4
                        break
            
            # Yellow / rust detection
            elif yellow_ratio > 0.10:
                for i, name in enumerate(self.class_names):
                    if "rust" in name.lower() or "yellow" in name.lower():
                        probs[i] = 0.4 + yellow_ratio * 0.4
                        break
            
            # Normalize
            probs = probs / probs.sum()
            
            top_idx = int(np.argmax(probs))
            confidence = float(probs[top_idx]) * 100
            
            class_name = self.class_names[top_idx] if top_idx < len(self.class_names) else "Unknown"
            
            return ModelPrediction(
                model_name="Color Analysis",
                model_type=ModelType.COLOR_ANALYSIS,
                class_name=class_name,
                class_index=top_idx,
                confidence=confidence,
                all_probabilities=probs,
                top_3=self._get_top_n(probs, 3)
            )
        except Exception as e:
            logger.error(f"Color analysis error: {e}")
            return None
    
    def _get_top_n(self, predictions: np.ndarray, n: int = 3) -> List[Dict]:
        """Get top N predictions with names and confidences"""
        top_indices = np.argsort(predictions)[-n:][::-1]
        
        results = []
        for idx in top_indices:
            if idx < len(self.class_names):
                name = self.class_names[idx]
            else:
                name = f"Class_{idx}"
            
            results.append({
                "class": name,
                "confidence": round(float(predictions[idx]) * 100, 2)
            })
        
        return results
    
    def _ensemble_predictions(self, predictions: List[ModelPrediction]) -> Dict[str, Any]:
        """
        Combine predictions from multiple models using weighted voting
        
        Strategy:
        1. Weight each model's prediction
        2. Check for agreement between models (by class name, not index)
        3. Boost confidence if models agree
        4. Handle models with different class sets - prefer model that has the crop type
        5. Use fallback if primary models disagree significantly
        """
        if not predictions:
            return {
                "class_name": "Unknown",
                "confidence": 0.0,
                "method": "none",
                "agreement": False
            }
        
        # Filter valid predictions above minimum threshold
        valid_predictions = [p for p in predictions 
                           if p is not None and p.confidence >= MIN_CONFIDENCE_FOR_ENSEMBLE]
        
        if not valid_predictions:
            # Use best available even if low confidence
            valid_predictions = [p for p in predictions if p is not None]
        
        if not valid_predictions:
            return {
                "class_name": "Unknown",
                "confidence": 0.0,
                "method": "none",
                "agreement": False
            }
        
        # If only one model, return its prediction
        if len(valid_predictions) == 1:
            pred = valid_predictions[0]
            return {
                "class_name": pred.class_name,
                "class_index": pred.class_index,
                "confidence": pred.confidence,
                "method": pred.model_name,
                "agreement": False,
                "all_probabilities": pred.all_probabilities,
                "top_3": pred.top_3
            }
        
        # Normalize class names for comparison (handle slight naming differences)
        def normalize_class(name: str) -> str:
            return name.lower().replace(" ", "_").replace("-", "_")
        
        # Extract crop type from class name
        def extract_crop(name: str) -> str:
            """Extract crop name from class like 'BrownSpot (Rice)' or 'Corn_(maize)___healthy'"""
            name_lower = name.lower()
            # Format: "Disease (Crop)"
            if '(' in name and ')' in name:
                crop = name.split('(')[-1].split(')')[0].strip().lower()
                return crop
            # Format: "Crop___disease" or "Crop_disease"
            if '___' in name:
                crop = name.split('___')[0].replace('_', ' ').strip().lower()
                return crop
            if '__' in name:
                crop = name.split('__')[0].replace('_', ' ').strip().lower()
                return crop
            return name_lower
        
        # Check if a crop type exists in the model's class names
        def model_has_crop(model_name: str, crop: str) -> bool:
            """Check if a model was trained on this crop type"""
            model_info = self.models.get(model_name, {})
            model_classes = model_info.get('class_names', [])
            if not model_classes:
                # For keras model, use V1 class names
                if model_name == 'keras_model':
                    model_classes = self.class_names
            
            crop_lower = crop.lower()
            for cls in model_classes:
                cls_lower = cls.lower()
                if crop_lower in cls_lower or crop_lower.replace(' ', '_') in cls_lower:
                    return True
            return False
        
        # Separate predictions by whether model has that crop type
        v1_pred = None
        v2_pred = None
        other_preds = []
        
        for pred in valid_predictions:
            if 'v1' in pred.model_name.lower() or 'pytorch v1' in pred.model_name.lower():
                v1_pred = pred
            elif 'v2' in pred.model_name.lower() or 'pytorch v2' in pred.model_name.lower():
                v2_pred = pred
            else:
                other_preds.append(pred)
        
        # Smart crop-aware selection: Check if models have the crop they're predicting
        best_prediction = None
        
        if v1_pred and v2_pred:
            v1_crop = extract_crop(v1_pred.class_name)
            v2_crop = extract_crop(v2_pred.class_name)
            
            v1_has_own_crop = model_has_crop('pytorch_v1', v1_crop)
            v2_has_own_crop = model_has_crop('pytorch_v2', v2_crop)
            
            logger.info(f"V1 predicts {v1_crop} (has crop: {v1_has_own_crop}), V2 predicts {v2_crop} (has crop: {v2_has_own_crop})")
            
            # If V2 has its crop but V1 doesn't have V1's predicted crop in V1's classes
            # This means V1 is guessing - prefer V2
            v1_has_v2_crop = model_has_crop('pytorch_v1', v2_crop)
            v2_has_v1_crop = model_has_crop('pytorch_v2', v1_crop)
            
            if not v1_has_v2_crop and v2_has_own_crop and v2_pred.confidence > 80:
                # V1 doesn't have V2's crop type - V2 is specialized for this
                logger.info(f"V2 specialized for {v2_crop} (V1 doesn't have it) - using V2")
                best_prediction = v2_pred
            elif not v2_has_v1_crop and v1_has_own_crop and v1_pred.confidence > 80:
                # V2 doesn't have V1's crop type - V1 is specialized for this
                logger.info(f"V1 specialized for {v1_crop} (V2 doesn't have it) - using V1")
                best_prediction = v1_pred
            elif v1_crop == v2_crop:
                # Both agree on crop type - use higher confidence
                best_prediction = v1_pred if v1_pred.confidence >= v2_pred.confidence else v2_pred
                logger.info(f"Both models agree on crop {v1_crop} - using higher confidence")
        
        # Fallback to standard ensemble logic if crop-aware didn't decide
        if best_prediction is None:
            # Check agreement between top predictions (by class name for cross-dataset compatibility)
            top_classes = [p.class_name for p in valid_predictions]
            
            normalized_classes = [normalize_class(c) for c in top_classes]
            class_counts = {}
            for i, norm_cls in enumerate(normalized_classes):
                # Group similar class names
                matched = False
                for existing in class_counts:
                    if norm_cls in existing or existing in norm_cls:
                        class_counts[existing] = class_counts[existing] + 1
                        matched = True
                        break
                if not matched:
                    class_counts[norm_cls] = 1
            
            # Find most voted class (by normalized name)
            most_voted_normalized = max(class_counts.keys(), key=lambda x: class_counts[x])
            
            # Find best original class name matching the consensus
            best_confidence = 0
            for pred in valid_predictions:
                if normalize_class(pred.class_name) == most_voted_normalized or \
                   most_voted_normalized in normalize_class(pred.class_name) or \
                   normalize_class(pred.class_name) in most_voted_normalized:
                    if pred.confidence > best_confidence:
                        best_confidence = pred.confidence
                        best_prediction = pred
            
            if best_prediction is None:
                # Fallback to highest confidence prediction
                best_prediction = max(valid_predictions, key=lambda p: p.confidence)
        
        # Re-check agreement
        top_classes = [p.class_name for p in valid_predictions]
        normalized_classes = [normalize_class(c) for c in top_classes]
        class_counts = {}
        for norm_cls in normalized_classes:
            matched = False
            for existing in class_counts:
                if norm_cls in existing or existing in norm_cls:
                    class_counts[existing] += 1
                    matched = True
                    break
            if not matched:
                class_counts[norm_cls] = 1
        
        best_normalized = normalize_class(best_prediction.class_name)
        agreement_count = 1
        for existing, count in class_counts.items():
            if best_normalized in existing or existing in best_normalized:
                agreement_count = count
                break
        models_agree = agreement_count >= 2
        
        final_class = best_prediction.class_name
        final_confidence = best_prediction.confidence
        
        # Apply agreement boost
        if models_agree:
            # Boost confidence when models agree (cap at 95%)
            final_confidence = min(final_confidence + AGREEMENT_BOOST, 95.0)
            logger.info(f"Models agree on {final_class}, boosting confidence by {AGREEMENT_BOOST}%")
        
        return {
            "class_name": final_class,
            "class_index": best_prediction.class_index,
            "confidence": final_confidence,
            "method": "ensemble" if len(valid_predictions) > 1 else valid_predictions[0].model_name,
            "agreement": models_agree,
            "agreement_count": agreement_count,
            "models_used": [p.model_name for p in valid_predictions],
            "all_probabilities": best_prediction.all_probabilities,
            "top_3": best_prediction.top_3,
            "individual_predictions": [
                {
                    "model": p.model_name,
                    "class": p.class_name,
                    "confidence": round(p.confidence, 2)
                }
                for p in valid_predictions
            ]
        }
    
    def classify(self, image: Image.Image, crop_filter: Optional[str] = None) -> Dict[str, Any]:
        """
        Classify disease using multiple models and ensemble voting
        
        Args:
            image: PIL Image (should be verified as crop first)
            crop_filter: Optional crop name to filter predictions (e.g., "Rice", "Tomato")
            
        Returns:
            Complete classification result with ensemble details
        """
        try:
            # If crop_filter provided, use the primary PyTorch classifier with filtering
            # This is more accurate than ensemble when user specifies the crop
            if crop_filter:
                logger.info(f"Using crop-filtered classification for: {crop_filter}")
                from disease_classifier import DiseaseClassifier
                single_classifier = DiseaseClassifier()
                return single_classifier.classify(image, crop_filter=crop_filter)
            
            # Ensure RGB
            if image.mode != 'RGB':
                image = image.convert('RGB')
            
            # Collect predictions from all available models
            predictions: List[ModelPrediction] = []
            
            # PyTorch V1 model (primary - vipoooool dataset)
            pytorch_v1_pred = self._predict_pytorch(image, 'pytorch_v1')
            if pytorch_v1_pred:
                predictions.append(pytorch_v1_pred)
                logger.info(f"PyTorch V1: {pytorch_v1_pred.class_name} ({pytorch_v1_pred.confidence:.1f}%)")
            
            # PyTorch V2 model (secondary - rashidthihan dataset)
            pytorch_v2_pred = self._predict_pytorch(image, 'pytorch_v2')
            if pytorch_v2_pred:
                predictions.append(pytorch_v2_pred)
                logger.info(f"PyTorch V2: {pytorch_v2_pred.class_name} ({pytorch_v2_pred.confidence:.1f}%)")
            
            # Keras model (tertiary)
            keras_pred = self._predict_keras(image)
            if keras_pred:
                predictions.append(keras_pred)
                logger.info(f"Keras: {keras_pred.class_name} ({keras_pred.confidence:.1f}%)")
            
            # Color analysis (fallback - always run for cross-check)
            color_pred = self._predict_color_analysis(image)
            if color_pred:
                predictions.append(color_pred)
                logger.info(f"Color: {color_pred.class_name} ({color_pred.confidence:.1f}%)")
            
            # Ensemble predictions
            ensemble_result = self._ensemble_predictions(predictions)
            
            # Parse class name
            raw_class = ensemble_result["class_name"]
            crop, disease = self._parse_class_name(raw_class)
            confidence = ensemble_result["confidence"]
            
            # Apply confidence threshold
            if confidence < CONFIDENCE_THRESHOLD:
                disease = "Uncertain"
                logger.info(f"Ensemble confidence {confidence:.1f}% below threshold")
            
            # Check if healthy
            is_healthy = "healthy" in disease.lower() or "healthy" in raw_class.lower()
            
            # Get solution
            solution = self._get_solution(raw_class, disease, crop)
            
            result = {
                "status": "success",
                "crop": crop,
                "disease": disease,
                "disease_bn": solution.get("disease_bn", ""),
                "symptoms": solution.get("symptoms", "No specific symptoms available."),
                "symptoms_bn": solution.get("symptoms_bn", "নির্দিষ্ট লক্ষণ পাওয়া যায়নি।"),
                "confidence": round(confidence, 2),
                "is_healthy": is_healthy,
                "raw_class": raw_class,
                "ensemble_method": ensemble_result.get("method", "single"),
                "models_agree": ensemble_result.get("agreement", False),
                "models_used": ensemble_result.get("models_used", []),
                "individual_predictions": ensemble_result.get("individual_predictions", []),
                "solution": {
                    "chemical": solution.get("chemical", "Consult agricultural expert."),
                    "chemical_bn": solution.get("chemical_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
                    "organic": solution.get("organic", "Consult agricultural expert."),
                    "organic_bn": solution.get("organic_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
                    "prevention": solution.get("prevention", "Regular monitoring recommended."),
                    "prevention_bn": solution.get("prevention_bn", "নিয়মিত পর্যবেক্ষণ করুন।")
                },
                "top_3_predictions": ensemble_result.get("top_3", [])
            }
            
            logger.info(f"Ensemble classification: {crop} - {disease} ({confidence:.1f}%), "
                       f"agreement: {ensemble_result.get('agreement', False)}")
            
            return result
            
        except Exception as e:
            logger.error(f"Multi-model classification error: {e}")
            import traceback
            traceback.print_exc()
            return {
                "status": "error",
                "message": str(e),
                "crop": "Unknown",
                "disease": "Uncertain",
                "confidence": 0.0
            }
    
    def _parse_class_name(self, raw_class: str) -> Tuple[str, str]:
        """Parse class name into crop and disease"""
        if "___" in raw_class:
            parts = raw_class.split("___")
            crop = parts[0].replace("_", " ").strip()
            crop = crop.replace("( ", "(").replace(" )", ")")
            disease = parts[1].replace("_", " ").strip()
        elif "(" in raw_class and ")" in raw_class:
            parts = raw_class.split("(")
            disease = parts[0].strip()
            crop = parts[1].replace(")", "").strip()
        else:
            crop = "Unknown"
            disease = raw_class.replace("_", " ").strip()
        
        return crop, disease
    
    def _get_solution(self, raw_class: str, disease: str, crop: str) -> Dict[str, str]:
        """Get treatment solution from database"""
        # Try exact match
        if raw_class in self.solutions_db:
            return self.solutions_db[raw_class]
        
        # Try with crop___disease format
        full_key = f"{crop}___{disease}".replace(" ", "_")
        for key in self.solutions_db:
            if key.lower() == full_key.lower():
                return self.solutions_db[key]
        
        # Partial match
        disease_key = disease.replace(" ", "").replace("_", "").lower()
        for key, value in self.solutions_db.items():
            key_normalized = key.replace(" ", "").replace("_", "").lower()
            if disease_key in key_normalized or key_normalized in disease_key:
                if value.get("crop", "").lower() == crop.lower() or value.get("crop") == "Various":
                    return value
        
        # Fallback
        return self.solutions_db.get("Unknown", {
            "chemical": "Consult local agricultural expert.",
            "organic": "Collect samples and consult expert.",
            "prevention": "Regular monitoring recommended."
        })
    
    def _load_solutions_database(self) -> Dict[str, Dict]:
        """Load disease solutions database - imported from disease_classifier"""
        # Import from existing disease_classifier to avoid duplication
        try:
            from disease_classifier import DiseaseClassifier
            temp_classifier = DiseaseClassifier.__new__(DiseaseClassifier)
            return temp_classifier._load_solutions_database()
        except Exception as e:
            logger.warning(f"Could not import solutions database: {e}")
            return {
                "Unknown": {
                    "crop": "Unknown",
                    "disease": "Uncertain",
                    "disease_bn": "অনিশ্চিত",
                    "symptoms": "Unable to determine. Please provide a clearer image.",
                    "symptoms_bn": "নির্ধারণ করা সম্ভব হয়নি। পরিষ্কার ছবি দিন।",
                    "chemical": "Consult local agricultural extension office.",
                    "chemical_bn": "স্থানীয় কৃষি সম্প্রসারণ অফিসে পরামর্শ করুন।",
                    "organic": "Collect sample and consult expert.",
                    "organic_bn": "নমুনা সংগ্রহ করুন এবং বিশেষজ্ঞের পরামর্শ নিন।",
                    "prevention": "Monitor crop regularly.",
                    "prevention_bn": "নিয়মিত ফসল পর্যবেক্ষণ করুন।"
                }
            }
    
    def get_model_info(self) -> Dict[str, Any]:
        """Get information about loaded models"""
        return {
            "models_loaded": list(self.models.keys()),
            "num_models": len(self.models),
            "num_classes": len(self.class_names),
            "device": str(self.device),
            "models_info": [
                {
                    "key": key,
                    "name": info["name"],
                    "type": info["type"].value,
                    "weight": info["weight"]
                }
                for key, info in self.models.items()
            ]
        }


def test_classifier():
    """Test the multi-model classifier"""
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python multi_model_classifier.py <image_path>")
        return
    
    image_path = sys.argv[1]
    image = Image.open(image_path).convert('RGB')
    
    classifier = MultiModelClassifier()
    
    print("\n" + "=" * 60)
    print("MODEL INFO")
    print("=" * 60)
    print(json.dumps(classifier.get_model_info(), indent=2))
    
    print("\n" + "=" * 60)
    print("MULTI-MODEL CLASSIFICATION RESULT")
    print("=" * 60)
    
    result = classifier.classify(image)
    print(json.dumps(result, indent=2, ensure_ascii=False, default=str))


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_classifier()
