import { apiClient } from './client';

export interface OcrWord {
  text: string;
  confidence: number;
  x: number;
  y: number;
  width: number;
  height: number;
}

export type RuleCheckResult = 'pass' | 'flag' | 'fail' | 'not_applicable';

export interface ImageAnalysisResult {
  ocr_text: string;
  // 0-100 scale (RapidOCR's confidence scale — the same scale
  // Media.ocr_avg_confidence stores and PhotoLegibilityRule compares
  // against), not a 0-1 fraction.
  ocr_avg_confidence: number;
  text_height_ratio: number | null;
  brightness_mean: number;
  is_dark: boolean;
  is_overexposed: boolean;
  image_width: number | null;
  image_height: number | null;
  sharpness_laplacian_var: number | null;
  contrast_std_dev: number | null;
  glare_ratio_pct: number | null;
  noise_sigma: number | null;
  word_count: number;
  words: OcrWord[];
  checks: {
    resolution: { result: RuleCheckResult; min_width: number; min_height: number; recommended_width: number; recommended_height: number };
    legibility: { result: RuleCheckResult; min_acceptable_confidence: number };
    framing: { result: RuleCheckResult; hard_fail_ratio: number; borderline_ratio: number };
    exposure: { result: RuleCheckResult; dark_threshold: number; overexposed_threshold: number };
    blur: { result: RuleCheckResult; hard_fail_variance: number; borderline_variance: number };
    contrast: { result: RuleCheckResult; poor_threshold: number };
    glare: { result: RuleCheckResult; warning_threshold_pct: number; reject_threshold_pct: number };
    noise: { result: RuleCheckResult; warning_threshold: number; reject_threshold: number };
  };
}

export interface ImageTestResponse {
  success: boolean;
  result: ImageAnalysisResult | null;
  url: string;
}

export const analyzeImage = async (file: File): Promise<ImageTestResponse> => {
  const formData = new FormData();
  formData.append('image', file);

  const response = await apiClient.post<ImageTestResponse>('/qa/image-test', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });

  return response.data;
};
