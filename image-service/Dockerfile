FROM python:3.12-slim

WORKDIR /app

# libgl1/libglib2.0-0: rapidocr-onnxruntime hard-depends on full
# opencv-python (not headless), which dlopen()s libGL at import time. No
# tesseract-ocr binary needed anymore — RapidOCR ships its own ONNX models.
RUN apt-get update && apt-get install -y --no-install-recommends libgl1 libglib2.0-0 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
# torch: CPU-only wheel via the PyTorch CPU index (avoids pulling multi-GB
# CUDA wheels for a service that only ever runs on CPU here). Generous
# timeout/retries: several of these wheels (torch, opencv) are 50-200MB and
# a slow/unstable connection mid-download otherwise aborts the whole build.
RUN pip install --no-cache-dir -r requirements.txt \
    --extra-index-url https://download.pytorch.org/whl/cpu \
    --default-timeout=180 --retries 10

COPY . .

EXPOSE 8001

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8001"]
