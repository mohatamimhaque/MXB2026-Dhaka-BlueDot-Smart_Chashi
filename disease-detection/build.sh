#!/usr/bin/env bash
set -e

echo "=== Smart Chashi - Render Build ==="

pip install --upgrade pip --quiet

echo "--- Installing CPU-only PyTorch (no GPU on Render, saves ~1.5 GB) ---"
pip install torch torchvision --index-url https://download.pytorch.org/whl/cpu --quiet

echo "--- Installing remaining dependencies ---"
pip install \
    flask>=3.0.0 \
    flask-cors>=4.0.0 \
    werkzeug>=3.0.0 \
    gunicorn>=21.0.0 \
    pillow>=10.0.0 \
    "numpy>=1.24.0" \
    "transformers>=4.35.0" \
    python-dotenv>=1.0.0 \
    --quiet

echo "=== Build complete ==="
