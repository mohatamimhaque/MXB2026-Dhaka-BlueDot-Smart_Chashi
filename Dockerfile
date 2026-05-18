FROM python:3.10

WORKDIR /app

COPY MXB2026-Dhaka-BlueDot-Smart_Chashi/disease-detection/ .

RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 7860

CMD ["python", "app.py"]