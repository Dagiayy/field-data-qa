import React, { useMemo, useState } from 'react';
import { Upload, ImageIcon, RefreshCw, AlertCircle, CheckCircle2, AlertTriangle, XCircle, MinusCircle, Target } from 'lucide-react';
import { analyzeImage, type ImageAnalysisResult, type RuleCheckResult } from '../api/imageTest';

// Levenshtein edit distance — standard O(n*m) DP, fine at ground-truth-label
// string lengths (a product name/price line, not a whole page of text).
function levenshteinDistance(a: string, b: string): number {
  const rows = a.length + 1;
  const cols = b.length + 1;
  const d: number[][] = Array.from({ length: rows }, () => new Array<number>(cols).fill(0));

  for (let i = 0; i < rows; i++) d[i][0] = i;
  for (let j = 0; j < cols; j++) d[0][j] = j;

  for (let i = 1; i < rows; i++) {
    for (let j = 1; j < cols; j++) {
      const cost = a[i - 1] === b[j - 1] ? 0 : 1;
      d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);
    }
  }

  return d[rows - 1][cols - 1];
}

// Same normalization + thresholds as the "Product Name Matching" QA spec
// (>90% match, 80-90% possible match, <80% no match) — reused here so a
// tester's read on "is this OCR accurate enough" lines up with what the
// eventual product-name-matching rule would decide.
function normalizeForComparison(s: string): string {
  return s.toLowerCase().trim().replace(/\s+/g, ' ');
}

function ocrAccuracy(expected: string, actual: string): { similarityPct: number; distance: number } {
  const e = normalizeForComparison(expected);
  const a = normalizeForComparison(actual);

  if (!e && !a) return { similarityPct: 100, distance: 0 };

  const distance = levenshteinDistance(e, a);
  const maxLen = Math.max(e.length, a.length) || 1;

  return { similarityPct: Math.max(0, (1 - distance / maxLen) * 100), distance };
}

type MatchVerdict = 'match' | 'possible_match' | 'no_match';

function matchVerdict(similarityPct: number): MatchVerdict {
  if (similarityPct > 90) return 'match';
  if (similarityPct >= 80) return 'possible_match';
  return 'no_match';
}

const MATCH_BADGE: Record<MatchVerdict, { label: string; cls: string }> = {
  match: { label: 'Match', cls: 'bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' },
  possible_match: { label: 'Possible Match', cls: 'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800' },
  no_match: { label: 'No Match', cls: 'bg-rose-50 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800' },
};

const CHECK_BADGE: Record<RuleCheckResult, { label: string; cls: string; icon: React.ReactNode }> = {
  pass: {
    label: 'Pass',
    cls: 'bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
    icon: <CheckCircle2 className="w-3.5 h-3.5" />,
  },
  flag: {
    label: 'Flag',
    cls: 'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    icon: <AlertTriangle className="w-3.5 h-3.5" />,
  },
  fail: {
    label: 'Fail',
    cls: 'bg-rose-50 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800',
    icon: <XCircle className="w-3.5 h-3.5" />,
  },
  not_applicable: {
    label: 'N/A',
    cls: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
    icon: <MinusCircle className="w-3.5 h-3.5" />,
  },
};

const CheckBadge: React.FC<{ result: RuleCheckResult }> = ({ result }) => {
  const cfg = CHECK_BADGE[result];
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-bold ${cfg.cls}`}>
      {cfg.icon} {cfg.label}
    </span>
  );
};

export const ImageTest: React.FC = () => {
  const [selectedImage, setSelectedImage] = useState<string | null>(null);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [results, setResults] = useState<ImageAnalysisResult | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [expectedText, setExpectedText] = useState('');

  // Independent of the persisted QA pipeline — this is purely a client-side
  // accuracy check against whatever ground-truth text the tester types in,
  // so it can be used to gauge RapidOCR's real-world accuracy on demand
  // without touching a real submission.
  const accuracy = useMemo(() => {
    if (!results || !expectedText.trim()) return null;
    return ocrAccuracy(expectedText, results.ocr_text);
  }, [results, expectedText]);

  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const imageUrl = URL.createObjectURL(file);
      setSelectedImage(imageUrl);
      setSelectedFile(file);
      setResults(null);
      setError(null);
    }
  };

  const handleAnalyze = async () => {
    if (!selectedFile) return;

    setIsAnalyzing(true);
    setError(null);

    try {
      const response = await analyzeImage(selectedFile);
      if (response.success && response.result) {
        setResults(response.result);
      } else {
        setError('Analysis failed or service is down. Please check backend and docker logs.');
        setResults(null);
      }
    } catch (err: any) {
      console.error(err);
      setError(err.response?.data?.message || 'Error communicating with backend.');
      setResults(null);
    } finally {
      setIsAnalyzing(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white">OCR & Photo Analysis Testing</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Upload any image and see exactly what the OCR pipeline detects — full raw parameters, plus how the real
            legibility, framing, and exposure QA rules would score it, using their actual thresholds. Independent of
            ingestion — nothing here touches a real submission.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
          <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">Upload Image</h2>

          <div className="border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-xl p-8 flex flex-col items-center justify-center text-center">
            {selectedImage ? (
              <div className="space-y-4 w-full">
                <img src={selectedImage} alt="Uploaded preview" className="max-h-64 mx-auto rounded-lg object-contain" />
                <div className="flex justify-center gap-3">
                  <button
                    onClick={() => {
                      setSelectedImage(null);
                      setSelectedFile(null);
                      setResults(null);
                      setError(null);
                      setExpectedText('');
                    }}
                    className="px-4 py-2 text-sm font-semibold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                  >
                    Clear
                  </button>
                  <button
                    onClick={handleAnalyze}
                    disabled={isAnalyzing}
                    className="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-colors flex items-center gap-2 disabled:opacity-50"
                  >
                    {isAnalyzing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
                    Analyze Image
                  </button>
                </div>
              </div>
            ) : (
              <>
                <div className="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                  <ImageIcon className="w-8 h-8 text-slate-400" />
                </div>
                <p className="text-sm font-semibold text-slate-900 dark:text-white">Click to upload or drag and drop</p>
                <p className="text-xs text-slate-500 mt-1">PNG, JPG or GIF</p>
                <input type="file" className="hidden" id="image-upload" accept="image/*" onChange={handleImageUpload} />
                <label
                  htmlFor="image-upload"
                  className="mt-4 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                >
                  Select File
                </label>
              </>
            )}
          </div>
        </div>

        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
          <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">QA Rule Checks</h2>

          {!results && !isAnalyzing && !error && (
            <div className="h-48 flex items-center justify-center text-slate-500 text-sm italic">
              Upload an image and run analysis to see results here.
            </div>
          )}

          {error && !isAnalyzing && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3">
              <AlertCircle className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
              <div className="text-sm font-medium text-red-800">{error}</div>
            </div>
          )}

          {isAnalyzing && (
            <div className="h-48 flex flex-col items-center justify-center text-slate-500 gap-3">
              <RefreshCw className="w-8 h-8 animate-spin text-emerald-500" />
              <p className="text-sm font-medium animate-pulse">Running photo analysis...</p>
            </div>
          )}

          {results && !isAnalyzing && (
            <div className="space-y-3">
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Resolution (enough pixels)</span>
                <CheckBadge result={results.checks.resolution.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Legibility (product name readable)</span>
                <CheckBadge result={results.checks.legibility.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Size / Framing (product big enough)</span>
                <CheckBadge result={results.checks.framing.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Exposure (not too dark / bright)</span>
                <CheckBadge result={results.checks.exposure.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Blur / Sharpness</span>
                <CheckBadge result={results.checks.blur.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Contrast</span>
                <CheckBadge result={results.checks.contrast.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Glare / Reflection</span>
                <CheckBadge result={results.checks.glare.result} />
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                <span className="text-xs font-bold text-slate-600 dark:text-slate-300">Noise</span>
                <CheckBadge result={results.checks.noise.result} />
              </div>
            </div>
          )}
        </div>
      </div>

      {results && !isAnalyzing && (
        <>
          {/* Raw OCR Text + Accuracy Measurement */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-4">
            <div>
              <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">OCR Text Detected</div>
              <div className="font-mono text-sm text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/50 rounded-lg p-3 min-h-[2.5rem] break-words">
                {results.ocr_text || <span className="italic text-slate-400">No text detected</span>}
              </div>
            </div>

            <div className="border-t border-slate-100 dark:border-slate-800 pt-4">
              <div className="flex items-center gap-1.5 text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">
                <Target className="w-3.5 h-3.5" /> Measure OCR Accuracy
              </div>
              <p className="text-[11px] text-slate-400 mb-2">
                Type what the label actually says (ground truth) to score RapidOCR's accuracy against it — independent of ingestion.
              </p>
              <input
                type="text"
                value={expectedText}
                onChange={(e) => setExpectedText(e.target.value)}
                placeholder="e.g. SUNFLOWER OIL 1L PREMIUM"
                className="w-full text-sm font-mono px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
              />

              {accuracy && (
                <div className="mt-3 flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                  <div>
                    <div className="text-lg font-black text-slate-900 dark:text-white font-mono">{accuracy.similarityPct.toFixed(1)}%</div>
                    <div className="text-[10px] text-slate-400">
                      similarity · edit distance {accuracy.distance} · match &gt;90%, possible match 80-90%
                    </div>
                  </div>
                  <span className={`inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-bold ${MATCH_BADGE[matchVerdict(accuracy.similarityPct)].cls}`}>
                    {MATCH_BADGE[matchVerdict(accuracy.similarityPct)].label}
                  </span>
                </div>
              )}
            </div>
          </div>

          {/* All Raw Parameters */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
            <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">Raw Parameters</h2>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">OCR Confidence</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">{results.ocr_avg_confidence}%</div>
                <div className="text-[10px] text-slate-400 mt-0.5">min acceptable: {results.checks.legibility.min_acceptable_confidence}%</div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Text Height Ratio</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.text_height_ratio !== null ? results.text_height_ratio : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  fail &lt; {results.checks.framing.hard_fail_ratio}, flag &lt; {results.checks.framing.borderline_ratio}
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Brightness (mean)</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">{results.brightness_mean}</div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  dark &lt; {results.checks.exposure.dark_threshold}, bright &gt; {results.checks.exposure.overexposed_threshold}
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Image Size</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.image_width && results.image_height ? `${results.image_width}×${results.image_height}` : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  min long/short edge: {results.checks.resolution.min_width}/{results.checks.resolution.min_height}px (either orientation)
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Blur / Sharpness</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.sharpness_laplacian_var !== null ? results.sharpness_laplacian_var.toFixed(1) : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  reject &lt; {results.checks.blur.hard_fail_variance}, flag &lt; {results.checks.blur.borderline_variance}
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Contrast (std dev)</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.contrast_std_dev !== null ? results.contrast_std_dev.toFixed(1) : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">poor &lt; {results.checks.contrast.poor_threshold}</div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Glare / Reflection</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.glare_ratio_pct !== null ? `${results.glare_ratio_pct.toFixed(2)}%` : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  flag &gt; {results.checks.glare.warning_threshold_pct}%, reject &gt; {results.checks.glare.reject_threshold_pct}%
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Noise (sigma)</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">
                  {results.noise_sigma !== null ? results.noise_sigma.toFixed(2) : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400 mt-0.5">
                  flag &gt; {results.checks.noise.warning_threshold}, reject &gt; {results.checks.noise.reject_threshold}
                </div>
              </div>
              <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                <div className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Words Detected</div>
                <div className="font-semibold text-slate-900 dark:text-white font-mono">{results.word_count}</div>
                <div className="text-[10px] text-slate-400 mt-0.5">via RapidOCR (ONNXRuntime)</div>
              </div>
            </div>
          </div>

          {/* Per-Word OCR Breakdown */}
          {results.words.length > 0 && (
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
              <h2 className="text-lg font-bold text-slate-900 dark:text-white mb-4">Per-Word OCR Breakdown</h2>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                      <th className="py-2.5 px-3">Word</th>
                      <th className="py-2.5 px-3">Confidence</th>
                      <th className="py-2.5 px-3">Position (x, y)</th>
                      <th className="py-2.5 px-3">Size (w × h)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                    {results.words.map((word, idx) => (
                      <tr key={idx}>
                        <td className="py-2 px-3 font-mono font-bold text-slate-900 dark:text-white">{word.text}</td>
                        <td
                          className={`py-2 px-3 font-mono ${
                            word.confidence < results.checks.legibility.min_acceptable_confidence
                              ? 'text-rose-600 dark:text-rose-400'
                              : 'text-emerald-600 dark:text-emerald-400'
                          }`}
                        >
                          {word.confidence.toFixed(1)}%
                        </td>
                        <td className="py-2 px-3 font-mono text-slate-500 dark:text-slate-400">
                          {word.x}, {word.y}
                        </td>
                        <td className="py-2 px-3 font-mono text-slate-500 dark:text-slate-400">
                          {word.width} × {word.height}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
};
