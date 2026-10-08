import io
import math
import threading

import cv2
import numpy as np
import requests
from fastapi import FastAPI, HTTPException
from PIL import Image
from pydantic import BaseModel
from rapidocr_onnxruntime import RapidOCR

app = FastAPI(title="Metrix Image Service")

# Both engines are process-wide singletons, loaded once and reused across
# requests — RapidOCR bundles its own ONNX detection/classification/
# recognition models (no system tesseract binary needed); the OpenCLIP model
# is loaded lazily (see _get_clip_model) since /embed is only hit for
# baseline-comparison photos, not every /analyze call, and the first load
# downloads/caches weights.
_ocr_engine = RapidOCR()

_clip_lock = threading.Lock()
_clip_model = None
_clip_preprocess = None
CLIP_MODEL_NAME = "ViT-B-32"
CLIP_PRETRAINED = "laion2b_s34b_b79k"


def _get_clip_model():
    """Lazily load OpenCLIP on first use; safe under concurrent requests."""
    global _clip_model, _clip_preprocess
    if _clip_model is not None:
        return _clip_model, _clip_preprocess

    with _clip_lock:
        if _clip_model is None:
            import open_clip
            import torch

            model, _, preprocess = open_clip.create_model_and_transforms(
                CLIP_MODEL_NAME, pretrained=CLIP_PRETRAINED
            )
            model.eval()
            torch.set_grad_enabled(False)
            _clip_model = model
            _clip_preprocess = preprocess

        return _clip_model, _clip_preprocess


@app.get("/health")
def health():
    return {"status": "ok", "service": "image-service"}


class AnalyzeRequest(BaseModel):
    image_url: str


class Word(BaseModel):
    text: str
    confidence: float
    x: int
    y: int
    width: int
    height: int


class AnalyzeResponse(BaseModel):
    image_width: int
    image_height: int
    text: str
    avg_confidence: float
    words: list[Word]

    # Exposure (brightness)
    brightness_mean: float
    is_dark: bool
    is_overexposed: bool

    # Sharpness / blur — variance of the Laplacian (OpenCV). Higher = sharper.
    sharpness_laplacian_var: float

    # Contrast — standard deviation of grayscale pixel intensity (RMS contrast).
    contrast_std_dev: float

    # Glare / reflection — percentage of pixels at/near full saturation (>250/255).
    glare_ratio_pct: float

    # Noise — fast noise-variance estimate (Immerkaer's method), reported as
    # an estimated sigma. Higher = noisier.
    noise_sigma: float


DARK_THRESHOLD = 50.0
OVEREXPOSED_THRESHOLD = 220.0
GLARE_SATURATION_LEVEL = 250


def _decode_image(content: bytes) -> tuple[Image.Image, np.ndarray]:
    """Returns (PIL RGB image, OpenCV BGR ndarray) for the same bytes."""
    pil_image = Image.open(io.BytesIO(content)).convert("RGB")
    cv_image = cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)

    return pil_image, cv_image


def _fetch_image(image_url: str) -> tuple[Image.Image, np.ndarray]:
    try:
        response = requests.get(image_url, timeout=10)
        response.raise_for_status()

        return _decode_image(response.content)
    except Exception as exc:
        raise HTTPException(status_code=422, detail=f"Could not fetch/decode image: {exc}")


def _sharpness_laplacian_var(gray: np.ndarray) -> float:
    return float(cv2.Laplacian(gray, cv2.CV_64F).var())


def _contrast_std_dev(gray: np.ndarray) -> float:
    return float(gray.std())


def _glare_ratio_pct(gray: np.ndarray) -> float:
    saturated = np.count_nonzero(gray >= GLARE_SATURATION_LEVEL)

    return float(saturated) / gray.size * 100.0


def _noise_sigma(gray: np.ndarray) -> float:
    """
    Fast noise-variance estimate (J. Immerkaer, "Fast Noise Variance
    Estimation", CVIU 1996): convolve with a Laplacian-of-the-mask kernel
    that cancels out smooth image content, leaving mostly noise energy, then
    scale the mean absolute response into an estimated noise sigma. Cheap
    (single 3x3 convolution), no reference/clean image required.
    """
    h, w = gray.shape
    if h < 3 or w < 3:
        return 0.0

    kernel = np.array([[1, -2, 1], [-2, 4, -2], [1, -2, 1]], dtype=np.float64)
    convolved = cv2.filter2D(gray.astype(np.float64), -1, kernel, borderType=cv2.BORDER_CONSTANT)

    sigma = math.sqrt(math.pi / 2) * np.sum(np.abs(convolved)) / (6 * (w - 2) * (h - 2))

    return float(sigma)


def _run_ocr(cv_image: np.ndarray) -> tuple[str, float, list[Word]]:
    """
    RapidOCR (ONNXRuntime) detection + recognition pass. Returns the
    concatenated text, an overall 0-100 confidence (mean of per-box scores,
    matching the 0-100 scale the rest of the system already compares
    against), and per-word bounding boxes derived from RapidOCR's 4-point
    polygons — used as a size/framing proxy (tallest word height vs image
    height).
    """
    ocr_result, _ = _ocr_engine(cv_image)

    words: list[Word] = []
    text_parts: list[str] = []
    confidences: list[float] = []

    for box, text, score in ocr_result or []:
        raw_text = (text or "").strip()
        if not raw_text:
            continue

        xs = [point[0] for point in box]
        ys = [point[1] for point in box]
        x_min, x_max = int(min(xs)), int(max(xs))
        y_min, y_max = int(min(ys)), int(max(ys))
        confidence_pct = float(score) * 100.0

        text_parts.append(raw_text)
        confidences.append(confidence_pct)
        words.append(
            Word(
                text=raw_text,
                confidence=confidence_pct,
                x=x_min,
                y=y_min,
                width=max(x_max - x_min, 0),
                height=max(y_max - y_min, 0),
            )
        )

    avg_confidence = sum(confidences) / len(confidences) if confidences else 0.0

    return " ".join(text_parts), avg_confidence, words


@app.post("/analyze", response_model=AnalyzeResponse)
def analyze(payload: AnalyzeRequest):
    """
    One pass over a submitted photo covering every check that doesn't need a
    baseline/reference image to compare against:
      - OCR (product-name legibility) via RapidOCR — text, per-word
        confidence, and bounding boxes (also used as a size/framing proxy).
      - Resolution (raw width/height — the caller applies the 1280x720 /
        1920x1080 thresholds).
      - Sharpness/blur (variance of the Laplacian).
      - Brightness (mean grayscale intensity).
      - Contrast (grayscale standard deviation).
      - Glare/reflection (percentage of near-fully-saturated pixels).
      - Noise (fast noise-variance estimate).

    True product-visibility/occlusion/multi-product/angle checks need an
    object-detection model and a product catalog, neither of which exist in
    this system yet — deliberately out of scope here.
    """
    _, cv_image = _fetch_image(payload.image_url)
    height, width = cv_image.shape[:2]
    gray = cv2.cvtColor(cv_image, cv2.COLOR_BGR2GRAY)

    text, avg_confidence, words = _run_ocr(cv_image)
    brightness_mean = float(gray.mean())

    return AnalyzeResponse(
        image_width=width,
        image_height=height,
        text=text,
        avg_confidence=avg_confidence,
        words=words,
        brightness_mean=brightness_mean,
        is_dark=brightness_mean < DARK_THRESHOLD,
        is_overexposed=brightness_mean > OVEREXPOSED_THRESHOLD,
        sharpness_laplacian_var=_sharpness_laplacian_var(gray),
        contrast_std_dev=_contrast_std_dev(gray),
        glare_ratio_pct=_glare_ratio_pct(gray),
        noise_sigma=_noise_sigma(gray),
    )


class EmbedRequest(BaseModel):
    image_url: str


class EmbedResponse(BaseModel):
    embedding: list[float]
    model: str
    dim: int


@app.post("/embed", response_model=EmbedResponse)
def embed(payload: EmbedRequest):
    """
    Vision embedding for baseline-vs-submitted-photo comparison, via a
    locally-run OpenCLIP model (CPU) — no external API, no API key. The
    caller (Laravel) stores this vector once per photo (media.embedding /
    outlet_baselines.baseline_embedding) and computes cosine similarity
    locally rather than re-requesting an embedding on every comparison.
    """
    pil_image, _ = _fetch_image(payload.image_url)

    model, preprocess = _get_clip_model()

    import torch

    tensor = preprocess(pil_image).unsqueeze(0)
    with torch.no_grad():
        features = model.encode_image(tensor)
        features = features / features.norm(dim=-1, keepdim=True)

    vector = features.squeeze(0).tolist()

    return EmbedResponse(embedding=vector, model=f"open_clip/{CLIP_MODEL_NAME}/{CLIP_PRETRAINED}", dim=len(vector))
