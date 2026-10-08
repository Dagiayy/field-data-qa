import React, { useState } from 'react';
import type { Media, QAFlag, QualityVerdict } from '../types';
import { FlagBadge } from './FlagBadge';
import { StatusDot } from './StatusDot';
import {
  AlertCircle,
  AlertTriangle,
  CheckCircle2,
  MapPin,
  Hash,
  Sparkles,
  Sun,
  ScanText,
  Ruler,
  Maximize,
  Focus,
  Contrast,
  Sparkle,
  Waves,
  HelpCircle,
  ImageOff,
} from 'lucide-react';

// Broken/dead photo URLs (a bad file_ref, a stale test-image link, the
// image-service or storage disk being briefly unreachable) are a real,
// observed failure mode here — fall back to an explicit placeholder instead
// of the browser's default broken-image icon, which looks like a rendering
// bug rather than a data problem.
const ImageWithFallback: React.FC<{ src?: string | null; alt: string; className?: string }> = ({ src, alt, className }) => {
  const [failed, setFailed] = useState(false);

  if (!src || failed) {
    return (
      <div className={`flex flex-col items-center justify-center gap-1.5 bg-gray-100 text-gray-400 ${className ?? ''}`}>
        <ImageOff className="w-6 h-6" />
        <span className="text-[10px] font-medium px-2 text-center">{!src ? 'No image URL on record' : 'Photo failed to load'}</span>
      </div>
    );
  }

  return <img src={src} alt={alt} className={className} onError={() => setFailed(true)} />;
};

interface PhotoCompareCardProps {
  media: Media;
  flags: QAFlag[];
}

const VERDICT_STYLES: Record<'pass' | 'flag' | 'fail', { label: string; cls: string }> = {
  pass: { label: 'Accept', cls: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800' },
  flag: { label: 'Warning', cls: 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800' },
  fail: { label: 'Reject', cls: 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800' },
};

const VerdictBadge: React.FC<{ verdict: QualityVerdict }> = ({ verdict }) => {
  if (!verdict) {
    return (
      <span className="inline-flex items-center px-1.5 py-0.5 rounded-full border text-[9px] font-bold uppercase bg-gray-100 text-gray-400 border-gray-200">
        N/A
      </span>
    );
  }
  const style = VERDICT_STYLES[verdict];
  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded-full border text-[9px] font-bold uppercase ${style.cls}`}>
      {style.label}
    </span>
  );
};

const QualityMetric: React.FC<{
  icon: React.ReactNode;
  label: string;
  value: React.ReactNode;
  detail?: string;
  verdict: QualityVerdict;
}> = ({ icon, label, value, detail, verdict }) => (
  <div className="bg-white dark:bg-slate-950/60 p-2.5 rounded-lg border border-gray-150 dark:border-slate-800 space-y-1">
    <div className="flex items-center justify-between">
      <span className="text-[10px] text-gray-500 dark:text-slate-400 flex items-center gap-1 font-medium uppercase">
        {icon} {label}
      </span>
      <VerdictBadge verdict={verdict} />
    </div>
    <div className="text-sm font-extrabold text-gray-800 dark:text-slate-100">{value}</div>
    {detail && <div className="text-[9px] text-gray-400 dark:text-slate-500">{detail}</div>}
  </div>
);

// Checks the spec calls for that this system can't automate yet — no
// object-detection model (product visibility / occlusion / multi-product /
// angle) and no product-name catalog (name matching) exist anywhere in this
// codebase. Shown honestly as "not evaluated" rather than faking a result.
const NOT_YET_AUTOMATED: { label: string; requires: string }[] = [
  { label: 'Product Visibility', requires: 'object-detection model' },
  { label: 'Camera Angle / Perspective', requires: 'object-detection model' },
  { label: 'Occlusion', requires: 'segmentation / object-detection model' },
  { label: 'Multiple Products', requires: 'object-detection model' },
  { label: 'Product Name Matching', requires: 'product catalog reference data' },
];

export const PhotoCompareCard: React.FC<PhotoCompareCardProps> = ({ media, flags }) => {
  const { baseline, photo_analysis: analysis } = media;

  // The authoritative source for "what's wrong with this photo" is the
  // backend's own QaFlag rows, matched by media_ref — not re-derived
  // thresholds guessed on the frontend.
  const photoFlags = flags.filter((f) => f.media_refs?.includes(media.media_ref));
  const hasFail = photoFlags.some((f) => f.result === 'fail');
  const hasSoftFlag = !hasFail && photoFlags.some((f) => f.result === 'flag');

  let status: { label: string; bg: string; icon: React.ReactNode };
  if (hasFail) {
    status = {
      label: 'Failed Automated Checks',
      bg: 'bg-rose-50 text-rose-800 border-rose-200',
      icon: <AlertCircle className="w-3.5 h-3.5 text-rose-600" />,
    };
  } else if (hasSoftFlag) {
    status = {
      label: 'Needs Human Review',
      bg: 'bg-amber-50 text-amber-800 border-amber-200',
      icon: <AlertTriangle className="w-3.5 h-3.5 text-amber-600" />,
    };
  } else {
    status = {
      label: 'Verified',
      bg: 'bg-emerald-50 text-emerald-800 border-emerald-200',
      icon: <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />,
    };
  }

  const similarityLabel = baseline?.similarity_method === 'perceptual_hash_fallback'
    ? 'Similarity (Hash Fallback)'
    : 'Similarity (OpenCLIP)';

  return (
    <div className="relative bg-white rounded-2xl border border-gray-150 p-4 shadow-sm space-y-4">
      <StatusDot status={hasFail ? 'fail' : hasSoftFlag ? 'flag' : 'pass'} />
      <div className="flex items-center justify-between">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">Photo Evidence</span>
          <h4 className="text-sm font-bold text-gray-800">{baseline ? baseline.spot_label : `Question Ref: ${media.question_id}`}</h4>
        </div>
        <div className="flex items-center gap-2">
          <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-bold ${status.bg}`}>
            {status.icon}
            {status.label}
          </span>
          <span className="text-xs text-gray-500 font-mono">
            {new Date(media.captured_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs text-gray-600 font-medium">
            <span className="flex items-center gap-1 text-slate-800 font-bold">
              <span className="w-2 h-2 rounded-full bg-emerald-500" /> Submitted Photo
            </span>
            <a href={media.url} target="_blank" rel="noreferrer" className="text-emerald-700 hover:underline text-[11px]">
              View Full Resolution
            </a>
          </div>
          <div className="relative aspect-4/3 rounded-xl overflow-hidden bg-gray-100 border border-gray-200">
            <ImageWithFallback src={media.url} alt="Submitted evidence" className="w-full h-full object-cover" />
          </div>
        </div>

        {baseline ? (
          <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs text-gray-600 font-medium">
              <span className="flex items-center gap-1 text-slate-800 font-bold">
                <span className="w-2 h-2 rounded-full bg-blue-500" /> Outlet Baseline ({baseline.spot_label})
              </span>
              <a href={baseline.photo_url} target="_blank" rel="noreferrer" className="text-blue-700 hover:underline text-[11px]">
                View Original Baseline
              </a>
            </div>
            <div className="relative aspect-4/3 rounded-xl overflow-hidden bg-gray-100 border border-gray-200">
              <ImageWithFallback src={baseline.photo_url} alt="Baseline comparison" className="w-full h-full object-cover" />
            </div>
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center aspect-4/3 rounded-xl bg-gray-50 border border-dashed border-gray-200 p-4 text-center">
            <span className="text-xs text-gray-400 font-medium">No spot baseline photo registered for this outlet location</span>
          </div>
        )}
      </div>

      {baseline && (
        <div className="space-y-1.5">
          <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Baseline Verification</span>
          <div className="grid grid-cols-3 gap-2 bg-gray-50 p-2.5 rounded-xl text-center border border-gray-100">
            <div>
              <div className="text-[10px] text-gray-500 flex items-center justify-center gap-1 font-medium uppercase">
                <Hash className="w-3 h-3 text-gray-400" /> Hash Distance
              </div>
              <div className="text-sm font-extrabold text-gray-800 mt-0.5">{baseline.hash_distance ?? 'N/A'}</div>
            </div>
            <div>
              <div className="text-[10px] text-gray-500 flex items-center justify-center gap-1 font-medium uppercase">
                <Sparkles className="w-3 h-3 text-gray-400" /> {similarityLabel}
              </div>
              <div className="text-sm font-extrabold text-gray-800 mt-0.5">
                {baseline.embedding_similarity !== null ? `${(baseline.embedding_similarity * 100).toFixed(0)}%` : 'N/A'}
              </div>
            </div>
            <div>
              <div className="text-[10px] text-gray-500 flex items-center justify-center gap-1 font-medium uppercase">
                <MapPin className="w-3 h-3 text-gray-400" /> GPS Distance
              </div>
              <div className="text-sm font-extrabold text-gray-800 mt-0.5">{baseline.gps_distance_m}m</div>
            </div>
          </div>
        </div>
      )}

      {analysis && (
        <div className="space-y-2">
          <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Photo Quality Analysis</span>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <QualityMetric
              icon={<Maximize className="w-3 h-3 text-gray-400" />}
              label="Resolution"
              value={analysis.resolution.width && analysis.resolution.height ? `${analysis.resolution.width}×${analysis.resolution.height}` : 'N/A'}
              detail={`min ${analysis.resolution.min_width}×${analysis.resolution.min_height}`}
              verdict={analysis.resolution.verdict}
            />
            <QualityMetric
              icon={<Focus className="w-3 h-3 text-gray-400" />}
              label="Blur / Sharpness"
              value={analysis.blur.laplacian_variance !== null ? analysis.blur.laplacian_variance.toFixed(0) : 'N/A'}
              detail={`reject < ${analysis.blur.hard_fail_variance}`}
              verdict={analysis.blur.verdict}
            />
            <QualityMetric
              icon={<Sun className="w-3 h-3 text-gray-400" />}
              label="Brightness"
              value={analysis.brightness_mean !== null ? analysis.brightness_mean.toFixed(0) : 'N/A'}
              detail={analysis.is_dark ? 'Too Dark' : analysis.is_overexposed ? 'Overexposed' : 'Normal'}
              verdict={analysis.is_dark || analysis.is_overexposed ? 'flag' : analysis.brightness_mean !== null ? 'pass' : null}
            />
            <QualityMetric
              icon={<Contrast className="w-3 h-3 text-gray-400" />}
              label="Contrast"
              value={analysis.contrast.std_dev !== null ? analysis.contrast.std_dev.toFixed(0) : 'N/A'}
              detail={`poor < ${analysis.contrast.poor_threshold}`}
              verdict={analysis.contrast.verdict}
            />
            <QualityMetric
              icon={<Sparkle className="w-3 h-3 text-gray-400" />}
              label="Glare / Reflection"
              value={analysis.glare.ratio_pct !== null ? `${analysis.glare.ratio_pct.toFixed(1)}%` : 'N/A'}
              detail={`reject > ${analysis.glare.reject_threshold_pct}%`}
              verdict={analysis.glare.verdict}
            />
            <QualityMetric
              icon={<Waves className="w-3 h-3 text-gray-400" />}
              label="Noise"
              value={analysis.noise.sigma !== null ? analysis.noise.sigma.toFixed(1) : 'N/A'}
              detail={`reject > ${analysis.noise.reject_threshold}`}
              verdict={analysis.noise.verdict}
            />
            <QualityMetric
              icon={<ScanText className="w-3 h-3 text-gray-400" />}
              label="OCR / Text Readability (RapidOCR)"
              value={analysis.ocr_avg_confidence !== null ? `${analysis.ocr_avg_confidence.toFixed(0)}%` : 'N/A'}
              detail={analysis.ocr_text ? `"${analysis.ocr_text}"` : 'No text detected'}
              verdict={analysis.legibility.verdict}
            />
            <QualityMetric
              icon={<Ruler className="w-3 h-3 text-gray-400" />}
              label="Size in Frame"
              value={analysis.text_height_ratio !== null ? `${(analysis.text_height_ratio * 100).toFixed(1)}%` : 'N/A'}
              detail={`of frame height — reject < ${(analysis.size_framing.hard_fail_ratio * 100).toFixed(1)}%`}
              verdict={analysis.size_framing.verdict}
            />
          </div>
        </div>
      )}

      <div className="space-y-1.5">
        <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider flex items-center gap-1">
          <HelpCircle className="w-3 h-3" /> Not Yet Automated
        </span>
        <div className="flex flex-wrap gap-1.5">
          {NOT_YET_AUTOMATED.map((item) => (
            <span
              key={item.label}
              title={`Requires ${item.requires} — not implemented in this system yet`}
              className="inline-flex items-center px-2 py-0.5 rounded-full border text-[9px] font-semibold uppercase bg-gray-50 text-gray-400 border-gray-200"
            >
              {item.label}
            </span>
          ))}
        </div>
      </div>

      {photoFlags.length > 0 && (
        <div className="space-y-2 pt-1">
          <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Findings for This Photo</span>
          <div className="flex flex-wrap gap-2">
            {photoFlags.map((flag, idx) => (
              <FlagBadge key={idx} flag={flag} showDetail />
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
