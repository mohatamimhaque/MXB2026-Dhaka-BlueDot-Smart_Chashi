# ================================================================
#  Smart Chashi - AI Disease Detection
#  Docker image for Render deployment
# ================================================================

FROM python:3.11-slim

# System deps needed by Pillow / torch
RUN apt-get update && apt-get install -y --no-install-recommends \
        libglib2.0-0 \
        libgl1 \
        libgomp1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# --- Install CPU-only PyTorch first (saves ~1.5 GB vs CUDA build) ---
RUN pip install --no-cache-dir \
    torch torchvision \
    --index-url https://download.pytorch.org/whl/cpu

# --- Install remaining Python dependencies ---
COPY requirements.txt .
RUN pip install --no-cache-dir \
    flask>=3.0.0 \
    flask-cors>=4.0.0 \
    werkzeug>=3.0.0 \
    gunicorn>=21.0.0 \
    pillow>=10.0.0 \
    "numpy>=1.24.0" \
    "transformers>=4.35.0" \
    python-dotenv>=1.0.0

# --- Copy application code ---
COPY app.py .
COPY backend/ ./backend/
COPY simple-plant-detection/ ./simple-plant-detection/
COPY templates/ ./templates/
COPY static/ ./static/

# Render injects PORT at runtime; default to 8080 locally
ENV PORT=8080
ENV PYTHONIOENCODING=utf-8
ENV FLASK_ENV=production

EXPOSE $PORT

# 1 worker — ML models are large; multiple workers would multiply RAM usage.
# Timeout 300s to allow cold model load on first request.
CMD gunicorn app:app \
    --workers 1 \
    --timeout 300 \
    --bind "0.0.0.0:$PORT"
