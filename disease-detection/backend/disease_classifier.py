"""
Disease Classifier Module
Stage 2: Classify crop disease from plant images

Workflow:
1. Load pre-trained disease classification model
2. Preprocess image
3. Run inference with softmax probabilities
4. Apply confidence threshold (60%)
5. Map to disease name and solutions
"""

import numpy as np
from PIL import Image
import json
import os
import logging
from typing import Dict, Any, List, Tuple, Optional

# PyTorch imports
import torch
import torch.nn as nn
from torchvision import transforms, models
from torchvision.models import EfficientNet_B0_Weights

logger = logging.getLogger(__name__)

# Configuration
CONFIDENCE_THRESHOLD = 60.0  # Minimum confidence for disease detection
INPUT_SHAPE = (224, 224)

# Device configuration
DEVICE = torch.device('cuda' if torch.cuda.is_available() else 'cpu')


class PlantDiseaseModel(nn.Module):
    """PyTorch model architecture matching the trained model"""
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


class DiseaseClassifier:
    """
    Stage 2: Disease Classification
    
    Uses:
    1. PyTorch model for disease detection (EfficientNet-B0)
    2. Softmax probabilities for confidence
    3. Solution database for recommendations
    """
    
    def __init__(self, model_path: str = None, class_names_path: str = None):
        """
        Initialize disease classifier
        
        Args:
            model_path: Path to PyTorch disease model (.pth)
            class_names_path: Path to class names JSON
        """
        self.model = None
        self.class_names = []
        self.solutions_db = self._load_solutions_database()
        self.device = DEVICE
        
        # Image transform (must match training)
        self.transform = transforms.Compose([
            transforms.Resize((INPUT_SHAPE[0], INPUT_SHAPE[1])),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
        ])
        
        # Default paths
        base_dir = os.path.dirname(__file__)
        if model_path is None:
            model_path = os.path.join(base_dir, "models", "disease_model_pytorch.pth")
        if class_names_path is None:
            class_names_path = os.path.join(base_dir, "models", "class_names.json")
        
        # Load class names
        self._load_class_names(class_names_path)
        
        # Load model
        self._load_model(model_path)
        
        logger.info(f"DiseaseClassifier initialized with {len(self.class_names)} classes")
    
    def _load_class_names(self, path: str):
        """Load class names from JSON file"""
        try:
            if os.path.exists(path):
                with open(path, 'r', encoding='utf-8') as f:
                    self.class_names = json.load(f)
                logger.info(f"Loaded {len(self.class_names)} class names")
            else:
                logger.warning(f"Class names file not found: {path}")
                self.class_names = []
        except Exception as e:
            logger.error(f"Error loading class names: {e}")
            self.class_names = []
    
    def _load_model(self, path: str):
        """Load PyTorch model"""
        try:
            if os.path.exists(path):
                # Load checkpoint
                checkpoint = torch.load(path, map_location=self.device, weights_only=False)
                
                # Get number of classes
                num_classes = len(self.class_names) if self.class_names else 38
                
                # Create model
                self.model = PlantDiseaseModel(num_classes)
                
                # Load weights
                if 'model_state_dict' in checkpoint:
                    self.model.load_state_dict(checkpoint['model_state_dict'])
                else:
                    self.model.load_state_dict(checkpoint)
                
                # Move to device and set to eval mode
                self.model.to(self.device)
                self.model.eval()
                
                logger.info(f"PyTorch disease model loaded: {path} (device: {self.device})")
            else:
                logger.warning(f"Model not found: {path}")
        except Exception as e:
            logger.error(f"Error loading PyTorch model: {e}")
            import traceback
            traceback.print_exc()
            self.model = None
    
    def _load_solutions_database(self) -> Dict[str, Dict]:
        """
        Load disease solutions database
        Contains chemical, organic treatments and prevention advice
        Covers all 38 classes from vipoooool/new-plant-diseases-dataset
        """
        return {
            # Apple Diseases
            "Apple___Apple_scab": {
                "crop": "Apple",
                "disease": "Apple Scab",
                "disease_bn": "আপেল স্ক্যাব",
                "symptoms": "Olive-green to brown velvety spots on leaves. Dark scabby lesions on fruits. Leaves may curl and drop early.",
                "symptoms_bn": "পাতায় জলপাই-সবুজ থেকে বাদামী মখমলের মতো দাগ। ফলে কালো খসখসে ক্ষত। পাতা কুঁকড়ে যেতে পারে এবং তাড়াতাড়ি ঝরে যেতে পারে।",
                "chemical": "Apply Captan 50% WP @ 2g/L or Myclobutanil @ 0.5ml/L. Spray every 7-10 days during wet periods.",
                "chemical_bn": "ক্যাপটান ৫০% WP @ ২ গ্রাম/লিটার অথবা মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার প্রয়োগ করুন। আর্দ্র সময়ে প্রতি ৭-১০ দিন অন্তর স্প্রে করুন।",
                "organic": "Remove fallen leaves. Apply sulfur-based fungicides. Use neem oil spray (5ml/L).",
                "organic_bn": "ঝরে পড়া পাতা সরিয়ে ফেলুন। সালফার-ভিত্তিক ছত্রাকনাশক প্রয়োগ করুন। নিম তেল স্প্রে (৫ মিলি/লিটার) ব্যবহার করুন।",
                "prevention": "Plant resistant varieties. Ensure good air circulation. Prune trees properly. Avoid overhead irrigation.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। ভালো বায়ু চলাচল নিশ্চিত করুন। গাছ সঠিকভাবে ছাঁটাই করুন। উপর থেকে সেচ এড়িয়ে চলুন।"
            },
            "Apple___Black_rot": {
                "crop": "Apple",
                "disease": "Black Rot",
                "disease_bn": "ব্ল্যাক রট",
                "symptoms": "Purple spots on leaves. Sunken black rot on fruits. Cankers on branches. Frog-eye leaf spot pattern.",
                "symptoms_bn": "পাতায় বেগুনি দাগ। ফলে ডুবে যাওয়া কালো পচন। ডালে ক্যাংকার। ব্যাঙের চোখের মতো পাতার দাগের প্যাটার্ন।",
                "chemical": "Apply Captan @ 2g/L or Thiophanate-methyl @ 1g/L. Remove mummified fruits before spraying.",
                "chemical_bn": "ক্যাপটান @ ২ গ্রাম/লিটার অথবা থায়োফ্যানেট-মিথাইল @ ১ গ্রাম/লিটার প্রয়োগ করুন। স্প্রে করার আগে শুকিয়ে যাওয়া ফল সরিয়ে ফেলুন।",
                "organic": "Prune dead branches. Remove infected fruits. Apply copper spray during dormant season.",
                "organic_bn": "মরা ডালপালা ছাঁটাই করুন। সংক্রমিত ফল সরিয়ে ফেলুন। সুপ্ত মৌসুমে কপার স্প্রে প্রয়োগ করুন।",
                "prevention": "Remove cankers and dead wood. Maintain tree vigor. Good sanitation practices.",
                "prevention_bn": "ক্যাংকার এবং মরা কাঠ সরিয়ে ফেলুন। গাছের শক্তি বজায় রাখুন। ভালো স্যানিটেশন অনুশীলন করুন।"
            },
            "Apple___Cedar_apple_rust": {
                "crop": "Apple",
                "disease": "Cedar Apple Rust",
                "disease_bn": "সিডার আপেল রাস্ট",
                "symptoms": "Bright orange-yellow spots on upper leaf surface. Tube-like projections on leaf undersides. Deformed fruits.",
                "symptoms_bn": "পাতার উপরের পৃষ্ঠে উজ্জ্বল কমলা-হলুদ দাগ। পাতার নিচে নলাকার প্রক্ষেপণ। বিকৃত ফল।",
                "chemical": "Apply Myclobutanil @ 0.5ml/L or Triadimefon @ 0.5ml/L from pink bud to petal fall.",
                "chemical_bn": "মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার অথবা ট্রায়াডিমেফন @ ০.৫ মিলি/লিটার গোলাপি কুঁড়ি থেকে পাপড়ি ঝরা পর্যন্ত প্রয়োগ করুন।",
                "organic": "Remove nearby juniper trees. Apply sulfur-based fungicides. Use resistant varieties.",
                "organic_bn": "কাছাকাছি জুনিপার গাছ সরিয়ে ফেলুন। সালফার-ভিত্তিক ছত্রাকনাশক প্রয়োগ করুন। প্রতিরোধী জাত ব্যবহার করুন।",
                "prevention": "Avoid planting near cedar/juniper trees. Plant resistant varieties. Regular monitoring.",
                "prevention_bn": "সিডার/জুনিপার গাছের কাছে রোপণ এড়িয়ে চলুন। প্রতিরোধী জাত রোপণ করুন। নিয়মিত পর্যবেক্ষণ করুন।"
            },
            "Apple___healthy": {
                "crop": "Apple",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Leaves are green and healthy. Normal growth pattern.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। পাতা সবুজ এবং সুস্থ। স্বাভাবিক বৃদ্ধির প্যাটার্ন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good agricultural practices. Apply compost for soil health.",
                "organic_bn": "ভালো কৃষি অনুশীলন বজায় রাখুন। মাটির স্বাস্থ্যের জন্য কম্পোস্ট প্রয়োগ করুন।",
                "prevention": "Regular inspection. Balanced nutrition. Proper pruning schedule.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম পুষ্টি। সঠিক ছাঁটাই সূচি।"
            },
            # Blueberry
            "Blueberry___healthy": {
                "crop": "Blueberry",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green foliage. Normal berry development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক বেরি বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain acidic soil pH (4.5-5.5). Apply organic mulch.",
                "organic_bn": "অম্লীয় মাটির pH (৪.৫-৫.৫) বজায় রাখুন। জৈব মালচ প্রয়োগ করুন।",
                "prevention": "Regular inspection. Proper watering. Maintain soil acidity.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সঠিক জল দেওয়া। মাটির অম্লতা বজায় রাখুন।"
            },
            # Cherry Diseases
            "Cherry_(including_sour)___Powdery_mildew": {
                "crop": "Cherry",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery coating on leaves. Curled and distorted leaves. Stunted shoot growth.",
                "symptoms_bn": "পাতায় সাদা গুঁড়ো আবরণ। কুঁকড়ানো এবং বিকৃত পাতা। বাধাগ্রস্ত কান্ড বৃদ্ধি।",
                "chemical": "Apply Sulfur 80% WP @ 3g/L or Myclobutanil @ 0.5ml/L. Repeat every 10-14 days.",
                "chemical_bn": "সালফার ৮০% WP @ ৩ গ্রাম/লিটার অথবা মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার প্রয়োগ করুন। প্রতি ১০-১৪ দিন পুনরাবৃত্তি করুন।",
                "organic": "Spray milk solution (1:9). Apply potassium bicarbonate @ 5g/L. Use neem oil.",
                "organic_bn": "দুধের দ্রবণ (১:৯) স্প্রে করুন। পটাসিয়াম বাইকার্বনেট @ ৫ গ্রাম/লিটার প্রয়োগ করুন। নিম তেল ব্যবহার করুন।",
                "prevention": "Improve air circulation. Avoid overhead irrigation. Plant resistant varieties.",
                "prevention_bn": "বায়ু চলাচল উন্নত করুন। উপর থেকে সেচ এড়িয়ে চলুন। প্রতিরোধী জাত রোপণ করুন।"
            },
            "Cherry_(including_sour)___healthy": {
                "crop": "Cherry",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good agricultural practices. Proper pruning.",
                "organic_bn": "ভালো কৃষি অনুশীলন বজায় রাখুন। সঠিক ছাঁটাই।",
                "prevention": "Regular inspection. Balanced fertilization. Proper water management.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম সার প্রয়োগ। সঠিক পানি ব্যবস্থাপনা।"
            },
            # Corn (Maize) Diseases
            "Corn_(maize)___Cercospora_leaf_spot Gray_leaf_spot": {
                "crop": "Corn",
                "disease": "Gray Leaf Spot",
                "disease_bn": "গ্রে লিফ স্পট",
                "symptoms": "Rectangular gray to tan lesions on leaves. Lesions run parallel to leaf veins. Lower leaves affected first.",
                "symptoms_bn": "পাতায় আয়তাকার ধূসর থেকে বাদামী ক্ষত। ক্ষত পাতার শিরার সমান্তরালে চলে। প্রথমে নিচের পাতা আক্রান্ত হয়।",
                "chemical": "Apply Azoxystrobin @ 1ml/L or Pyraclostrobin @ 0.5ml/L at first sign of disease.",
                "chemical_bn": "রোগের প্রথম লক্ষণে অ্যাজোক্সিস্ট্রোবিন @ ১ মিলি/লিটার অথবা পাইরাক্লোস্ট্রোবিন @ ০.৫ মিলি/লিটার প্রয়োগ করুন।",
                "organic": "Practice crop rotation. Remove crop debris. Use resistant hybrids.",
                "organic_bn": "শস্য আবর্তন অনুশীলন করুন। ফসলের অবশিষ্টাংশ সরিয়ে ফেলুন। প্রতিরোধী হাইব্রিড ব্যবহার করুন।",
                "prevention": "Plant resistant varieties. Avoid continuous corn planting. Tillage to bury residue.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। ধারাবাহিক ভুট্টা রোপণ এড়িয়ে চলুন। অবশিষ্টাংশ মাটিতে মিশিয়ে দিন।"
            },
            "Corn_(maize)___Common_rust_": {
                "crop": "Corn",
                "disease": "Common Rust",
                "disease_bn": "কমন রাস্ট",
                "symptoms": "Reddish-brown pustules on both leaf surfaces. Pustules rupture releasing powdery spores. Leaves may turn yellow.",
                "symptoms_bn": "পাতার উভয় পৃষ্ঠে লালচে-বাদামী পাস্টুল। পাস্টুল ফেটে গুঁড়ো স্পোর বের হয়। পাতা হলুদ হতে পারে।",
                "chemical": "Apply Mancozeb @ 2.5g/L or Propiconazole @ 1ml/L when pustules first appear.",
                "chemical_bn": "পাস্টুল প্রথম দেখা দিলে ম্যানকোজেব @ ২.৫ গ্রাম/লিটার অথবা প্রোপিকোনাজল @ ১ মিলি/লিটার প্রয়োগ করুন।",
                "organic": "Plant early to avoid peak infection periods. Use resistant varieties.",
                "organic_bn": "সর্বোচ্চ সংক্রমণ সময় এড়াতে তাড়াতাড়ি রোপণ করুন। প্রতিরোধী জাত ব্যবহার করুন।",
                "prevention": "Plant resistant hybrids. Scout fields regularly. Timely planting.",
                "prevention_bn": "প্রতিরোধী হাইব্রিড রোপণ করুন। নিয়মিত মাঠ পরিদর্শন করুন। সময়মত রোপণ।"
            },
            "Corn_(maize)___Northern_Leaf_Blight": {
                "crop": "Corn",
                "disease": "Northern Leaf Blight",
                "disease_bn": "নর্দার্ন লিফ ব্লাইট",
                "symptoms": "Long cigar-shaped gray-green lesions on leaves. Lesions turn tan as they mature. Severe cases cause leaf death.",
                "symptoms_bn": "পাতায় লম্বা সিগারের আকৃতির ধূসর-সবুজ ক্ষত। পরিপক্ক হলে ক্ষত বাদামী হয়ে যায়। গুরুতর ক্ষেত্রে পাতা মারা যায়।",
                "chemical": "Apply Azoxystrobin @ 1ml/L or Trifloxystrobin @ 0.5ml/L at tasseling if disease is present.",
                "chemical_bn": "রোগ থাকলে ফুল আসার সময় অ্যাজোক্সিস্ট্রোবিন @ ১ মিলি/লিটার অথবা ট্রাইফ্লক্সিস্ট্রোবিন @ ০.৫ মিলি/লিটার প্রয়োগ করুন।",
                "organic": "Crop rotation with non-host crops. Remove infected debris. Use resistant varieties.",
                "organic_bn": "অ-আতিথেয় ফসলের সাথে শস্য আবর্তন করুন। সংক্রমিত অবশিষ্টাংশ সরিয়ে ফেলুন। প্রতিরোধী জাত ব্যবহার করুন।",
                "prevention": "Plant resistant hybrids. Rotate crops. Manage crop residue.",
                "prevention_bn": "প্রতিরোধী হাইব্রিড রোপণ করুন। শস্য আবর্তন করুন। ফসলের অবশিষ্টাংশ ব্যবস্থাপনা করুন।"
            },
            "Corn_(maize)___healthy": {
                "crop": "Corn",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal tassel and ear development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফুল এবং মোচা বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good soil health. Balanced fertilization.",
                "organic_bn": "ভালো মাটির স্বাস্থ্য বজায় রাখুন। সুষম সার প্রয়োগ।",
                "prevention": "Regular scouting. Proper plant spacing. Good drainage.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সঠিক গাছের দূরত্ব। ভালো নিষ্কাশন।"
            },
            # Grape Diseases
            "Grape___Black_rot": {
                "crop": "Grape",
                "disease": "Black Rot",
                "disease_bn": "ব্ল্যাক রট",
                "symptoms": "Brown circular spots on leaves with dark margins. Berries shrivel and turn black (mummies). Cankers on shoots.",
                "symptoms_bn": "পাতায় গাঢ় প্রান্তযুক্ত বাদামী গোলাকার দাগ। বেরি কুঁকড়ে কালো হয়ে যায় (মামি)। কান্ডে ক্যাংকার।",
                "chemical": "Apply Mancozeb @ 2g/L or Myclobutanil @ 0.5ml/L starting at bud break. Repeat every 7-14 days.",
                "chemical_bn": "কুঁড়ি ভাঙার সময় ম্যানকোজেব @ ২ গ্রাম/লিটার অথবা মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার প্রয়োগ করুন। প্রতি ৭-১৪ দিন পুনরাবৃত্তি করুন।",
                "organic": "Remove mummified berries. Apply copper-based fungicides. Improve air circulation.",
                "organic_bn": "শুকিয়ে যাওয়া বেরি সরিয়ে ফেলুন। কপার-ভিত্তিক ছত্রাকনাশক প্রয়োগ করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Prune properly for air circulation. Remove infected material. Clean cultivation.",
                "prevention_bn": "বায়ু চলাচলের জন্য সঠিকভাবে ছাঁটাই করুন। সংক্রমিত উপাদান সরিয়ে ফেলুন। পরিচ্ছন্ন চাষ।"
            },
            "Grape___Esca_(Black_Measles)": {
                "crop": "Grape",
                "disease": "Esca (Black Measles)",
                "disease_bn": "এসকা (ব্ল্যাক মিজলস)",
                "symptoms": "Tiger-stripe pattern on leaves. Dark spots on berries. Internal wood decay. Sudden vine collapse.",
                "symptoms_bn": "পাতায় বাঘের ডোরার মতো প্যাটার্ন। বেরিতে কালো দাগ। অভ্যন্তরীণ কাঠ পচন। হঠাৎ লতা ভেঙে পড়া।",
                "chemical": "No effective chemical control. Remove severely infected vines. Protect pruning wounds.",
                "chemical_bn": "কার্যকর রাসায়নিক নিয়ন্ত্রণ নেই। মারাত্মকভাবে সংক্রমিত লতা সরিয়ে ফেলুন। ছাঁটাই ক্ষত রক্ষা করুন।",
                "organic": "Apply Trichoderma to pruning wounds. Remove and burn infected wood.",
                "organic_bn": "ছাঁটাই ক্ষতে ট্রাইকোডার্মা প্রয়োগ করুন। সংক্রমিত কাঠ সরিয়ে পুড়িয়ে ফেলুন।",
                "prevention": "Protect pruning wounds. Use clean pruning tools. Avoid large pruning cuts.",
                "prevention_bn": "ছাঁটাই ক্ষত রক্ষা করুন। পরিষ্কার ছাঁটাই সরঞ্জাম ব্যবহার করুন। বড় ছাঁটাই কাট এড়িয়ে চলুন।"
            },
            "Grape___Leaf_blight_(Isariopsis_Leaf_Spot)": {
                "crop": "Grape",
                "disease": "Leaf Blight (Isariopsis Leaf Spot)",
                "disease_bn": "লিফ ব্লাইট",
                "symptoms": "Irregular brown spots on leaves. Spots may have yellow halos. Premature leaf drop. Reduced fruit quality.",
                "symptoms_bn": "পাতায় অনিয়মিত বাদামী দাগ। দাগের চারপাশে হলুদ বলয় থাকতে পারে। অকাল পাতা ঝরা। ফলের মান কমে যাওয়া।",
                "chemical": "Apply Mancozeb @ 2g/L or Copper oxychloride @ 3g/L. Spray at 10-14 day intervals.",
                "chemical_bn": "ম্যানকোজেব @ ২ গ্রাম/লিটার অথবা কপার অক্সিক্লোরাইড @ ৩ গ্রাম/লিটার প্রয়োগ করুন। ১০-১৪ দিন অন্তর স্প্রে করুন।",
                "organic": "Remove infected leaves. Apply neem oil. Improve ventilation.",
                "organic_bn": "সংক্রমিত পাতা সরিয়ে ফেলুন। নিম তেল প্রয়োগ করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Proper spacing. Good air circulation. Remove infected plant parts.",
                "prevention_bn": "সঠিক দূরত্ব। ভালো বায়ু চলাচল। সংক্রমিত গাছের অংশ সরিয়ে ফেলুন।"
            },
            "Grape___healthy": {
                "crop": "Grape",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good vineyard practices. Proper pruning.",
                "organic_bn": "ভালো আঙ্গুর বাগান অনুশীলন বজায় রাখুন। সঠিক ছাঁটাই।",
                "prevention": "Regular inspection. Balanced nutrition. Good canopy management.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম পুষ্টি। ভালো ছাউনি ব্যবস্থাপনা।"
            },
            # Orange
            "Orange___Haunglongbing_(Citrus_greening)": {
                "crop": "Orange",
                "disease": "Huanglongbing (Citrus Greening)",
                "disease_bn": "সাইট্রাস গ্রিনিং",
                "symptoms": "Yellow shoots. Blotchy mottle on leaves. Lopsided bitter fruits. Small misshapen seeds. Tree decline.",
                "symptoms_bn": "হলুদ কান্ড। পাতায় ছোপযুক্ত দাগ। একপাশে তেতো ফল। ছোট বিকৃত বীজ। গাছের ক্ষয়।",
                "chemical": "Control psyllid vectors with Imidacloprid @ 0.5ml/L. No cure for infected trees.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার দিয়ে সাইলিড ভেক্টর নিয়ন্ত্রণ করুন। সংক্রমিত গাছের কোনো নিরাময় নেই।",
                "organic": "Remove infected trees immediately. Control psyllid with neem oil. Use reflective mulch.",
                "organic_bn": "অবিলম্বে সংক্রমিত গাছ সরিয়ে ফেলুন। নিম তেল দিয়ে সাইলিড নিয়ন্ত্রণ করুন। প্রতিফলনশীল মালচ ব্যবহার করুন।",
                "prevention": "Use certified disease-free nursery stock. Control Asian citrus psyllid. Regular scouting.",
                "prevention_bn": "প্রত্যয়িত রোগমুক্ত নার্সারি স্টক ব্যবহার করুন। এশিয়ান সাইট্রাস সাইলিড নিয়ন্ত্রণ করুন। নিয়মিত পরিদর্শন।"
            },
            # Peach Diseases
            "Peach___Bacterial_spot": {
                "crop": "Peach",
                "disease": "Bacterial Spot",
                "disease_bn": "ব্যাকটেরিয়াল স্পট",
                "symptoms": "Small dark spots on leaves. Spots fall out creating shot-holes. Sunken lesions on fruits. Cracked fruit surface.",
                "symptoms_bn": "পাতায় ছোট কালো দাগ। দাগ পড়ে গিয়ে গুলির মতো ছিদ্র তৈরি হয়। ফলে ডুবে যাওয়া ক্ষত। ফাটা ফলের পৃষ্ঠ।",
                "chemical": "Apply Copper hydroxide @ 2g/L + Mancozeb @ 2g/L. Start at petal fall.",
                "chemical_bn": "কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার + ম্যানকোজেব @ ২ গ্রাম/লিটার প্রয়োগ করুন। পাপড়ি ঝরার সময় শুরু করুন।",
                "organic": "Remove infected twigs. Apply copper spray during dormant season.",
                "organic_bn": "সংক্রমিত ডালপালা সরিয়ে ফেলুন। সুপ্ত মৌসুমে কপার স্প্রে প্রয়োগ করুন।",
                "prevention": "Plant resistant varieties. Avoid overhead irrigation. Proper tree spacing.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। উপর থেকে সেচ এড়িয়ে চলুন। সঠিক গাছের দূরত্ব।"
            },
            "Peach___healthy": {
                "crop": "Peach",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good orchard practices. Proper pruning.",
                "organic_bn": "ভালো বাগান অনুশীলন বজায় রাখুন। সঠিক ছাঁটাই।",
                "prevention": "Regular inspection. Balanced fertilization. Good drainage.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম সার প্রয়োগ। ভালো নিষ্কাশন।"
            },
            # Pepper Diseases
            "Pepper,_bell___Bacterial_spot": {
                "crop": "Pepper",
                "disease": "Bacterial Spot",
                "disease_bn": "ব্যাকটেরিয়াল স্পট",
                "symptoms": "Water-soaked spots on leaves. Raised scab-like lesions on fruits. Leaf yellowing and drop. Reduced yield.",
                "symptoms_bn": "পাতায় পানিতে ভেজা দাগ। ফলে উঁচু খোসপাঁচড়ার মতো ক্ষত। পাতা হলুদ হওয়া এবং ঝরে পড়া। ফলন কমে যাওয়া।",
                "chemical": "Apply Copper hydroxide @ 2g/L + Mancozeb @ 2g/L every 7-10 days.",
                "chemical_bn": "প্রতি ৭-১০ দিন অন্তর কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার + ম্যানকোজেব @ ২ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Use disease-free seeds. Apply Bacillus subtilis. Remove infected plants.",
                "organic_bn": "রোগমুক্ত বীজ ব্যবহার করুন। ব্যাসিলাস সাবটিলিস প্রয়োগ করুন। সংক্রমিত গাছ সরিয়ে ফেলুন।",
                "prevention": "Avoid overhead irrigation. Use drip irrigation. Rotate crops 2-3 years.",
                "prevention_bn": "উপর থেকে সেচ এড়িয়ে চলুন। ড্রিপ সেচ ব্যবহার করুন। ২-৩ বছর শস্য আবর্তন করুন।"
            },
            "Pepper,_bell___healthy": {
                "crop": "Pepper",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good agricultural practices. Proper plant spacing.",
                "organic_bn": "ভালো কৃষি অনুশীলন বজায় রাখুন। সঠিক গাছের দূরত্ব।",
                "prevention": "Regular inspection. Balanced nutrition. Avoid overwatering.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম পুষ্টি। অতিরিক্ত পানি দেওয়া এড়িয়ে চলুন।"
            },
            # Potato Diseases
            "Potato___Early_blight": {
                "crop": "Potato",
                "disease": "Early Blight",
                "disease_bn": "আর্লি ব্লাইট",
                "symptoms": "Dark brown spots with concentric rings (target pattern). Lower leaves affected first. Yellowing around spots.",
                "symptoms_bn": "কেন্দ্রীভূত বলয়সহ গাঢ় বাদামী দাগ (টার্গেট প্যাটার্ন)। প্রথমে নিচের পাতা আক্রান্ত হয়। দাগের চারপাশে হলুদ হওয়া।",
                "chemical": "Apply Chlorothalonil @ 2g/L or Mancozeb @ 2.5g/L every 7-10 days.",
                "chemical_bn": "প্রতি ৭-১০ দিন অন্তর ক্লোরোথালোনিল @ ২ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove infected leaves. Apply copper-based fungicide. Use Trichoderma harzianum.",
                "organic_bn": "সংক্রমিত পাতা সরিয়ে ফেলুন। কপার-ভিত্তিক ছত্রাকনাশক প্রয়োগ করুন। ট্রাইকোডার্মা হার্জিয়ানাম ব্যবহার করুন।",
                "prevention": "Use certified seed potatoes. Avoid overhead irrigation. Maintain 3-year crop rotation.",
                "prevention_bn": "প্রত্যয়িত বীজ আলু ব্যবহার করুন। উপর থেকে সেচ এড়িয়ে চলুন। ৩ বছরের শস্য আবর্তন বজায় রাখুন।"
            },
            "Potato___Late_blight": {
                "crop": "Potato",
                "disease": "Late Blight",
                "disease_bn": "লেট ব্লাইট",
                "symptoms": "Water-soaked pale green spots. White mold on leaf undersides. Rapidly spreading brown lesions. Tuber rot.",
                "symptoms_bn": "পানিতে ভেজা হালকা সবুজ দাগ। পাতার নিচে সাদা ছত্রাক। দ্রুত ছড়িয়ে পড়া বাদামী ক্ষত। কন্দ পচা।",
                "chemical": "Apply Metalaxyl + Mancozeb @ 2.5g/L or Cymoxanil + Mancozeb @ 2g/L immediately.",
                "chemical_bn": "অবিলম্বে মেটালাক্সিল + ম্যানকোজেব @ ২.৫ গ্রাম/লিটার অথবা সাইমোক্সানিল + ম্যানকোজেব @ ২ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove and destroy infected plants. Apply copper hydroxide. Improve air circulation.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ধ্বংস করুন। কপার হাইড্রক্সাইড প্রয়োগ করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Use resistant varieties. Avoid planting near infected fields. Monitor weather.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। সংক্রমিত মাঠের কাছে রোপণ এড়িয়ে চলুন। আবহাওয়া পর্যবেক্ষণ করুন।"
            },
            "Potato___healthy": {
                "crop": "Potato",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green foliage. Normal tuber development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক কন্দ বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good soil health. Hill plants properly.",
                "organic_bn": "ভালো মাটির স্বাস্থ্য বজায় রাখুন। গাছ সঠিকভাবে মাটি দিন।",
                "prevention": "Use certified seed. Regular scouting. Proper water management.",
                "prevention_bn": "প্রত্যয়িত বীজ ব্যবহার করুন। নিয়মিত পরিদর্শন। সঠিক পানি ব্যবস্থাপনা।"
            },
            # Raspberry
            "Raspberry___healthy": {
                "crop": "Raspberry",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green canes and leaves. Normal berry production.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ ডাল এবং পাতা। স্বাভাবিক বেরি উৎপাদন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good cane management. Proper mulching.",
                "organic_bn": "ভালো ডাল ব্যবস্থাপনা বজায় রাখুন। সঠিক মালচিং।",
                "prevention": "Regular inspection. Prune old canes. Good air circulation.",
                "prevention_bn": "নিয়মিত পরিদর্শন। পুরানো ডাল ছাঁটাই করুন। ভালো বায়ু চলাচল।"
            },
            # Soybean
            "Soybean___healthy": {
                "crop": "Soybean",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal pod development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক শুঁটি বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Practice crop rotation. Maintain soil health.",
                "organic_bn": "শস্য আবর্তন অনুশীলন করুন। মাটির স্বাস্থ্য বজায় রাখুন।",
                "prevention": "Regular scouting. Proper plant population. Balanced fertilization.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সঠিক গাছের সংখ্যা। সুষম সার প্রয়োগ।"
            },
            # Squash
            "Squash___Powdery_mildew": {
                "crop": "Squash",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery patches on leaves. Yellowing and browning of leaves. Stunted growth. Reduced fruit quality.",
                "symptoms_bn": "পাতায় সাদা গুঁড়ো দাগ। পাতা হলুদ এবং বাদামী হওয়া। বৃদ্ধি ব্যাহত। ফলের মান কমে যাওয়া।",
                "chemical": "Apply Sulfur 80% WP @ 3g/L or Myclobutanil @ 0.5ml/L every 7-14 days.",
                "chemical_bn": "প্রতি ৭-১৪ দিন অন্তর সালফার ৮০% WP @ ৩ গ্রাম/লিটার অথবা মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার প্রয়োগ করুন।",
                "organic": "Spray milk solution (1:9). Apply potassium bicarbonate. Use neem oil.",
                "organic_bn": "দুধের দ্রবণ (১:৯) স্প্রে করুন। পটাসিয়াম বাইকার্বনেট প্রয়োগ করুন। নিম তেল ব্যবহার করুন।",
                "prevention": "Plant resistant varieties. Proper spacing. Avoid overhead irrigation.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। সঠিক দূরত্ব। উপর থেকে সেচ এড়িয়ে চলুন।"
            },
            # Strawberry Diseases
            "Strawberry___Leaf_scorch": {
                "crop": "Strawberry",
                "disease": "Leaf Scorch",
                "disease_bn": "লিফ স্কর্চ",
                "symptoms": "Purple or red spots on leaves. Spots enlarge and merge. Leaf margins turn brown. Reduced plant vigor.",
                "symptoms_bn": "পাতায় বেগুনি বা লাল দাগ। দাগ বড় হয় এবং মিশে যায়। পাতার প্রান্ত বাদামী হয়ে যায়। গাছের শক্তি কমে যায়।",
                "chemical": "Apply Copper hydroxide @ 2g/L or Captan @ 2g/L after harvest and before bloom.",
                "chemical_bn": "ফসল তোলার পর এবং ফুল ফোটার আগে কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার অথবা ক্যাপটান @ ২ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove infected leaves. Apply Trichoderma. Improve air circulation.",
                "organic_bn": "সংক্রমিত পাতা সরিয়ে ফেলুন। ট্রাইকোডার্মা প্রয়োগ করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Use certified disease-free plants. Proper plant spacing. Avoid overhead irrigation.",
                "prevention_bn": "প্রত্যয়িত রোগমুক্ত চারা ব্যবহার করুন। সঠিক গাছের দূরত্ব। উপর থেকে সেচ এড়িয়ে চলুন।"
            },
            "Strawberry___healthy": {
                "crop": "Strawberry",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit production.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল উৎপাদন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain mulch. Proper plant spacing.",
                "organic_bn": "মালচ বজায় রাখুন। সঠিক গাছের দূরত্ব।",
                "prevention": "Regular inspection. Renovate beds after harvest. Good drainage.",
                "prevention_bn": "নিয়মিত পরিদর্শন। ফসল তোলার পর বেড নবায়ন করুন। ভালো নিষ্কাশন।"
            },
            # Tomato Diseases
            "Tomato___Bacterial_spot": {
                "crop": "Tomato",
                "disease": "Bacterial Spot",
                "disease_bn": "ব্যাকটেরিয়াল স্পট",
                "symptoms": "Small dark raised spots on leaves. Spots on fruit become scabby. Leaf yellowing. Defoliation in severe cases.",
                "symptoms_bn": "পাতায় ছোট কালো উঁচু দাগ। ফলে দাগ খসখসে হয়ে যায়। পাতা হলুদ হওয়া। গুরুতর ক্ষেত্রে পাতা ঝরা।",
                "chemical": "Spray Copper hydroxide @ 2g/L + Mancozeb @ 2g/L every 7 days.",
                "chemical_bn": "প্রতি ৭ দিন অন্তর কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার + ম্যানকোজেব @ ২ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Use disease-free seeds. Apply Bacillus subtilis. Remove infected plants.",
                "organic_bn": "রোগমুক্ত বীজ ব্যবহার করুন। ব্যাসিলাস সাবটিলিস প্রয়োগ করুন। সংক্রমিত গাছ সরিয়ে ফেলুন।",
                "prevention": "Avoid overhead irrigation. Use drip irrigation. Disinfect tools.",
                "prevention_bn": "উপর থেকে সেচ এড়িয়ে চলুন। ড্রিপ সেচ ব্যবহার করুন। সরঞ্জাম জীবাণুমুক্ত করুন।"
            },
            "Tomato___Early_blight": {
                "crop": "Tomato",
                "disease": "Early Blight",
                "disease_bn": "আর্লি ব্লাইট",
                "symptoms": "Dark brown spots with concentric rings. Lower leaves affected first. Yellowing around lesions. Fruit stem-end rot.",
                "symptoms_bn": "কেন্দ্রীভূত বলয়সহ গাঢ় বাদামী দাগ। প্রথমে নিচের পাতা আক্রান্ত। ক্ষতের চারপাশে হলুদ হওয়া। ফলের বোঁটার দিকে পচন।",
                "chemical": "Apply Chlorothalonil @ 2g/L or Mancozeb @ 2.5g/L every 7-10 days.",
                "chemical_bn": "প্রতি ৭-১০ দিন অন্তর ক্লোরোথালোনিল @ ২ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove lower infected leaves. Apply copper spray. Mulch around plants.",
                "organic_bn": "নিচের সংক্রমিত পাতা সরিয়ে ফেলুন। কপার স্প্রে প্রয়োগ করুন। গাছের চারপাশে মালচ দিন।",
                "prevention": "Stake plants. Proper spacing. Rotate crops 3 years.",
                "prevention_bn": "গাছে খুঁটি দিন। সঠিক দূরত্ব। ৩ বছর শস্য আবর্তন করুন।"
            },
            "Tomato___Late_blight": {
                "crop": "Tomato",
                "disease": "Late Blight",
                "disease_bn": "লেট ব্লাইট",
                "symptoms": "Water-soaked dark spots on leaves. White fuzzy mold on leaf undersides. Brown firm lesions on fruit. Rapid plant death.",
                "symptoms_bn": "পাতায় পানিতে ভেজা কালো দাগ। পাতার নিচে সাদা তুলতুলে ছত্রাক। ফলে বাদামী শক্ত ক্ষত। দ্রুত গাছ মারা যাওয়া।",
                "chemical": "Apply Metalaxyl + Mancozeb @ 2.5g/L immediately. Repeat every 5-7 days in wet weather.",
                "chemical_bn": "অবিলম্বে মেটালাক্সিল + ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন। আর্দ্র আবহাওয়ায় প্রতি ৫-৭ দিন পুনরাবৃত্তি করুন।",
                "organic": "Remove and destroy infected plants. Apply copper fungicide.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ধ্বংস করুন। কপার ছত্রাকনাশক প্রয়োগ করুন।",
                "prevention": "Use resistant varieties. Avoid overhead irrigation. Monitor weather forecasts.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। উপর থেকে সেচ এড়িয়ে চলুন। আবহাওয়ার পূর্বাভাস পর্যবেক্ষণ করুন।"
            },
            "Tomato___Leaf_Mold": {
                "crop": "Tomato",
                "disease": "Leaf Mold",
                "disease_bn": "লিফ মোল্ড",
                "symptoms": "Pale green to yellow spots on upper leaf surface. Olive-green velvety mold on leaf undersides. Leaf curling and death.",
                "symptoms_bn": "পাতার উপরিভাগে হালকা সবুজ থেকে হলুদ দাগ। পাতার নিচে জলপাই-সবুজ মখমলের মতো ছত্রাক। পাতা কুঁকড়ানো এবং মারা যাওয়া।",
                "chemical": "Apply Chlorothalonil @ 2g/L or Mancozeb @ 2.5g/L. Spray lower leaf surfaces.",
                "chemical_bn": "ক্লোরোথালোনিল @ ২ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন। পাতার নিচের পৃষ্ঠে স্প্রে করুন।",
                "organic": "Improve ventilation. Remove lower leaves. Apply baking soda solution.",
                "organic_bn": "বায়ু চলাচল উন্নত করুন। নিচের পাতা সরিয়ে ফেলুন। বেকিং সোডা দ্রবণ প্রয়োগ করুন।",
                "prevention": "Maintain humidity below 85%. Use resistant varieties. Proper spacing.",
                "prevention_bn": "আর্দ্রতা ৮৫% এর নিচে রাখুন। প্রতিরোধী জাত ব্যবহার করুন। সঠিক দূরত্ব।"
            },
            "Tomato___Septoria_leaf_spot": {
                "crop": "Tomato",
                "disease": "Septoria Leaf Spot",
                "disease_bn": "সেপ্টোরিয়া লিফ স্পট",
                "symptoms": "Small circular spots with dark borders and gray centers. Black specks in spot centers. Lower leaves affected first.",
                "symptoms_bn": "গাঢ় প্রান্ত এবং ধূসর কেন্দ্রযুক্ত ছোট গোলাকার দাগ। দাগের কেন্দ্রে কালো ফোঁটা। প্রথমে নিচের পাতা আক্রান্ত।",
                "chemical": "Apply Chlorothalonil @ 2g/L or Mancozeb @ 2.5g/L at first sign of disease.",
                "chemical_bn": "রোগের প্রথম লক্ষণে ক্লোরোথালোনিল @ ২ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove infected lower leaves. Apply copper fungicide. Mulch soil surface.",
                "organic_bn": "সংক্রমিত নিচের পাতা সরিয়ে ফেলুন। কপার ছত্রাকনাশক প্রয়োগ করুন। মাটির উপরিভাগে মালচ দিন।",
                "prevention": "Stake plants. Proper spacing. Avoid working when plants are wet.",
                "prevention_bn": "গাছে খুঁটি দিন। সঠিক দূরত্ব। গাছ ভেজা থাকলে কাজ এড়িয়ে চলুন।"
            },
            "Tomato___Spider_mites Two-spotted_spider_mite": {
                "crop": "Tomato",
                "disease": "Spider Mites",
                "disease_bn": "স্পাইডার মাইট",
                "symptoms": "Yellow stippling on leaves. Fine webbing on undersides. Bronzed leaves. Leaf drop in severe cases.",
                "symptoms_bn": "পাতায় হলুদ ফোঁটা। নিচে সূক্ষ্ম জাল। ব্রোঞ্জ রঙের পাতা। গুরুতর ক্ষেত্রে পাতা ঝরা।",
                "chemical": "Apply Abamectin @ 0.5ml/L or Spiromesifen @ 1ml/L. Repeat in 5-7 days.",
                "chemical_bn": "অ্যাবামেক্টিন @ ০.৫ মিলি/লিটার অথবা স্পিরোমেসিফেন @ ১ মিলি/লিটার প্রয়োগ করুন। ৫-৭ দিন পর পুনরাবৃত্তি করুন।",
                "organic": "Spray with strong water jet. Apply neem oil @ 5ml/L. Release predatory mites.",
                "organic_bn": "শক্তিশালী পানির স্প্রে করুন। নিম তেল @ ৫ মিলি/লিটার প্রয়োগ করুন। শিকারী মাইট ছেড়ে দিন।",
                "prevention": "Monitor regularly. Maintain plant health. Avoid water stress.",
                "prevention_bn": "নিয়মিত পর্যবেক্ষণ করুন। গাছের স্বাস্থ্য বজায় রাখুন। পানির চাপ এড়িয়ে চলুন।"
            },
            "Tomato___Target_Spot": {
                "crop": "Tomato",
                "disease": "Target Spot",
                "disease_bn": "টার্গেট স্পট",
                "symptoms": "Brown spots with concentric rings on leaves. Spots on stems and fruit. Defoliation. Reduced yield.",
                "symptoms_bn": "পাতায় কেন্দ্রীভূত বলয়সহ বাদামী দাগ। কান্ড এবং ফলে দাগ। পাতা ঝরা। ফলন কমে যাওয়া।",
                "chemical": "Apply Azoxystrobin @ 1ml/L or Difenoconazole @ 0.5ml/L. Rotate fungicides.",
                "chemical_bn": "অ্যাজোক্সিস্ট্রোবিন @ ১ মিলি/লিটার অথবা ডাইফেনোকোনাজল @ ০.৫ মিলি/লিটার প্রয়োগ করুন। ছত্রাকনাশক পরিবর্তন করুন।",
                "organic": "Remove infected debris. Apply Trichoderma. Use copper spray.",
                "organic_bn": "সংক্রমিত অবশিষ্টাংশ সরিয়ে ফেলুন। ট্রাইকোডার্মা প্রয়োগ করুন। কপার স্প্রে ব্যবহার করুন।",
                "prevention": "Avoid wetting foliage. Mulch around plants. Practice crop rotation.",
                "prevention_bn": "পাতা ভেজানো এড়িয়ে চলুন। গাছের চারপাশে মালচ দিন। শস্য আবর্তন অনুশীলন করুন।"
            },
            "Tomato___Tomato_Yellow_Leaf_Curl_Virus": {
                "crop": "Tomato",
                "disease": "Yellow Leaf Curl Virus",
                "disease_bn": "ইয়েলো লিফ কার্ল ভাইরাস",
                "symptoms": "Upward curling of leaf margins. Yellow edges on leaves. Stunted plant growth. Reduced fruit size.",
                "symptoms_bn": "পাতার প্রান্ত উপরের দিকে কুঁকড়ানো। পাতায় হলুদ প্রান্ত। গাছের বৃদ্ধি স্থবির। ফলের আকার কমে যাওয়া।",
                "chemical": "Control whitefly vectors with Imidacloprid @ 0.5ml/L. No cure for infected plants.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার দিয়ে সাদামাছি ভেক্টর নিয়ন্ত্রণ করুন। সংক্রমিত গাছের কোনো নিরাময় নেই।",
                "organic": "Remove infected plants. Use yellow sticky traps. Apply neem oil for whitefly.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ফেলুন। হলুদ আঠালো ফাঁদ ব্যবহার করুন। সাদামাছির জন্য নিম তেল প্রয়োগ করুন।",
                "prevention": "Use resistant varieties. Control whiteflies. Use reflective mulch.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। সাদামাছি নিয়ন্ত্রণ করুন। প্রতিফলনশীল মালচ ব্যবহার করুন।"
            },
            "Tomato___Tomato_mosaic_virus": {
                "crop": "Tomato",
                "disease": "Tomato Mosaic Virus",
                "disease_bn": "টমেটো মোজাইক ভাইরাস",
                "symptoms": "Mottled light and dark green leaves. Leaf distortion. Stunted growth. Reduced fruit quality.",
                "symptoms_bn": "হালকা এবং গাঢ় সবুজ মিশ্রিত পাতা। পাতার বিকৃতি। স্থবির বৃদ্ধি। ফলের গুণমান কমে যাওয়া।",
                "chemical": "No chemical cure. Disinfect tools with 10% bleach solution.",
                "chemical_bn": "কোনো রাসায়নিক নিরাময় নেই। ১০% ব্লিচ দ্রবণ দিয়ে সরঞ্জাম জীবাণুমুক্ত করুন।",
                "organic": "Remove and destroy infected plants. Wash hands with milk before handling plants.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ধ্বংস করুন। গাছ ধরার আগে দুধ দিয়ে হাত ধুয়ে নিন।",
                "prevention": "Use virus-free seeds. Disinfect tools. Don't smoke near plants.",
                "prevention_bn": "ভাইরাসমুক্ত বীজ ব্যবহার করুন। সরঞ্জাম জীবাণুমুক্ত করুন। গাছের কাছে ধূমপান করবেন না।"
            },
            "Tomato___healthy": {
                "crop": "Tomato",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit production.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল উৎপাদন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good practices. Proper staking and mulching.",
                "organic_bn": "ভালো অনুশীলন বজায় রাখুন। সঠিক খুঁটি এবং মালচিং।",
                "prevention": "Regular inspection. Balanced nutrition. Proper water management.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম পুষ্টি। সঠিক পানি ব্যবস্থাপনা।"
            },
            # Generic entries for backward compatibility
            "Healthy": {
                "crop": "Various",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Plant appears normal and vigorous.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। গাছ স্বাভাবিক এবং সতেজ দেখাচ্ছে।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good agricultural practices. Apply compost for soil health.",
                "organic_bn": "ভালো কৃষি অনুশীলন বজায় রাখুন। মাটির স্বাস্থ্যের জন্য কম্পোস্ট প্রয়োগ করুন।",
                "prevention": "Regular field inspection. Balanced nutrition. Proper water management.",
                "prevention_bn": "নিয়মিত মাঠ পরিদর্শন। সুষম পুষ্টি। সঠিক পানি ব্যবস্থাপনা।"
            },
            "PowderyMildew": {
                "crop": "Various",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery coating on leaves. Curled and distorted leaves. Premature leaf drop.",
                "symptoms_bn": "পাতায় সাদা পাউডারের আবরণ। কুঁকড়ানো এবং বিকৃত পাতা। অকাল পাতা ঝরা।",
                "chemical": "Apply Sulfur 80% WP @ 3g/L or Hexaconazole @ 1ml/L. Repeat every 10 days.",
                "chemical_bn": "সালফার ৮০% WP @ ৩ গ্রাম/লিটার অথবা হেক্সাকোনাজল @ ১ মিলি/লিটার প্রয়োগ করুন। প্রতি ১০ দিন পুনরাবৃত্তি করুন।",
                "organic": "Spray milk solution (1:9). Apply potassium bicarbonate. Use neem oil.",
                "organic_bn": "দুধের দ্রবণ (১:৯) স্প্রে করুন। পটাসিয়াম বাইকার্বনেট প্রয়োগ করুন। নিম তেল ব্যবহার করুন।",
                "prevention": "Improve air circulation. Avoid overcrowding. Water at soil level.",
                "prevention_bn": "বায়ু চলাচল উন্নত করুন। অতিরিক্ত ঘনত্ব এড়িয়ে চলুন। মাটির স্তরে পানি দিন।"
            },
            # Cotton Diseases
            "BacterialBlight": {
                "crop": "Cotton",
                "disease": "Bacterial Blight",
                "disease_bn": "ব্যাকটেরিয়াল ব্লাইট",
                "symptoms": "Angular water-soaked spots on leaves. Black lesions on stems. Boll rot. Wilting.",
                "symptoms_bn": "পাতায় কোণাকার পানিতে ভেজা দাগ। কান্ডে কালো ক্ষত। বলের পচন। ঢলে পড়া।",
                "chemical": "Spray Streptocycline @ 0.5g/L + Copper oxychloride @ 2.5g/L.",
                "chemical_bn": "স্ট্রেপ্টোসাইক্লিন @ ০.৫ গ্রাম/লিটার + কপার অক্সিক্লোরাইড @ ২.৫ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Use disease-free seeds. Apply Pseudomonas fluorescens. Remove infected plants.",
                "organic_bn": "রোগমুক্ত বীজ ব্যবহার করুন। সিউডোমোনাস ফ্লুরোসেন্স প্রয়োগ করুন। সংক্রমিত গাছ সরিয়ে ফেলুন।",
                "prevention": "Seed treatment with antibiotics. Avoid field work when wet. Crop rotation.",
                "prevention_bn": "অ্যান্টিবায়োটিক দিয়ে বীজ শোধন। ভেজা অবস্থায় মাঠে কাজ এড়িয়ে চলুন। শস্য আবর্তন।"
            },
            # Sugarcane Diseases  
            "RedRot": {
                "crop": "Sugarcane",
                "disease": "Red Rot",
                "disease_bn": "রেড রট",
                "symptoms": "Yellowing and wilting of leaves. Red discoloration in stalk pith. White spots in red tissue. Stalk drying.",
                "symptoms_bn": "পাতা হলুদ এবং ঢলে পড়া। কান্ডের মজ্জায় লাল বিবর্ণতা। লাল টিস্যুতে সাদা দাগ। কান্ড শুকানো।",
                "chemical": "Treat setts with Carbendazim @ 0.1% for 15 minutes before planting.",
                "chemical_bn": "রোপণের আগে ১৫ মিনিটের জন্য কার্বেন্ডাজিম @ ০.১% দিয়ে চারা শোধন করুন।",
                "organic": "Use disease-free setts. Apply Trichoderma. Practice hot water treatment.",
                "organic_bn": "রোগমুক্ত চারা ব্যবহার করুন। ট্রাইকোডার্মা প্রয়োগ করুন। গরম পানি শোধন অনুশীলন করুন।",
                "prevention": "Use resistant varieties. Remove infected clumps. Avoid waterlogging.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। সংক্রমিত ঝাড় সরিয়ে ফেলুন। জলাবদ্ধতা এড়িয়ে চলুন।"
            },
            "Mosaic": {
                "crop": "Sugarcane",
                "disease": "Mosaic Virus",
                "disease_bn": "মোজাইক ভাইরাস",
                "symptoms": "Light and dark green mottled pattern on leaves. Stunted growth. Reduced tillering. Poor cane quality.",
                "symptoms_bn": "পাতায় হালকা এবং গাঢ় সবুজ মিশ্রিত প্যাটার্ন। স্থবির বৃদ্ধি। কম কুশি। দুর্বল আখের মান।",
                "chemical": "Control aphid vectors with Imidacloprid @ 0.5ml/L.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার দিয়ে জাব পোকা ভেক্টর নিয়ন্ত্রণ করুন।",
                "organic": "Remove infected plants. Control weeds. Use virus-free planting material.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ফেলুন। আগাছা নিয়ন্ত্রণ করুন। ভাইরাসমুক্ত রোপণ উপাদান ব্যবহার করুন।",
                "prevention": "Use resistant varieties. Eliminate weed hosts. Rogue infected plants.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। আগাছা আতিথেয় দূর করুন। সংক্রমিত গাছ উপড়ে ফেলুন।"
            },
            # Rice Diseases (V2 model - rashidthihan dataset)
            "BrownSpot (Rice)": {
                "crop": "Rice",
                "disease": "Brown Spot",
                "disease_bn": "বাদামী দাগ রোগ",
                "symptoms": "Oval brown spots with gray centers on leaves. Spots may have yellow halos. Severe infection causes leaf drying. Can affect grains causing brown discoloration.",
                "symptoms_bn": "পাতায় ধূসর কেন্দ্রসহ ডিম্বাকার বাদামী দাগ। দাগের চারপাশে হলুদ বলয় থাকতে পারে। তীব্র সংক্রমণে পাতা শুকিয়ে যায়। শস্যেও বাদামী বিবর্ণতা দেখা দিতে পারে।",
                "chemical": "Spray Mancozeb @ 2.5g/L or Carbendazim @ 1g/L at 15-day intervals. Apply Tricyclazole @ 0.6g/L for severe cases.",
                "chemical_bn": "১৫ দিন অন্তর ম্যানকোজেব @ ২.৫ গ্রাম/লিটার অথবা কার্বেন্ডাজিম @ ১ গ্রাম/লিটার স্প্রে করুন। তীব্র ক্ষেত্রে ট্রাইসাইক্লাজল @ ০.৬ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Apply Trichoderma viride @ 5g/L. Use Pseudomonas fluorescens. Maintain proper nutrition especially potassium.",
                "organic_bn": "ট্রাইকোডার্মা ভিরিডি @ ৫ গ্রাম/লিটার প্রয়োগ করুন। সিউডোমোনাস ফ্লুরোসেন্স ব্যবহার করুন। বিশেষত পটাসিয়াম সহ সঠিক পুষ্টি বজায় রাখুন।",
                "prevention": "Use certified disease-free seeds. Treat seeds with fungicide. Maintain balanced NPK fertilization. Avoid water stress.",
                "prevention_bn": "প্রত্যয়িত রোগমুক্ত বীজ ব্যবহার করুন। ছত্রাকনাশক দিয়ে বীজ শোধন করুন। সুষম NPK সার প্রয়োগ করুন। পানির চাপ এড়িয়ে চলুন।"
            },
            "Hispa (Rice)": {
                "crop": "Rice",
                "disease": "Rice Hispa",
                "disease_bn": "ধানের হিস্পা পোকা",
                "symptoms": "White parallel streaks on leaves caused by larval tunneling. Adults scrape upper leaf surface. Severe damage causes white patches and leaf drying.",
                "symptoms_bn": "লার্ভার সুড়ঙ্গ করার কারণে পাতায় সাদা সমান্তরাল রেখা। পূর্ণবয়স্ক পোকা পাতার উপরের পৃষ্ঠ ছিঁড়ে খায়। তীব্র ক্ষতিতে সাদা দাগ এবং পাতা শুকিয়ে যায়।",
                "chemical": "Spray Chlorpyrifos @ 2ml/L or Quinalphos @ 2ml/L. Apply Cartap hydrochloride @ 1g/L for severe infestation.",
                "chemical_bn": "ক্লোরপাইরিফস @ ২ মিলি/লিটার অথবা কুইনালফস @ ২ মিলি/লিটার স্প্রে করুন। তীব্র আক্রমণে কার্টাপ হাইড্রোক্লোরাইড @ ১ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Collect and destroy adults by hand. Clip infested leaf tips. Use light traps. Release Trichogramma parasitoids.",
                "organic_bn": "হাতে পূর্ণবয়স্ক পোকা সংগ্রহ করে ধ্বংস করুন। আক্রান্ত পাতার ডগা কেটে ফেলুন। আলোক ফাঁদ ব্যবহার করুন। ট্রাইকোগ্রামা পরজীবী ছাড়ুন।",
                "prevention": "Avoid excess nitrogen fertilization. Maintain proper water level. Remove grassy weeds. Early planting.",
                "prevention_bn": "অতিরিক্ত নাইট্রোজেন সার এড়িয়ে চলুন। সঠিক পানির স্তর বজায় রাখুন। ঘাস জাতীয় আগাছা সরিয়ে ফেলুন। তাড়াতাড়ি রোপণ করুন।"
            },
            "LeafBlast (Rice)": {
                "crop": "Rice",
                "disease": "Leaf Blast",
                "disease_bn": "পাতা ব্লাস্ট রোগ",
                "symptoms": "Diamond-shaped spots with gray centers and brown margins on leaves. Spots may enlarge and merge. Severe infection can kill seedlings.",
                "symptoms_bn": "পাতায় ধূসর কেন্দ্র এবং বাদামী প্রান্তসহ হীরা আকৃতির দাগ। দাগ বড় হয়ে মিশে যেতে পারে। তীব্র সংক্রমণে চারা মারা যেতে পারে।",
                "chemical": "Apply Tricyclazole @ 0.6g/L or Isoprothiolane @ 1.5ml/L at disease appearance. Repeat after 10-15 days.",
                "chemical_bn": "রোগ দেখা দিলে ট্রাইসাইক্লাজল @ ০.৬ গ্রাম/লিটার অথবা আইসোপ্রোথিওলেন @ ১.৫ মিলি/লিটার প্রয়োগ করুন। ১০-১৫ দিন পর পুনরাবৃত্তি করুন।",
                "organic": "Apply Pseudomonas fluorescens @ 10g/L. Use Trichoderma harzianum. Silicon application improves resistance.",
                "organic_bn": "সিউডোমোনাস ফ্লুরোসেন্স @ ১০ গ্রাম/লিটার প্রয়োগ করুন। ট্রাইকোডার্মা হার্জিয়ানাম ব্যবহার করুন। সিলিকন প্রয়োগে প্রতিরোধ ক্ষমতা বাড়ে।",
                "prevention": "Use resistant varieties. Avoid excess nitrogen. Maintain proper spacing. Treat seeds with fungicide.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। অতিরিক্ত নাইট্রোজেন এড়িয়ে চলুন। সঠিক দূরত্ব বজায় রাখুন। ছত্রাকনাশক দিয়ে বীজ শোধন করুন।"
            },
            "Healthy (Rice)": {
                "crop": "Rice",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal tillering and growth.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক কুশি এবং বৃদ্ধি।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good agricultural practices. Apply organic fertilizers.",
                "organic_bn": "ভালো কৃষি অনুশীলন বজায় রাখুন। জৈব সার প্রয়োগ করুন।",
                "prevention": "Regular inspection. Proper water management. Balanced fertilization.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সঠিক পানি ব্যবস্থাপনা। সুষম সার প্রয়োগ।"
            },
            # Mango Diseases (V2 model)
            "Anthracnose (Mango)": {
                "crop": "Mango",
                "disease": "Anthracnose",
                "disease_bn": "অ্যানথ্রাকনোজ",
                "symptoms": "Black sunken spots on leaves and fruits. Blossom blight. Fruit rot during storage. Twig dieback.",
                "symptoms_bn": "পাতা ও ফলে কালো ডুবে যাওয়া দাগ। ফুলের ব্লাইট। সংরক্ষণকালে ফল পচা। ডালের মরে যাওয়া।",
                "chemical": "Spray Carbendazim @ 1g/L or Mancozeb @ 2.5g/L before and after flowering. Copper oxychloride @ 3g/L post-harvest.",
                "chemical_bn": "ফুল আসার আগে ও পরে কার্বেন্ডাজিম @ ১ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার স্প্রে করুন। ফসল তোলার পর কপার অক্সিক্লোরাইড @ ৩ গ্রাম/লিটার।",
                "organic": "Apply Trichoderma. Use neem oil @ 5ml/L. Hot water treatment of fruits at 52°C for 5 minutes.",
                "organic_bn": "ট্রাইকোডার্মা প্রয়োগ করুন। নিম তেল @ ৫ মিলি/লিটার ব্যবহার করুন। ৫২°C তাপমাত্রায় ৫ মিনিট ফলের গরম পানি শোধন।",
                "prevention": "Prune dead twigs. Improve air circulation. Avoid overhead irrigation. Harvest during dry weather.",
                "prevention_bn": "মরা ডালপালা ছাঁটাই করুন। বায়ু চলাচল উন্নত করুন। উপর থেকে সেচ এড়িয়ে চলুন। শুষ্ক আবহাওয়ায় ফসল তুলুন।"
            },
            "Powdery Mildew (Mango)": {
                "crop": "Mango",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery growth on panicles, leaves, and young fruits. Flower drop. Fruit drop. Malformed fruits.",
                "symptoms_bn": "মুকুল, পাতা এবং কচি ফলে সাদা গুঁড়ো বৃদ্ধি। ফুল ঝরে যাওয়া। ফল ঝরে যাওয়া। বিকৃত ফল।",
                "chemical": "Spray Sulfur 80% WP @ 2g/L or Hexaconazole @ 1ml/L at panicle emergence and repeat after 15 days.",
                "chemical_bn": "মুকুল বের হলে সালফার ৮০% WP @ ২ গ্রাম/লিটার অথবা হেক্সাকোনাজল @ ১ মিলি/লিটার স্প্রে করুন এবং ১৫ দিন পর পুনরাবৃত্তি করুন।",
                "organic": "Spray neem oil @ 5ml/L. Apply milk spray (1:9). Potassium bicarbonate @ 5g/L.",
                "organic_bn": "নিম তেল @ ৫ মিলি/লিটার স্প্রে করুন। দুধের স্প্রে (১:৯) প্রয়োগ করুন। পটাসিয়াম বাইকার্বনেট @ ৫ গ্রাম/লিটার।",
                "prevention": "Avoid excess nitrogen. Improve tree spacing. Prune for better air circulation.",
                "prevention_bn": "অতিরিক্ত নাইট্রোজেন এড়িয়ে চলুন। গাছের দূরত্ব উন্নত করুন। ভালো বায়ু চলাচলের জন্য ছাঁটাই করুন।"
            },
            "Healthy (Mango)": {
                "crop": "Mango",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal flowering and fruiting.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফুল ও ফলন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good orchard hygiene. Apply organic manure.",
                "organic_bn": "ভালো বাগান স্বাস্থ্যবিধি বজায় রাখুন। জৈব সার প্রয়োগ করুন।",
                "prevention": "Regular pruning. Proper nutrition. Pest monitoring.",
                "prevention_bn": "নিয়মিত ছাঁটাই। সঠিক পুষ্টি। পোকা পর্যবেক্ষণ।"
            },
            # Pumpkin Diseases (V2 model)
            "Powdery Mildew (Pumpkin)": {
                "crop": "Pumpkin",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery spots on leaves and stems. Yellowing of leaves. Premature leaf drop. Reduced fruit size.",
                "symptoms_bn": "পাতা ও কান্ডে সাদা গুঁড়ো দাগ। পাতা হলুদ হওয়া। অকালে পাতা ঝরে যাওয়া। ফলের আকার কমে যাওয়া।",
                "chemical": "Spray Sulfur 80% WP @ 3g/L or Myclobutanil @ 0.5ml/L. Repeat every 7-10 days.",
                "chemical_bn": "সালফার ৮০% WP @ ৩ গ্রাম/লিটার অথবা মাইক্লোবিউটানিল @ ০.৫ মিলি/লিটার স্প্রে করুন। প্রতি ৭-১০ দিন পুনরাবৃত্তি করুন।",
                "organic": "Apply milk spray (1:9). Use potassium bicarbonate @ 5g/L. Neem oil @ 5ml/L.",
                "organic_bn": "দুধের স্প্রে (১:৯) প্রয়োগ করুন। পটাসিয়াম বাইকার্বনেট @ ৫ গ্রাম/লিটার ব্যবহার করুন। নিম তেল @ ৫ মিলি/লিটার।",
                "prevention": "Plant resistant varieties. Ensure proper spacing. Avoid overhead irrigation.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। সঠিক দূরত্ব নিশ্চিত করুন। উপর থেকে সেচ এড়িয়ে চলুন।"
            },
            "Healthy Leaf (Pumpkin)": {
                "crop": "Pumpkin",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal vine growth and fruiting.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক লতা বৃদ্ধি এবং ফলন।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain good soil health. Apply compost.",
                "organic_bn": "ভালো মাটির স্বাস্থ্য বজায় রাখুন। কম্পোস্ট প্রয়োগ করুন।",
                "prevention": "Regular inspection. Proper watering. Balanced fertilization.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সঠিক পানি দেওয়া। সুষম সার প্রয়োগ।"
            },
            # Jackfruit Diseases (V2 model - rashidthihan dataset)
            "Algal_Leaf_Spot___Jackfruit": {
                "crop": "Jackfruit",
                "disease": "Algal Leaf Spot",
                "disease_bn": "অ্যালগাল লিফ স্পট",
                "symptoms": "Raised, greenish-gray spots on leaves. Orange-red spore masses when mature. Circular to irregular spots.",
                "symptoms_bn": "পাতায় উঁচু, সবুজ-ধূসর দাগ। পরিপক্ক হলে কমলা-লাল স্পোর। গোলাকার থেকে অনিয়মিত দাগ।",
                "chemical": "Spray Copper hydroxide @ 2g/L or Copper oxychloride @ 3g/L. Apply before monsoon.",
                "chemical_bn": "কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার অথবা কপার অক্সিক্লোরাইড @ ৩ গ্রাম/লিটার স্প্রে করুন। বর্ষার আগে প্রয়োগ করুন।",
                "organic": "Improve air circulation by pruning. Remove affected leaves. Apply neem oil.",
                "organic_bn": "ছাঁটাই করে বায়ু চলাচল উন্নত করুন। আক্রান্ত পাতা সরিয়ে ফেলুন। নিম তেল প্রয়োগ করুন।",
                "prevention": "Avoid overhead irrigation. Proper tree spacing. Remove shading vegetation.",
                "prevention_bn": "উপর থেকে সেচ এড়িয়ে চলুন। সঠিক গাছের দূরত্ব। ছায়া দেওয়া গাছপালা সরিয়ে ফেলুন।"
            },
            "Black_Spot___Jackfruit": {
                "crop": "Jackfruit",
                "disease": "Black Spot",
                "disease_bn": "কালো দাগ রোগ",
                "symptoms": "Black circular spots on leaves and fruits. Spots may coalesce. Premature fruit drop.",
                "symptoms_bn": "পাতা ও ফলে কালো গোলাকার দাগ। দাগ একত্রিত হতে পারে। অকালে ফল ঝরে যাওয়া।",
                "chemical": "Apply Carbendazim @ 1g/L or Mancozeb @ 2.5g/L at 15-day intervals.",
                "chemical_bn": "১৫ দিন অন্তর কার্বেন্ডাজিম @ ১ গ্রাম/লিটার অথবা ম্যানকোজেব @ ২.৫ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Prune infected parts. Apply Trichoderma. Use neem cake as soil amendment.",
                "organic_bn": "সংক্রমিত অংশ ছাঁটাই করুন। ট্রাইকোডার্মা প্রয়োগ করুন। মাটি সংশোধনে নিম খৈল ব্যবহার করুন।",
                "prevention": "Remove fallen debris. Good drainage. Balanced fertilization.",
                "prevention_bn": "পড়ে যাওয়া অবশিষ্টাংশ সরিয়ে ফেলুন। ভালো নিষ্কাশন। সুষম সার প্রয়োগ।"
            },
            "Jackfruit___healthy": {
                "crop": "Jackfruit",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal fruit development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক ফল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Apply organic manure. Maintain mulch around tree base.",
                "organic_bn": "জৈব সার প্রয়োগ করুন। গাছের গোড়ায় মালচ বজায় রাখুন।",
                "prevention": "Regular pruning. Proper nutrition. Pest monitoring.",
                "prevention_bn": "নিয়মিত ছাঁটাই। সঠিক পুষ্টি। পোকা পর্যবেক্ষণ।"
            },
            # Cauliflower Diseases (V2 model)
            "Black_Rot___Cauliflower": {
                "crop": "Cauliflower",
                "disease": "Black Rot",
                "disease_bn": "কালো পচা রোগ",
                "symptoms": "V-shaped yellow lesions from leaf margins. Veins turn black. Head rot with foul smell.",
                "symptoms_bn": "পাতার প্রান্ত থেকে V-আকৃতির হলুদ ক্ষত। শিরা কালো হয়ে যায়। দুর্গন্ধসহ মাথা পচন।",
                "chemical": "Seed treatment with Streptocycline @ 0.1g/L. Spray Copper hydroxide @ 2g/L.",
                "chemical_bn": "স্ট্রেপ্টোসাইক্লিন @ ০.১ গ্রাম/লিটার দিয়ে বীজ শোধন। কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Use disease-free seeds. Hot water seed treatment at 50°C for 20 minutes.",
                "organic_bn": "রোগমুক্ত বীজ ব্যবহার করুন। ৫০°C তাপমাত্রায় ২০ মিনিট গরম পানিতে বীজ শোধন।",
                "prevention": "Crop rotation for 2-3 years. Remove crop debris. Avoid overhead irrigation.",
                "prevention_bn": "২-৩ বছর শস্য আবর্তন। ফসলের অবশিষ্টাংশ সরিয়ে ফেলুন। উপর থেকে সেচ এড়িয়ে চলুন।"
            },
            "Cauliflower___healthy": {
                "crop": "Cauliflower",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal head development.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক মাথা বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain soil health with compost. Proper water management.",
                "organic_bn": "কম্পোস্ট দিয়ে মাটির স্বাস্থ্য বজায় রাখুন। সঠিক পানি ব্যবস্থাপনা।",
                "prevention": "Regular inspection. Balanced fertilization. Good drainage.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম সার প্রয়োগ। ভালো নিষ্কাশন।"
            },
            # Additional Cotton entries (V2 model)
            "Aphids___Cotton": {
                "crop": "Cotton",
                "disease": "Aphids",
                "disease_bn": "জাব পোকা",
                "symptoms": "Curled and distorted leaves. Sticky honeydew on leaves. Sooty mold growth. Stunted growth.",
                "symptoms_bn": "কুঁকড়ানো এবং বিকৃত পাতা। পাতায় আঠালো মধু। কালো ছত্রাক বৃদ্ধি। স্থবির বৃদ্ধি।",
                "chemical": "Spray Imidacloprid @ 0.5ml/L or Thiamethoxam @ 0.2g/L.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার অথবা থায়ামিথক্সাম @ ০.২ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Spray neem oil @ 5ml/L. Release ladybird beetles. Use yellow sticky traps.",
                "organic_bn": "নিম তেল @ ৫ মিলি/লিটার স্প্রে করুন। লেডিবার্ড বিটল ছাড়ুন। হলুদ আঠালো ফাঁদ ব্যবহার করুন।",
                "prevention": "Avoid excess nitrogen. Monitor regularly. Maintain field hygiene.",
                "prevention_bn": "অতিরিক্ত নাইট্রোজেন এড়িয়ে চলুন। নিয়মিত পর্যবেক্ষণ করুন। মাঠের পরিচ্ছন্নতা বজায় রাখুন।"
            },
            "Powdery_Mildew___Cotton": {
                "crop": "Cotton",
                "disease": "Powdery Mildew",
                "disease_bn": "পাউডারি মিলডিউ",
                "symptoms": "White powdery coating on leaves. Yellowing and premature leaf drop. Reduced boll formation.",
                "symptoms_bn": "পাতায় সাদা গুঁড়ো আবরণ। হলুদ হওয়া এবং অকালে পাতা ঝরা। বল গঠন কমে যাওয়া।",
                "chemical": "Spray Sulfur 80% WP @ 3g/L or Hexaconazole @ 1ml/L at 10-day intervals.",
                "chemical_bn": "১০ দিন অন্তর সালফার ৮০% WP @ ৩ গ্রাম/লিটার অথবা হেক্সাকোনাজল @ ১ মিলি/লিটার স্প্রে করুন।",
                "organic": "Apply potassium bicarbonate @ 5g/L. Spray neem oil. Milk spray (1:9).",
                "organic_bn": "পটাসিয়াম বাইকার্বনেট @ ৫ গ্রাম/লিটার প্রয়োগ করুন। নিম তেল স্প্রে করুন। দুধের স্প্রে (১:৯)।",
                "prevention": "Proper plant spacing. Avoid excess nitrogen. Good air circulation.",
                "prevention_bn": "সঠিক গাছের দূরত্ব। অতিরিক্ত নাইট্রোজেন এড়িয়ে চলুন। ভালো বায়ু চলাচল।"
            },
            "Target_Spot___Cotton": {
                "crop": "Cotton",
                "disease": "Target Spot",
                "disease_bn": "টার্গেট স্পট",
                "symptoms": "Circular spots with concentric rings on leaves. Spots may coalesce. Premature defoliation.",
                "symptoms_bn": "পাতায় কেন্দ্রীভূত বলয়সহ গোলাকার দাগ। দাগ একত্রিত হতে পারে। অকালে পাতা ঝরা।",
                "chemical": "Apply Mancozeb @ 2.5g/L or Carbendazim @ 1g/L at 10-15 day intervals.",
                "chemical_bn": "১০-১৫ দিন অন্তর ম্যানকোজেব @ ২.৫ গ্রাম/লিটার অথবা কার্বেন্ডাজিম @ ১ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Remove infected leaves. Apply Trichoderma. Improve air circulation.",
                "organic_bn": "সংক্রমিত পাতা সরিয়ে ফেলুন। ট্রাইকোডার্মা প্রয়োগ করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Crop rotation. Avoid waterlogging. Remove crop debris.",
                "prevention_bn": "শস্য আবর্তন। জলাবদ্ধতা এড়িয়ে চলুন। ফসলের অবশিষ্টাংশ সরিয়ে ফেলুন।"
            },
            "Cotton___healthy": {
                "crop": "Cotton",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease or pest symptoms. Healthy green leaves. Normal boll development.",
                "symptoms_bn": "কোনো রোগ বা পোকার লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক বল বিকাশ।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Maintain soil health. Apply organic manure.",
                "organic_bn": "মাটির স্বাস্থ্য বজায় রাখুন। জৈব সার প্রয়োগ করুন।",
                "prevention": "Regular scouting. IPM practices. Balanced nutrition.",
                "prevention_bn": "নিয়মিত পরিদর্শন। IPM অনুশীলন। সুষম পুষ্টি।"
            },
            # Additional Sugarcane entries (V2 model)
            "Bacterial_Blights___Sugarcane": {
                "crop": "Sugarcane",
                "disease": "Bacterial Blight",
                "disease_bn": "ব্যাকটেরিয়াল ব্লাইট",
                "symptoms": "White to cream stripes on leaves. Leaf tips dry up. Stunted growth. Reduced juice quality.",
                "symptoms_bn": "পাতায় সাদা থেকে ক্রিম রঙের ডোরা। পাতার ডগা শুকিয়ে যাওয়া। স্থবির বৃদ্ধি। রসের মান কমে যাওয়া।",
                "chemical": "Sett treatment with Streptocycline @ 0.5g/L for 30 minutes. Spray Copper oxychloride @ 3g/L.",
                "chemical_bn": "৩০ মিনিটের জন্য স্ট্রেপ্টোসাইক্লিন @ ০.৫ গ্রাম/লিটার দিয়ে চারা শোধন। কপার অক্সিক্লোরাইড @ ৩ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Use disease-free setts. Hot water treatment at 50°C for 2 hours.",
                "organic_bn": "রোগমুক্ত চারা ব্যবহার করুন। ৫০°C তাপমাত্রায় ২ ঘন্টা গরম পানি শোধন।",
                "prevention": "Use resistant varieties. Remove infected plants. Avoid waterlogging.",
                "prevention_bn": "প্রতিরোধী জাত ব্যবহার করুন। সংক্রমিত গাছ সরিয়ে ফেলুন। জলাবদ্ধতা এড়িয়ে চলুন।"
            },
            "Rust___Sugarcane": {
                "crop": "Sugarcane",
                "disease": "Rust",
                "disease_bn": "মরিচা রোগ",
                "symptoms": "Orange-brown pustules on leaves. Pustules release powdery spores. Severe infection causes leaf drying.",
                "symptoms_bn": "পাতায় কমলা-বাদামী পাস্টুল। পাস্টুল থেকে গুঁড়ো স্পোর বের হয়। তীব্র সংক্রমণে পাতা শুকিয়ে যায়।",
                "chemical": "Spray Mancozeb @ 2.5g/L or Propiconazole @ 1ml/L at 15-day intervals.",
                "chemical_bn": "১৫ দিন অন্তর ম্যানকোজেব @ ২.৫ গ্রাম/লিটার অথবা প্রোপিকোনাজল @ ১ মিলি/লিটার স্প্রে করুন।",
                "organic": "Remove infected leaves. Apply Trichoderma. Use resistant varieties.",
                "organic_bn": "সংক্রমিত পাতা সরিয়ে ফেলুন। ট্রাইকোডার্মা প্রয়োগ করুন। প্রতিরোধী জাত ব্যবহার করুন।",
                "prevention": "Plant resistant varieties. Avoid excess nitrogen. Maintain field hygiene.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। অতিরিক্ত নাইট্রোজেন এড়িয়ে চলুন। মাঠের পরিচ্ছন্নতা বজায় রাখুন।"
            },
            "Yellow___Sugarcane": {
                "crop": "Sugarcane",
                "disease": "Yellow Leaf Disease",
                "disease_bn": "হলুদ পাতা রোগ",
                "symptoms": "Yellowing of midrib followed by entire leaf. Stunted growth. Reduced sugar content.",
                "symptoms_bn": "মধ্যশিরা হলুদ হওয়ার পর পুরো পাতা হলুদ। স্থবির বৃদ্ধি। চিনির পরিমাণ কমে যাওয়া।",
                "chemical": "Control aphid vectors with Imidacloprid @ 0.5ml/L. No cure for infected plants.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার দিয়ে জাব পোকা ভেক্টর নিয়ন্ত্রণ করুন। সংক্রমিত গাছের কোনো নিরাময় নেই।",
                "organic": "Remove infected plants. Use virus-free setts. Control aphids with neem oil.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ফেলুন। ভাইরাসমুক্ত চারা ব্যবহার করুন। নিম তেল দিয়ে জাব পোকা নিয়ন্ত্রণ করুন।",
                "prevention": "Use certified disease-free setts. Rogue infected plants. Control vectors.",
                "prevention_bn": "প্রত্যয়িত রোগমুক্ত চারা ব্যবহার করুন। সংক্রমিত গাছ উপড়ে ফেলুন। ভেক্টর নিয়ন্ত্রণ করুন।"
            },
            "Sugarcane___healthy": {
                "crop": "Sugarcane",
                "disease": "Healthy",
                "disease_bn": "সুস্থ",
                "symptoms": "No disease symptoms. Healthy green leaves. Normal cane growth.",
                "symptoms_bn": "কোনো রোগের লক্ষণ নেই। সুস্থ সবুজ পাতা। স্বাভাবিক আখ বৃদ্ধি।",
                "chemical": "No treatment needed. Continue regular monitoring.",
                "chemical_bn": "কোনো চিকিৎসার প্রয়োজন নেই। নিয়মিত পর্যবেক্ষণ চালিয়ে যান।",
                "organic": "Apply organic manure. Maintain proper irrigation.",
                "organic_bn": "জৈব সার প্রয়োগ করুন। সঠিক সেচ বজায় রাখুন।",
                "prevention": "Regular inspection. Balanced fertilization. Proper drainage.",
                "prevention_bn": "নিয়মিত পরিদর্শন। সুষম সার প্রয়োগ। সঠিক নিষ্কাশন।"
            },
            # Additional Mango entries (V2 model)
            "Bacterial_Canker___Mango": {
                "crop": "Mango",
                "disease": "Bacterial Canker",
                "disease_bn": "ব্যাকটেরিয়াল ক্যাংকার",
                "symptoms": "Water-soaked angular spots on leaves. Raised cankers on twigs. Gummosis. Fruit spots and cracking.",
                "symptoms_bn": "পাতায় পানিতে ভেজা কোণাকার দাগ। ডালে উঁচু ক্যাংকার। আঠা বের হওয়া। ফলে দাগ এবং ফাটল।",
                "chemical": "Spray Streptocycline @ 0.5g/L + Copper oxychloride @ 2.5g/L. Prune affected parts.",
                "chemical_bn": "স্ট্রেপ্টোসাইক্লিন @ ০.৫ গ্রাম/লিটার + কপার অক্সিক্লোরাইড @ ২.৫ গ্রাম/লিটার স্প্রে করুন। আক্রান্ত অংশ ছাঁটাই করুন।",
                "organic": "Remove and destroy infected parts. Apply Bordeaux paste to wounds. Use Pseudomonas fluorescens.",
                "organic_bn": "সংক্রমিত অংশ সরিয়ে ধ্বংস করুন। ক্ষতে বোর্দো পেস্ট লাগান। সিউডোমোনাস ফ্লুরোসেন্স ব্যবহার করুন।",
                "prevention": "Avoid injuries during harvest. Prune during dry weather. Maintain tree vigor.",
                "prevention_bn": "ফসল তোলার সময় আঘাত এড়িয়ে চলুন। শুষ্ক আবহাওয়ায় ছাঁটাই করুন। গাছের শক্তি বজায় রাখুন।"
            },
            "Cutting_Weevil___Mango": {
                "crop": "Mango",
                "disease": "Cutting Weevil",
                "disease_bn": "কাটিং উইভিল",
                "symptoms": "Young shoots cut and fallen. Weevil makes ring cuts. Wilting of terminal shoots.",
                "symptoms_bn": "কচি কান্ড কেটে পড়ে যাওয়া। পোকা বলয়াকার কাটা করে। শীর্ষ কান্ড ঢলে পড়া।",
                "chemical": "Spray Quinalphos @ 2ml/L or Chlorpyrifos @ 2ml/L on new flushes.",
                "chemical_bn": "নতুন পাতায় কুইনালফস @ ২ মিলি/লিটার অথবা ক্লোরপাইরিফস @ ২ মিলি/লিটার স্প্রে করুন।",
                "organic": "Collect and destroy fallen shoots with grubs. Use light traps. Apply neem oil.",
                "organic_bn": "পড়ে যাওয়া কান্ড সংগ্রহ করে লার্ভাসহ ধ্বংস করুন। আলোক ফাঁদ ব্যবহার করুন। নিম তেল প্রয়োগ করুন।",
                "prevention": "Remove and burn fallen shoots. Keep orchard clean. Proper pruning.",
                "prevention_bn": "পড়ে যাওয়া কান্ড সরিয়ে পুড়িয়ে ফেলুন। বাগান পরিষ্কার রাখুন। সঠিক ছাঁটাই।"
            },
            "Die_Back___Mango": {
                "crop": "Mango",
                "disease": "Die Back",
                "disease_bn": "ডাই ব্যাক",
                "symptoms": "Drying of twigs from tip downwards. Gummosis. Brown discoloration of wood. Tree decline.",
                "symptoms_bn": "ডালের ডগা থেকে নিচের দিকে শুকিয়ে যাওয়া। আঠা বের হওয়া। কাঠে বাদামী বিবর্ণতা। গাছের ক্ষয়।",
                "chemical": "Prune 15cm below infection. Apply Copper oxychloride paste. Spray Carbendazim @ 1g/L.",
                "chemical_bn": "সংক্রমণের ১৫ সেমি নিচে ছাঁটাই করুন। কপার অক্সিক্লোরাইড পেস্ট লাগান। কার্বেন্ডাজিম @ ১ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Remove infected parts and burn. Apply Bordeaux paste. Use Trichoderma.",
                "organic_bn": "সংক্রমিত অংশ সরিয়ে পুড়িয়ে ফেলুন। বোর্দো পেস্ট লাগান। ট্রাইকোডার্মা ব্যবহার করুন।",
                "prevention": "Avoid water stress. Maintain tree nutrition. Prune during dry season.",
                "prevention_bn": "পানির চাপ এড়িয়ে চলুন। গাছের পুষ্টি বজায় রাখুন। শুষ্ক মৌসুমে ছাঁটাই করুন।"
            },
            "Gall_Midge___Mango": {
                "crop": "Mango",
                "disease": "Gall Midge",
                "disease_bn": "গল মিজ",
                "symptoms": "Galls on leaves and inflorescence. Malformed flowers. Flower drop. Reduced fruit set.",
                "symptoms_bn": "পাতা এবং মুকুলে গল। বিকৃত ফুল। ফুল ঝরা। ফল ধারণ কমে যাওয়া।",
                "chemical": "Spray Dimethoate @ 2ml/L or Quinalphos @ 2ml/L at panicle emergence.",
                "chemical_bn": "মুকুল বের হলে ডাইমিথোয়েট @ ২ মিলি/লিটার অথবা কুইনালফস @ ২ মিলি/লিটার স্প্রে করুন।",
                "organic": "Collect and destroy galled parts. Apply neem oil @ 5ml/L. Use pheromone traps.",
                "organic_bn": "গল হওয়া অংশ সংগ্রহ করে ধ্বংস করুন। নিম তেল @ ৫ মিলি/লিটার প্রয়োগ করুন। ফেরোমন ফাঁদ ব্যবহার করুন।",
                "prevention": "Plough around trees before monsoon. Remove alternate hosts. Maintain orchard hygiene.",
                "prevention_bn": "বর্ষার আগে গাছের চারপাশে চাষ দিন। বিকল্প আতিথেয় সরিয়ে ফেলুন। বাগানের পরিচ্ছন্নতা বজায় রাখুন।"
            },
            "Sooty_Mould___Mango": {
                "crop": "Mango",
                "disease": "Sooty Mould",
                "disease_bn": "সুটি মোল্ড",
                "symptoms": "Black sooty coating on leaves. Reduced photosynthesis. Associated with honeydew from insects.",
                "symptoms_bn": "পাতায় কালো কালিঝুলির মতো আবরণ। সালোকসংশ্লেষণ কমে যাওয়া। পোকার মধু থেকে সম্পর্কিত।",
                "chemical": "First control sucking pests. Then spray Starch solution @ 5g/L to peel off mould.",
                "chemical_bn": "প্রথমে চোষক পোকা নিয়ন্ত্রণ করুন। তারপর ছত্রাক খোসা ছাড়াতে স্টার্চ দ্রবণ @ ৫ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Control mealybugs/aphids with neem oil. Spray water to wash off mould. Release natural enemies.",
                "organic_bn": "নিম তেল দিয়ে মিলিবাগ/জাব পোকা নিয়ন্ত্রণ করুন। ছত্রাক ধুয়ে ফেলতে পানি স্প্রে করুন। প্রাকৃতিক শত্রু ছাড়ুন।",
                "prevention": "Control honeydew-producing insects. Maintain tree spacing. Proper pruning.",
                "prevention_bn": "মধু উৎপাদনকারী পোকা নিয়ন্ত্রণ করুন। গাছের দূরত্ব বজায় রাখুন। সঠিক ছাঁটাই।"
            },
            # Additional Pumpkin entries (V2 model)
            "Bacterial_Leaf_Spot___Pumpkin": {
                "crop": "Pumpkin",
                "disease": "Bacterial Leaf Spot",
                "disease_bn": "ব্যাকটেরিয়াল লিফ স্পট",
                "symptoms": "Small angular water-soaked spots on leaves. Spots turn brown with yellow halos. Leaf tearing.",
                "symptoms_bn": "পাতায় ছোট কোণাকার পানিতে ভেজা দাগ। দাগ হলুদ বলয়সহ বাদামী হয়ে যায়। পাতা ছিঁড়ে যাওয়া।",
                "chemical": "Spray Copper hydroxide @ 2g/L or Streptocycline @ 0.5g/L at 10-day intervals.",
                "chemical_bn": "১০ দিন অন্তর কপার হাইড্রক্সাইড @ ২ গ্রাম/লিটার অথবা স্ট্রেপ্টোসাইক্লিন @ ০.৫ গ্রাম/লিটার স্প্রে করুন।",
                "organic": "Use disease-free seeds. Remove infected leaves. Apply Bacillus subtilis.",
                "organic_bn": "রোগমুক্ত বীজ ব্যবহার করুন। সংক্রমিত পাতা সরিয়ে ফেলুন। ব্যাসিলাস সাবটিলিস প্রয়োগ করুন।",
                "prevention": "Avoid overhead irrigation. Crop rotation. Remove plant debris.",
                "prevention_bn": "উপর থেকে সেচ এড়িয়ে চলুন। শস্য আবর্তন। ফসলের অবশিষ্টাংশ সরিয়ে ফেলুন।"
            },
            "Downy_Mildew___Pumpkin": {
                "crop": "Pumpkin",
                "disease": "Downy Mildew",
                "disease_bn": "ডাউনি মিলডিউ",
                "symptoms": "Angular yellow spots on upper leaf surface. Purplish-gray downy growth on undersides. Rapid leaf death.",
                "symptoms_bn": "পাতার উপরিভাগে কোণাকার হলুদ দাগ। নিচে বেগুনি-ধূসর তুলতুলে বৃদ্ধি। দ্রুত পাতা মারা যাওয়া।",
                "chemical": "Apply Metalaxyl + Mancozeb @ 2g/L or Cymoxanil @ 1g/L at first symptoms.",
                "chemical_bn": "প্রথম লক্ষণে মেটালাক্সিল + ম্যানকোজেব @ ২ গ্রাম/লিটার অথবা সাইমোক্সানিল @ ১ গ্রাম/লিটার প্রয়োগ করুন।",
                "organic": "Apply Bacillus subtilis. Use copper-based fungicides. Improve air circulation.",
                "organic_bn": "ব্যাসিলাস সাবটিলিস প্রয়োগ করুন। কপার-ভিত্তিক ছত্রাকনাশক ব্যবহার করুন। বায়ু চলাচল উন্নত করুন।",
                "prevention": "Plant resistant varieties. Avoid overhead irrigation. Good plant spacing.",
                "prevention_bn": "প্রতিরোধী জাত রোপণ করুন। উপর থেকে সেচ এড়িয়ে চলুন। সঠিক গাছের দূরত্ব।"
            },
            "Mosaic_Disease___Pumpkin": {
                "crop": "Pumpkin",
                "disease": "Mosaic Disease",
                "disease_bn": "মোজাইক রোগ",
                "symptoms": "Light and dark green mosaic pattern on leaves. Leaf distortion and blistering. Stunted growth. Deformed fruits.",
                "symptoms_bn": "পাতায় হালকা এবং গাঢ় সবুজ মোজাইক প্যাটার্ন। পাতার বিকৃতি এবং ফোস্কা। স্থবির বৃদ্ধি। বিকৃত ফল।",
                "chemical": "Control aphid vectors with Imidacloprid @ 0.5ml/L. No cure for infected plants.",
                "chemical_bn": "ইমিডাক্লোপ্রিড @ ০.৫ মিলি/লিটার দিয়ে জাব পোকা ভেক্টর নিয়ন্ত্রণ করুন। সংক্রমিত গাছের কোনো নিরাময় নেই।",
                "organic": "Remove infected plants. Control aphids with neem oil. Use reflective mulch.",
                "organic_bn": "সংক্রমিত গাছ সরিয়ে ফেলুন। নিম তেল দিয়ে জাব পোকা নিয়ন্ত্রণ করুন। প্রতিফলনশীল মালচ ব্যবহার করুন।",
                "prevention": "Use virus-free seeds. Control aphid vectors. Remove weed hosts.",
                "prevention_bn": "ভাইরাসমুক্ত বীজ ব্যবহার করুন। জাব পোকা ভেক্টর নিয়ন্ত্রণ করুন। আগাছা আতিথেয় সরিয়ে ফেলুন।"
            },
            
            # ======= ALIAS MAPPINGS FOR 108-CLASS MODEL =======
            # These map the new Dataset 2 format (Disease_(Crop)) to existing solutions
            # The _get_solution method also handles runtime mapping, but explicit aliases are faster
            
            # ----- Apple aliases -----
            "Apple_scab_(Apple)": "ALIAS:Apple___Apple_scab",
            "Black_rot_(Apple)": "ALIAS:Apple___Black_rot", 
            "Cedar_apple_rust_(Apple)": "ALIAS:Apple___Cedar_apple_rust",
            "healthy_(Apple)": "ALIAS:Apple___healthy",
            
            # ----- Grape aliases -----
            "Black_rot_(Grape)": "ALIAS:Grape___Black_rot",
            "Esca_(Black_Measles)_(Grape)": "ALIAS:Grape___Esca_(Black_Measles)",
            "Leaf_blight_(Isariopsis_Leaf_Spot)_(Grape)": "ALIAS:Grape___Leaf_blight_(Isariopsis_Leaf_Spot)",
            "healthy_(Grape)": "ALIAS:Grape___healthy",
            
            # ----- Corn aliases -----
            "Cercospora_leaf_spot_Gray_leaf_spot_(Corn_(maize))": "ALIAS:Corn_(maize)___Cercospora_leaf_spot_Gray_leaf_spot",
            "Common_rust_(Corn_(maize))": "ALIAS:Corn_(maize)___Common_rust_",
            "Northern_Leaf_Blight_(Corn_(maize))": "ALIAS:Corn_(maize)___Northern_Leaf_Blight",
            "healthy_(Corn_(maize))": "ALIAS:Corn_(maize)___healthy",
            
            # ----- Tomato aliases -----
            "Bacterial_spot_(Tomato)": "ALIAS:Tomato___Bacterial_spot",
            "Early_blight_(Tomato)": "ALIAS:Tomato___Early_blight",
            "Late_blight_(Tomato)": "ALIAS:Tomato___Late_blight",
            "Leaf_Mold_(Tomato)": "ALIAS:Tomato___Leaf_Mold",
            "Septoria_leaf_spot_(Tomato)": "ALIAS:Tomato___Septoria_leaf_spot",
            "Spider_mites_Two-spotted_spider_mite_(Tomato)": "ALIAS:Tomato___Spider_mites_Two-spotted_spider_mite",
            "Target_Spot_(Tomato)": "ALIAS:Tomato___Target_Spot",
            "Tomato_Yellow_Leaf_Curl_Virus_(Tomato)": "ALIAS:Tomato___Tomato_Yellow_Leaf_Curl_Virus",
            "Tomato_mosaic_virus_(Tomato)": "ALIAS:Tomato___Tomato_mosaic_virus",
            "healthy_(Tomato)": "ALIAS:Tomato___healthy",
            
            # ----- Potato aliases -----
            "Early_blight_(Potato)": "ALIAS:Potato___Early_blight",
            "Late_blight_(Potato)": "ALIAS:Potato___Late_blight",
            "healthy_(Potato)": "ALIAS:Potato___healthy",
            
            # ----- Pepper aliases -----
            "Bacterial_spot_(Pepper,_bell)": "ALIAS:Pepper,_bell___Bacterial_spot",
            "healthy_(Pepper,_bell)": "ALIAS:Pepper,_bell___healthy",
            
            # ----- Peach aliases -----
            "Bacterial_spot_(Peach)": "ALIAS:Peach___Bacterial_spot",
            "healthy_(Peach)": "ALIAS:Peach___healthy",
            
            # ----- Strawberry aliases -----
            "Leaf_scorch_(Strawberry)": "ALIAS:Strawberry___Leaf_scorch",
            "healthy_(Strawberry)": "ALIAS:Strawberry___healthy",
            
            # ----- Cherry aliases -----
            "healthy_(Cherry_(including_sour))": "ALIAS:Cherry_(including_sour)___healthy",
            
            # ----- Orange aliases -----
            "Haunglongbing_(Citrus_greening)_(Orange)": "ALIAS:Orange___Haunglongbing_(Citrus_greening)",
            
            # ----- Healthy only aliases -----
            "healthy_(Blueberry)": "ALIAS:Blueberry___healthy",
            "healthy_(Raspberry)": "ALIAS:Raspberry___healthy",
            "healthy_(Soybean)": "ALIAS:Soybean___healthy",
            
            # Default/Unknown
            "Unknown": {
                "crop": "Unknown",
                "disease": "Uncertain",
                "disease_bn": "অনিশ্চিত",
                "symptoms": "Unable to determine specific symptoms. Please provide a clearer image or consult an expert.",
                "symptoms_bn": "নির্দিষ্ট লক্ষণ নির্ধারণ করা সম্ভব হয়নি। অনুগ্রহ করে একটি পরিষ্কার ছবি দিন অথবা বিশেষজ্ঞের পরামর্শ নিন।",
                "chemical": "Consult local agricultural extension office for proper diagnosis and treatment.",
                "chemical_bn": "সঠিক রোগ নির্ণয় এবং চিকিৎসার জন্য স্থানীয় কৃষি সম্প্রসারণ অফিসে পরামর্শ করুন।",
                "organic": "Collect sample and consult expert. Avoid applying treatments without diagnosis.",
                "organic_bn": "নমুনা সংগ্রহ করুন এবং বিশেষজ্ঞের পরামর্শ নিন। রোগ নির্ণয় ছাড়া চিকিৎসা প্রয়োগ এড়িয়ে চলুন।",
                "prevention": "Monitor crop regularly. Maintain records. Contact agricultural officer.",
                "prevention_bn": "নিয়মিত ফসল পর্যবেক্ষণ করুন। রেকর্ড রাখুন। কৃষি কর্মকর্তার সাথে যোগাযোগ করুন।"
            }
        }
    
    def _get_crop_class_indices(self, crop_filter: str) -> List[int]:
        """
        Get indices of classes that match the given crop filter.
        
        Args:
            crop_filter: Crop name to filter by (e.g., "Rice", "Tomato")
            
        Returns:
            List of class indices that match the crop
        """
        crop_filter_lower = crop_filter.lower().strip()
        matching_indices = []
        
        for idx, class_name in enumerate(self.class_names):
            class_lower = class_name.lower()
            
            # Check Crop___Disease format
            if '___' in class_name:
                crop_part = class_name.split('___')[0].lower()
                if crop_filter_lower in crop_part or crop_part in crop_filter_lower:
                    matching_indices.append(idx)
            # Check Disease_(Crop) format
            elif '(' in class_name and ')' in class_name:
                start = class_name.rfind('(')
                end = class_name.rfind(')')
                crop_part = class_name[start+1:end].lower()
                if crop_filter_lower in crop_part or crop_part in crop_filter_lower:
                    matching_indices.append(idx)
            # Check if crop name appears anywhere in class name
            elif crop_filter_lower in class_lower:
                matching_indices.append(idx)
        
        logger.info(f"Found {len(matching_indices)} classes for crop filter '{crop_filter}'")
        return matching_indices
    
    def classify(self, image: Image.Image, crop_filter: Optional[str] = None) -> Dict[str, Any]:
        """
        Classify disease from crop image
        
        Args:
            image: PIL Image (should be verified as crop first)
            crop_filter: Optional crop name to filter predictions (e.g., "Rice", "Tomato")
                        When provided, only diseases for this crop are considered
            
        Returns:
            {
                "status": "success" | "error",
                "crop": str,
                "disease": str,
                "disease_bn": str,
                "confidence": float (0-100),
                "is_healthy": bool,
                "solution": {
                    "chemical": str,
                    "organic": str,
                    "prevention": str
                }
            }
        """
        try:
            # Preprocess image
            processed = self._preprocess(image)
            
            # Get predictions
            if self.model is not None:
                predictions = self._model_predict(processed)
            else:
                # Fallback to color-based analysis
                predictions = self._color_based_predict(image)
            
            # If crop_filter provided, mask predictions to only that crop
            if crop_filter:
                crop_indices = self._get_crop_class_indices(crop_filter)
                if crop_indices:
                    # Create a masked prediction array
                    masked_predictions = np.zeros_like(predictions)
                    for idx in crop_indices:
                        masked_predictions[idx] = predictions[idx]
                    
                    # Re-normalize the masked predictions
                    total = masked_predictions.sum()
                    if total > 0:
                        masked_predictions = masked_predictions / total
                    predictions = masked_predictions
                    logger.info(f"Applied crop filter '{crop_filter}', {len(crop_indices)} classes available")
                else:
                    logger.warning(f"No classes found for crop '{crop_filter}', using all predictions")
            
            # Get top prediction with softmax probabilities
            top_idx, confidence, all_probs = self._get_top_prediction(predictions)
            
            # Get class name
            if top_idx < len(self.class_names):
                raw_class = self.class_names[top_idx]
            else:
                raw_class = "Unknown"
            
            # Parse class name (format: "Disease (Crop)" or "Crop___Disease")
            crop, disease = self._parse_class_name(raw_class)
            
            # Override crop with user-selected crop if provided
            if crop_filter:
                crop = crop_filter
            
            # Apply confidence threshold
            if confidence < CONFIDENCE_THRESHOLD:
                disease = "Uncertain"
                logger.info(f"Low confidence ({confidence:.1f}%), marking as Uncertain")
            
            # Check if healthy
            is_healthy = "healthy" in disease.lower() or "healthy" in raw_class.lower()
            
            # Get solution (pass raw_class for better matching)
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
                "crop_filter_used": crop_filter,
                "solution": {
                    "chemical": solution.get("chemical", "Consult agricultural expert."),
                    "chemical_bn": solution.get("chemical_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
                    "organic": solution.get("organic", "Consult agricultural expert."),
                    "organic_bn": solution.get("organic_bn", "কৃষি বিশেষজ্ঞের পরামর্শ নিন।"),
                    "prevention": solution.get("prevention", "Regular monitoring recommended."),
                    "prevention_bn": solution.get("prevention_bn", "নিয়মিত পর্যবেক্ষণ করুন।")
                },
                "top_3_predictions": self._get_top_n(predictions, 3)
            }
            
            logger.info(f"Disease classification: {crop} - {disease} ({confidence:.1f}%)")
            
            return result
            
        except Exception as e:
            logger.error(f"Classification error: {e}")
            return {
                "status": "error",
                "message": str(e),
                "crop": "Unknown",
                "disease": "Uncertain",
                "confidence": 0.0
            }
    
    def _preprocess(self, image: Image.Image) -> torch.Tensor:
        """
        Preprocess image for PyTorch model input
        Must match training preprocessing exactly
        """
        # Ensure RGB
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        # Apply transforms (resize, to tensor, normalize)
        input_tensor = self.transform(image)
        
        # Add batch dimension
        input_tensor = input_tensor.unsqueeze(0)
        
        # Move to device
        input_tensor = input_tensor.to(self.device)
        
        return input_tensor
    
    def _model_predict(self, processed: torch.Tensor) -> np.ndarray:
        """
        Run PyTorch model inference
        Returns softmax probabilities
        """
        with torch.no_grad():
            outputs = self.model(processed)
            # Apply softmax to get probabilities
            probabilities = torch.softmax(outputs, dim=1)
            # Convert to numpy and remove batch dimension
            predictions = probabilities.cpu().numpy()[0]
        return predictions
    
    def _color_based_predict(self, image: Image.Image) -> np.ndarray:
        """
        Fallback: Color-based disease estimation
        Used when model is not available
        """
        img = image.resize((100, 100))
        img_array = np.array(img, dtype=np.float32)
        
        r = img_array[:, :, 0]
        g = img_array[:, :, 1]
        b = img_array[:, :, 2]
        
        # Analyze color patterns
        green_ratio = np.mean(g) / (np.mean(r) + np.mean(g) + np.mean(b) + 1e-6)
        brown_ratio = np.mean((r > g) & (r > 100))
        yellow_ratio = np.mean((r > 150) & (g > 150) & (b < 100))
        
        # Create pseudo-probabilities
        num_classes = max(len(self.class_names), 71)
        probs = np.full(num_classes, 0.01)
        
        # Healthy detection
        if green_ratio > 0.4:
            # Find healthy classes
            for i, name in enumerate(self.class_names):
                if "healthy" in name.lower():
                    probs[i] = 0.6 + green_ratio * 0.3
                    break
        
        # Brown spot detection
        elif brown_ratio > 0.15:
            for i, name in enumerate(self.class_names):
                if "brown" in name.lower() or "spot" in name.lower():
                    probs[i] = 0.5 + brown_ratio * 0.3
                    break
        
        # Normalize
        probs = probs / probs.sum()
        
        return probs
    
    def _get_top_prediction(self, predictions: np.ndarray) -> Tuple[int, float, np.ndarray]:
        """
        Get top prediction with softmax confidence
        
        Returns:
            (class_index, confidence_percentage, all_probabilities)
        """
        # Apply softmax if not already
        if predictions.min() < 0 or predictions.max() > 1:
            exp_preds = np.exp(predictions - np.max(predictions))
            predictions = exp_preds / exp_preds.sum()
        
        top_idx = int(np.argmax(predictions))
        confidence = float(predictions[top_idx]) * 100
        
        return top_idx, confidence, predictions
    
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
    
    def _parse_class_name(self, raw_class: str) -> Tuple[str, str]:
        """
        Parse class name into crop and disease
        
        Handles formats:
        - "Crop___Disease" (primary format from Kaggle dataset)
        - "Disease_(Crop)" with underscores (e.g., BrownSpot_(Rice))
        - "Disease (Crop)" with spaces
        - "Disease"
        """
        # First check for "___" format (most common in dataset)
        if "___" in raw_class:
            # Format: "Crop___Disease" e.g., "Corn_(maize)___Cercospora_leaf_spot"
            parts = raw_class.split("___")
            crop = parts[0].replace("_", " ").strip()
            # Handle crop names like "Corn (maize)" -> keep parentheses for readability
            crop = crop.replace("( ", "(").replace(" )", ")")
            disease = parts[1].replace("_", " ").strip()
        elif "_(" in raw_class and ")" in raw_class:
            # Format: "Disease_(Crop)" e.g., "BrownSpot_(Rice)"
            parts = raw_class.split("_(")
            disease = parts[0].replace("_", " ").strip()
            crop = parts[1].replace(")", "").replace("_", " ").strip()
        elif "(" in raw_class and ")" in raw_class:
            # Format: "Disease (Crop)"
            parts = raw_class.split("(")
            disease = parts[0].strip()
            crop = parts[1].replace(")", "").strip()
        else:
            # Single name
            crop = "Unknown"
            disease = raw_class.replace("_", " ").strip()
        
        return crop, disease
    
    def _get_solution(self, raw_class: str, disease: str, crop: str) -> Dict[str, str]:
        """
        Get treatment solution from database
        
        Args:
            raw_class: Original class name (e.g., "Tomato___Early_blight" or "BrownSpot_(Rice)")
            disease: Parsed disease name (e.g., "Early blight")
            crop: Parsed crop name (e.g., "Tomato")
        """
        # First try exact match with raw_class
        if raw_class in self.solutions_db:
            result = self.solutions_db[raw_class]
            # Handle alias references
            if isinstance(result, str) and result.startswith("ALIAS:"):
                target_key = result[6:]  # Remove "ALIAS:" prefix
                if target_key in self.solutions_db:
                    return self.solutions_db[target_key]
            return result
        
        # Normalize class name: BrownSpot_(Rice) -> BrownSpot (Rice)
        normalized_raw = raw_class.replace("_(", " (").replace("_)", ")")
        if normalized_raw in self.solutions_db:
            result = self.solutions_db[normalized_raw]
            if isinstance(result, str) and result.startswith("ALIAS:"):
                target_key = result[6:]
                if target_key in self.solutions_db:
                    return self.solutions_db[target_key]
            return result
        
        # Try with crop___disease format
        full_key = f"{crop}___{disease}".replace(" ", "_")
        for key in self.solutions_db:
            if key.lower() == full_key.lower():
                result = self.solutions_db[key]
                if isinstance(result, str) and result.startswith("ALIAS:"):
                    target_key = result[6:]
                    if target_key in self.solutions_db:
                        return self.solutions_db[target_key]
                return result
        
        # Try disease___crop format (alternative)
        full_key_alt = f"{disease}___{crop}".replace(" ", "_")
        for key in self.solutions_db:
            if key.lower() == full_key_alt.lower():
                result = self.solutions_db[key]
                if isinstance(result, str) and result.startswith("ALIAS:"):
                    target_key = result[6:]
                    if target_key in self.solutions_db:
                        return self.solutions_db[target_key]
                return result
        
        # Try "Disease (Crop)" format with spaces
        disease_crop_key = f"{disease} ({crop})"
        for key in self.solutions_db:
            if key.lower().replace("_", " ") == disease_crop_key.lower():
                result = self.solutions_db[key]
                if isinstance(result, str) and result.startswith("ALIAS:"):
                    target_key = result[6:]
                    if target_key in self.solutions_db:
                        return self.solutions_db[target_key]
                return result
        
        # Try partial match with disease
        disease_key = disease.replace(" ", "").replace("_", "").lower()
        for key, value in self.solutions_db.items():
            if isinstance(value, str):  # Skip aliases in partial matching
                continue
            key_normalized = key.replace(" ", "").replace("_", "").lower()
            if disease_key in key_normalized or key_normalized in disease_key:
                # Make sure crop also matches if specified in value
                if value.get("crop", "").lower() == crop.lower() or value.get("crop") == "Various":
                    return value
        
        # Fallback: just match disease name loosely
        for key, value in self.solutions_db.items():
            if isinstance(value, str):  # Skip aliases
                continue
            if disease_key in key.lower().replace("_", "") or key.lower().replace("_", "") in disease_key:
                return value
        
        # Return unknown
        return self.solutions_db.get("Unknown", {
            "chemical": "Consult local agricultural expert.",
            "organic": "Collect samples and consult expert.",
            "prevention": "Regular monitoring recommended."
        })


def test_classifier():
    """Test the disease classifier"""
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python disease_classifier.py <image_path>")
        return
    
    image_path = sys.argv[1]
    image = Image.open(image_path).convert('RGB')
    
    classifier = DiseaseClassifier()
    result = classifier.classify(image)
    
    print("\n" + "=" * 50)
    print("DISEASE CLASSIFICATION RESULT")
    print("=" * 50)
    print(json.dumps(result, indent=2, ensure_ascii=False))


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO)
    test_classifier()
