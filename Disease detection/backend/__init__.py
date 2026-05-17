"""
Backend ML Module for Smart Chashi
====================================
Disease Detection ML Pipeline Components
"""

__version__ = "2.0.0"

from .crop_classifier import CropClassifier
from .disease_classifier import DiseaseClassifier

__all__ = ["CropClassifier", "DiseaseClassifier"]
