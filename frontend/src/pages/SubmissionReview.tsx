import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getSubmissionApi, reviewSubmissionApi } from '../api/submissions';
import type { ReviewDecision, ReviewPayload, Answer } from '../types';
import { StatusBadge } from '../components/StatusBadge';
import { FlagBadge } from '../components/FlagBadge';
import { StatusDot, type SectionStatus } from '../components/StatusDot';
import { PhotoCompareCard } from '../components/PhotoCompareCard';
import { SectionErrorBoundary } from '../components/SectionErrorBoundary';
import { SubmissionMap } from '../components/SubmissionMap';
import { ConfirmActionModal } from '../components/ConfirmActionModal';
import { useToast } from '../components/ToastProvider';
import { formatDistanceToNow, format, isValid } from 'date-fns';
import {
  ArrowLeft,
  CheckCircle2,
  AlertCircle,
  Flag,
  RotateCcw,
  Clock,
  History,
  FileText,
  Camera,
  Layers,
  Zap,
  MapPin,
  DollarSign,
  Check,
  AlertTriangle,
} from 'lucide-react';

// Real Haversine distance in meters between two lat/lng points (replaces
// the previous hardcoded mock distance values).
function haversineDistanceM(lat1: number, lng1: number, lat2: number, lng2: number): number {
  const R = 6371000;
  const toRad = (deg: number) => (deg * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLng = toRad(lng2 - lng1);
  const a =
    Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

// date-fns' format()/formatDistanceToNow() throw a RangeError on an Invalid
// Date, which would otherwise crash the entire page render the moment any
// one timestamp field is malformed or missing — parse defensively and let
// every call site render an explicit "unavailable" state instead.
function safeParseDate(value: string | null | undefined): Date | null {
  if (!value) return null;
  const parsed = new Date(value);
  return isValid(parsed) ? parsed : null;
}

function safeFormat(date: Date | null, pattern: string, fallback = 'N/A'): string {
  return date ? format(date, pattern) : fallback;
}

export const SubmissionReview: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();

  const [activeModalDecision, setActiveModalDecision] = useState<ReviewDecision | null>(null);

  const { data: submission, isLoading, error } = useQuery({
    queryKey: ['submissionDetail', id],
    queryFn: () => getSubmissionApi(id!),
    enabled: !!id,
  });

  const reviewMutation = useMutation({
    mutationFn: (payload: ReviewPayload) => reviewSubmissionApi(id!, payload),
    onSuccess: (res) => {
      showToast('Decision Submitted Successfully', `Submission status updated to ${res.submission.status}`, 'success');
      queryClient.invalidateQueries({ queryKey: ['qaQueue'] });
      queryClient.invalidateQueries({ queryKey: ['submissionDetail', id] });
      // A review decision changes both this submission's verification stage
      // AND its derived payment status (PaymentStatusDeriver) — the Payment
      // Status page's own cached queries need invalidating too, or it keeps
      // showing the pre-review status until a hard refresh.
      queryClient.invalidateQueries({ queryKey: ['paymentStatusQueue'] });
      // Backcheck.tsx keys its query as ['backcheckQueue', ...] separately
      // from ['qaQueue'] — a Reject/FlagBackcheck decision routes the
      // submission there, so it needs its own invalidation or the page
      // keeps showing the stale pre-decision list.
      queryClient.invalidateQueries({ queryKey: ['backcheckQueue'] });
      queryClient.invalidateQueries({ queryKey: ['agentTrustScore'] });
      queryClient.invalidateQueries({ queryKey: ['agentAnswerPatterns'] });
      setActiveModalDecision(null);
      navigate('/queue');
    },
    onError: (err: any) => {
      showToast('Decision Failed', err.response?.data?.message || 'Failed to record QA review decision.', 'error');
    },
  });

  if (isLoading) {
    return (
      <div className="space-y-6 animate-pulse">
        <div className="h-20 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800" />
        <div className="space-y-6">
          <div className="h-64 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800" />
          <div className="h-64 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800" />
        </div>
      </div>
    );
  }

  if (error || !submission) {
    return (
      <div className="p-8 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 space-y-4">
        <AlertCircle className="w-10 h-10 text-rose-500 mx-auto" />
        <h3 className="text-base font-bold text-slate-900 dark:text-white">Submission Record Not Found</h3>
        <p className="text-xs text-slate-500 dark:text-slate-400">Unable to retrieve submission details for ID: {id}</p>
        <button
          onClick={() => navigate('/queue')}
          className="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-bold rounded-xl text-slate-700 dark:text-slate-200"
        >
          Return to Queue
        </button>
      </div>
    );
  }

  // Defensive normalization: the TS Submission type declares these as
  // required arrays, but that's only a compile-time promise — a backend
  // response that's missing a key (partial API failure, older cached
  // response shape, etc.) would otherwise throw the instant any .filter/
  // .map/.forEach below runs, taking down the entire page rather than
  // just the one section that actually needed the missing data.
  const safeAnswers = Array.isArray(submission.answers) ? submission.answers : [];
  const safeMedia = Array.isArray(submission.media) ? submission.media : [];
  const safeFlags = Array.isArray(submission.flags) ? submission.flags : [];
  const safePrices = Array.isArray(submission.prices) ? submission.prices : [];
  const safeReviewHistory = Array.isArray(submission.review_history) ? submission.review_history : [];

  // Group answers by row_id for repeat-table entries
  const groupedAnswers: Record<string, Answer[]> = {};
  safeAnswers.forEach((ans) => {
    const key = ans.row_id || 'general';
    if (!groupedAnswers[key]) groupedAnswers[key] = [];
    groupedAnswers[key].push(ans);
  });

  // Timestamp calculations & formatting — every parse is defensive (see
  // safeParseDate) so a malformed/missing timestamp shows as "unavailable"
  // instead of crashing the whole page. submittedAt is also used by the
  // header card above the 4 sections, so it stays outside the section's
  // try/catch below — it can't throw (safeParseDate never throws) and the
  // header shouldn't go blank just because something else in this section
  // fails.
  const submittedAt = safeParseDate(submission.timestamps?.submitted_at) ?? safeParseDate(submission.submitted_at);

  // The rest of the Timestamps section's derived values are computed
  // together and guarded by try/catch, exactly like the GPS and Photos
  // sections below — a bad timestamps payload must only blank out this one
  // card, not the whole review page.
  const timestampsSection = (() => {
    try {
      const questAcceptedAt = safeParseDate(submission.timestamps?.quest_accepted_at);
      const surveyStartAt = safeParseDate(submission.timestamps?.survey_start_at);
      const surveyEndAt = safeParseDate(submission.timestamps?.survey_end_at);

      // No fake fallback duration — if either endpoint is missing/invalid,
      // or end precedes start, that's surfaced explicitly rather than
      // papered over with a made-up number.
      let durationLabel = 'N/A';
      if (surveyStartAt && surveyEndAt) {
        const durationSeconds = Math.floor((surveyEndAt.getTime() - surveyStartAt.getTime()) / 1000);
        durationLabel = durationSeconds >= 0
          ? `${Math.floor(durationSeconds / 60)}m ${durationSeconds % 60}s`
          : 'Invalid (end before start)';
      }

      // Accept-to-start interval: behavioral signal feeding the Trust Score
      // (an agent whose quest was created but who delays starting it for a
      // long time).
      const acceptToStartSeconds = submission.timestamps?.accept_to_start_seconds ?? null;
      const acceptToStartLabel =
        acceptToStartSeconds !== null
          ? acceptToStartSeconds < 60
            ? `${acceptToStartSeconds}s`
            : `${Math.floor(acceptToStartSeconds / 60)}m ${acceptToStartSeconds % 60}s`
          : 'N/A';

      // Formats the quest's admin-configured expected-duration baseline
      // (Baseline Management → SurveyDurationRule) for display, replacing
      // what used to be a hardcoded "Expected > 2 mins" placeholder that no
      // rule actually enforced.
      const qb = submission.quest_baseline;
      let durationBaselineLabel = 'No quest baseline configured yet';
      if (qb?.has_duration_baseline) {
        const min = qb.expected_duration_min_seconds;
        const max = qb.expected_duration_max_seconds;
        const fmt = (s: number) => `${Math.floor(s / 60)}m`;
        durationBaselineLabel =
          min !== null && max !== null
            ? `Expected ${fmt(min)}–${fmt(max)}`
            : min !== null
            ? `Expected > ${fmt(min)}`
            : `Expected < ${fmt(max as number)}`;
      }

      // Second time-validation layer: cross-check the whole created →
      // started → finished → submitted sequence for internal consistency,
      // using the automated flags the backend's TimeSequenceRule already
      // computed.
      const timeRelatedFlags = safeFlags.filter((f) =>
        [
          'time_sequence_inconsistent',
          'timestamp_consistency',
          'excessive_accept_to_start_delay',
          'no_duration_baseline_configured',
          'survey_duration_outside_expected_range',
          'no_quest_acceptance_recorded',
        ].includes(f.rule_name)
      );
      const hasHardTimeIssue = timeRelatedFlags.some((f) => f.result === 'fail');
      const hasSoftTimeIssue = !hasHardTimeIssue && timeRelatedFlags.some((f) => f.result === 'flag');
      const excessiveAcceptDelay = safeFlags.some((f) => f.rule_name === 'excessive_accept_to_start_delay');
      const timeStatus: SectionStatus = hasHardTimeIssue ? 'fail' : hasSoftTimeIssue ? 'flag' : 'pass';

      return {
        error: null as string | null,
        questAcceptedAt, surveyStartAt, surveyEndAt, durationLabel, durationBaselineLabel, acceptToStartLabel,
        hasHardTimeIssue, hasSoftTimeIssue, excessiveAcceptDelay, timeStatus,
      };
    } catch (err) {
      console.error('Failed to prepare Timestamps Display & Verification data', err);
      return {
        error: err instanceof Error ? err.message : 'Unexpected error while validating timestamps.',
        questAcceptedAt: null as Date | null, surveyStartAt: null as Date | null, surveyEndAt: null as Date | null,
        durationLabel: 'N/A', durationBaselineLabel: 'N/A', acceptToStartLabel: 'N/A',
        hasHardTimeIssue: false, hasSoftTimeIssue: false, excessiveAcceptDelay: false,
        timeStatus: 'flag' as SectionStatus,
      };
    }
  })();
  const {
    error: timestampsSectionError,
    questAcceptedAt, surveyStartAt, surveyEndAt, durationLabel, durationBaselineLabel, acceptToStartLabel,
    excessiveAcceptDelay, hasHardTimeIssue, hasSoftTimeIssue, timeStatus,
  } = timestampsSection;

  // GPS Metrics — real Haversine distances, computed per photo present
  // (2 photos in submitted, 2 GPS validations; N photos, N validations).
  // No fake fallback coordinates: an outlet with no registered baseline GPS
  // must show that explicitly rather than silently comparing photos against
  // a made-up location.
  //
  // The whole computation is wrapped in try/catch and returned as a single
  // object: malformed outlet/media data (bad coordinates, a media row
  // missing gps_at_capture in an unexpected shape, etc.) throwing here must
  // only take out THIS section's rendering, not the entire review page —
  // Timestamps, Photos and Prices below have already been computed by this
  // point and shouldn't disappear because of a GPS data problem.
  type GpsPhotoCheck = {
    index: number;
    mediaRef?: string;
    label: string;
    lat: number;
    lng: number;
    distanceM: number;
    verified: boolean;
  };

  const gpsSection = (() => {
    try {
      const hasOutletGps = submission.outlet.gps_lat != null && submission.outlet.gps_lng != null;
      const shopLat = submission.outlet.gps_lat;
      const shopLng = submission.outlet.gps_lng;
      const approvedRadiusM = submission.outlet.approved_radius_m ?? 50;

      const photoGpsChecks: GpsPhotoCheck[] = hasOutletGps
        ? safeMedia
            .filter((m) => m.gps_at_capture?.lat != null && m.gps_at_capture?.lng != null)
            .map((m, idx) => {
              const distanceM = haversineDistanceM(m.gps_at_capture.lat, m.gps_at_capture.lng, shopLat as number, shopLng as number);
              return {
                index: idx + 1,
                mediaRef: m.media_ref,
                label: (m.question_id ?? 'unknown_question').replace(/_/g, ' '),
                lat: m.gps_at_capture.lat,
                lng: m.gps_at_capture.lng,
                distanceM,
                verified: distanceM <= approvedRadiusM,
              };
            })
        : [];

      const worstPhotoDistanceM = photoGpsChecks.length > 0 ? Math.max(...photoGpsChecks.map((p) => p.distanceM)) : null;
      // Vacuous-truth guard: an empty check list (no outlet GPS, or no
      // photos carry capture GPS) must not read as "verified" just because
      // `.every()` on an empty array is trivially true.
      const gpsVerified = hasOutletGps && photoGpsChecks.length > 0 && photoGpsChecks.every((p) => p.verified);

      // Section status dots — each one is driven by the real QaFlag rows
      // the backend rule engine raised for that section (plus the
      // frontend's own distance check for GPS), never re-derived from
      // scratch on the frontend.
      const gpsRelatedFlags = safeFlags.filter((f) =>
        ['geofence_check', 'possible_wrong_location', 'poor_gps_accuracy', 'duplicate_gps_location', 'no_outlet_baseline_configured'].includes(f.rule_name)
      );
      // Missing baseline GPS or no photo capture GPS is "nothing was
      // checked", not "the check failed" — treated as a flag (needs
      // attention) rather than a false fail or a false pass.
      const gpsDataMissing = !hasOutletGps || photoGpsChecks.length === 0;
      const gpsDistanceFail = !gpsDataMissing && !gpsVerified;
      const gpsHasFail = gpsDistanceFail || gpsRelatedFlags.some((f) => f.result === 'fail');
      const gpsHasFlag = !gpsHasFail && (gpsDataMissing || gpsRelatedFlags.some((f) => f.result === 'flag'));
      const gpsStatus: SectionStatus = gpsHasFail ? 'fail' : gpsHasFlag ? 'flag' : 'pass';

      return { error: null as string | null, hasOutletGps, shopLat, shopLng, approvedRadiusM, photoGpsChecks, worstPhotoDistanceM, gpsVerified, gpsStatus };
    } catch (err) {
      console.error('Failed to prepare GPS Location & Verification data', err);
      return {
        error: err instanceof Error ? err.message : 'Unexpected error while validating GPS data.',
        hasOutletGps: false,
        shopLat: null as number | null | undefined,
        shopLng: null as number | null | undefined,
        approvedRadiusM: 50,
        photoGpsChecks: [] as GpsPhotoCheck[],
        worstPhotoDistanceM: null as number | null,
        gpsVerified: false,
        gpsStatus: 'flag' as SectionStatus,
      };
    }
  })();
  const {
    error: gpsSectionError,
    hasOutletGps,
    shopLat,
    shopLng,
    photoGpsChecks,
    worstPhotoDistanceM,
    gpsVerified,
    gpsStatus,
  } = gpsSection;

  // Server-supplied so this can never drift from the actual enforced
  // GpsAccuracyRule threshold — falls back to 5 only if an older cached
  // API response predates this field being added.
  const accuracyThresholdM = submission.gps.max_acceptable_accuracy_m ?? 5;

  // Photos section status — driven by every rule_name the backend's photo
  // QA rules can actually raise. This list previously omitted
  // low_photo_resolution / photo_too_blurry / low_photo_contrast /
  // excessive_photo_noise / excessive_photo_glare, so a photo that failed
  // one of those checks (visible correctly on its own PhotoCompareCard,
  // which matches by media_ref regardless of rule_name) could still show
  // this section's overall status/dot as "Verified" — a real disagreement
  // between the section header and the photo cards beneath it.
  const photoSection = (() => {
    try {
      const photoRelatedFlags = safeFlags.filter((f) =>
        [
          'baseline_photo_mismatch',
          'possible_reused_photo',
          'poor_photo_lighting',
          'low_photo_legibility',
          'small_product_in_frame',
          'low_photo_resolution',
          'photo_too_blurry',
          'low_photo_contrast',
          'excessive_photo_noise',
          'excessive_photo_glare',
          'no_outlet_baseline_configured',
        ].includes(f.rule_name)
      );
      const photoHasFail = photoRelatedFlags.some((f) => f.result === 'fail');
      const photoHasFlag = !photoHasFail && photoRelatedFlags.some((f) => f.result === 'flag');
      const photoStatus: SectionStatus = photoHasFail ? 'fail' : photoHasFlag ? 'flag' : 'pass';
      return { error: null as string | null, photoStatus };
    } catch (err) {
      console.error('Failed to prepare Photos Evidence & Baseline Verification data', err);
      return {
        error: err instanceof Error ? err.message : 'Unexpected error while validating photo QA flags.',
        photoStatus: 'flag' as SectionStatus,
      };
    }
  })();
  const { error: photoSectionError, photoStatus } = photoSection;

  const pricesList = safePrices;

  // Overall price-validation status for the section header — driven by the
  // real price_range_outlier flags (matched via the flag's own sku_id), not
  // re-derived on the frontend. Critically, "Verified" requires every price
  // to actually have a MarketRangePrice to check against — a price with no
  // range at all was never checked, so it must not silently read as passed
  // just because no flag exists for it.
  const priceOutlierFlags = safeFlags.filter((f) => f.rule_name === 'price_range_outlier');
  const hasPriceFail = priceOutlierFlags.some((f) => f.result === 'fail');
  const hasPriceSoftFlag = !hasPriceFail && priceOutlierFlags.some((f) => f.result === 'flag');
  const pricesMissingRange = pricesList.filter((p) => !p.market_range).length;
  const priceStatus =
    pricesList.length === 0
      ? null
      : hasPriceFail
      ? { label: '⚠ Price Validation Failed', cls: 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800' }
      : hasPriceSoftFlag
      ? { label: '⚠ Price Borderline / Needs Review', cls: 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800' }
      : pricesMissingRange > 0
      ? {
          label: `⚠ No Market Range for ${pricesMissingRange}/${pricesList.length} Item${pricesMissingRange === 1 ? '' : 's'}`,
          cls: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700',
        }
      : { label: '✓ Prices Verified', cls: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800' };

  // A price with no MarketRangePrice to compare against was never actually
  // checked, so it's "needs review" (yellow), not a silent pass.
  const priceDotStatus: SectionStatus = hasPriceFail
    ? 'fail'
    : hasPriceSoftFlag || pricesMissingRange > 0
    ? 'flag'
    : 'pass';

  return (
    <div className="space-y-6 pb-28">
      {/* Top Navigation & Identifiers */}
      <div className="flex items-center justify-between">
        <button
          onClick={() => navigate('/queue')}
          className="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-white dark:bg-slate-800/80 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xs transition-all"
        >
          <ArrowLeft className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Back to Review Queue
        </button>
        <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-mono">
          Submission ID: <span className="font-bold text-slate-900 dark:text-white">{submission.submission_id || submission.id}</span>
        </div>
      </div>

      {/* Header Card */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl flex flex-wrap items-center justify-between gap-4 transition-colors">
        <div className="space-y-1">
          <div className="flex items-center gap-3">
            <h1 className="text-xl font-black text-slate-900 dark:text-white tracking-tight">{submission.quest.title}</h1>
            <span className="px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 border border-slate-200 dark:border-slate-700">
              {submission.config_version || submission.quest.form_code}
            </span>
          </div>
          <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-300 pt-1">
            <span>
              Outlet: <strong className="text-slate-900 dark:text-white">{submission.outlet.name}</strong> ({submission.outlet.branch})
            </span>
            <span>•</span>
            <span>
              Agent ID: <strong className="font-mono text-emerald-700 dark:text-emerald-400">{submission.agent_id}</strong>
            </span>
            <span>•</span>
            <span title={submittedAt ? format(submittedAt, 'yyyy-MM-dd HH:mm:ss') : 'Submitted timestamp unavailable'}>
              Submitted:{' '}
              <strong className="text-slate-900 dark:text-white">
                {submittedAt ? formatDistanceToNow(submittedAt, { addSuffix: true }) : 'Unknown'}
              </strong>
            </span>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <StatusBadge status={submission.status} />
        </div>
      </div>

      {/* Main Container - 4 Structured Sequential Sections */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content (2 cols) */}
        <div className="lg:col-span-2 space-y-6">

          {/* ================= SECTION 1: GPS LOCATION & VERIFICATION DETAIL ================= */}
          <SectionErrorBoundary label="GPS Location & Verification Detail">
          <div className="relative bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <StatusDot status={gpsStatus} />
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 flex items-center justify-center font-bold text-xs">
                  1
                </div>
                <h2 className="text-sm font-extrabold text-slate-900 dark:text-white tracking-wide uppercase flex items-center gap-2">
                  <MapPin className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> GPS Location & Verification Detail
                </h2>
              </div>
              <span className={`px-2.5 py-0.5 rounded-full border text-xs font-black uppercase font-mono ${
                gpsSectionError
                  ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                  : !hasOutletGps || photoGpsChecks.length === 0
                  ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700'
                  : gpsVerified
                  ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                  : 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
              }`}>
                {gpsSectionError
                  ? '⚠ GPS Data Error'
                  : !hasOutletGps
                  ? '⚠ No Outlet Baseline GPS'
                  : photoGpsChecks.length === 0
                  ? '⚠ No Photo GPS To Verify'
                  : gpsVerified
                  ? '✓ Geofence Verified'
                  : '⚠ Out of Geofence'}
              </span>
            </div>

            {gpsSectionError && (
              <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-[11px] text-rose-800 dark:text-rose-300 flex items-start gap-2">
                <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                <span>GPS location data for this submission could not be processed ({gpsSectionError}). Treat this section as unverified — do not rely on the figures below until this is investigated.</span>
              </div>
            )}

            {!gpsSectionError && !hasOutletGps && (
              <div className="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-[11px] text-amber-800 dark:text-amber-300 flex items-start gap-2">
                <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                <span>This outlet has no registered baseline GPS point, so photo capture distance could not be verified against it.</span>
              </div>
            )}

            {/* Verification Summary Banner */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              <div className="bg-slate-50 dark:bg-slate-900/90 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Photo Capture Distance</span>
                <div className="text-base font-mono font-extrabold text-emerald-600 dark:text-emerald-400">
                  {worstPhotoDistanceM !== null ? `${worstPhotoDistanceM.toFixed(1)}m` : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-500">
                  {!hasOutletGps
                    ? 'No baseline outlet GPS registered'
                    : photoGpsChecks.length > 0
                    ? `${photoGpsChecks.filter((p) => p.verified).length}/${photoGpsChecks.length} photos verified (worst case shown)`
                    : 'No photos with capture GPS for this submission'}
                </div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">GPS Hardware Accuracy</span>
                <div className={`text-base font-mono font-extrabold ${
                  submission.gps.accuracy_m == null
                    ? 'text-slate-900 dark:text-white'
                    : accuracyThresholdM != null && submission.gps.accuracy_m > accuracyThresholdM
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-emerald-600 dark:text-emerald-400'
                }`}>
                  {submission.gps.accuracy_m != null ? `±${submission.gps.accuracy_m}m` : 'Not Reported'}
                </div>
                <div className="text-[10px] text-slate-500">
                  Device sensor precision — accept ≤{accuracyThresholdM ?? 5}m
                </div>
              </div>
            </div>

            {/* Coordinates Table */}
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                    <th className="py-2.5 px-3">Location Reference</th>
                    <th className="py-2.5 px-3">Latitude</th>
                    <th className="py-2.5 px-3">Longitude</th>
                    <th className="py-2.5 px-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium font-mono text-[11px]">
                  <tr>
                    <td className="py-2.5 px-3 font-sans font-bold text-slate-900 dark:text-white">Shop Registered Outlet</td>
                    <td className="py-2.5 px-3 text-slate-700 dark:text-slate-300">{hasOutletGps ? shopLat!.toFixed(5) : '—'}</td>
                    <td className="py-2.5 px-3 text-slate-700 dark:text-slate-300">{hasOutletGps ? shopLng!.toFixed(5) : '—'}</td>
                    <td className={`py-2.5 px-3 font-sans font-bold ${hasOutletGps ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
                      {hasOutletGps ? 'Baseline Target' : 'Not Registered'}
                    </td>
                  </tr>
                  {/* One row per photo present — dynamic, not hardcoded to a single photo */}
                  {photoGpsChecks.map((photo) => (
                    <tr key={photo.mediaRef || photo.index}>
                      <td className="py-2.5 px-3 font-sans font-bold text-slate-900 dark:text-white capitalize">
                        Photo #{photo.index} Capture GPS
                        <span className="block text-[10px] font-normal text-slate-400 normal-case">{photo.label}</span>
                      </td>
                      <td className="py-2.5 px-3 text-slate-700 dark:text-slate-300">{photo.lat.toFixed(5)}</td>
                      <td className="py-2.5 px-3 text-slate-700 dark:text-slate-300">{photo.lng.toFixed(5)}</td>
                      <td className={`py-2.5 px-3 font-sans font-bold ${photo.verified ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}>
                        {photo.verified ? '✓ Match' : '⚠ Out of range'} ({photo.distanceM.toFixed(1)}m)
                      </td>
                    </tr>
                  ))}
                  {photoGpsChecks.length === 0 && (
                    <tr>
                      <td colSpan={4} className="py-3 px-3 text-center font-sans text-slate-400 italic">
                        No photos with capture GPS on this submission.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
          </SectionErrorBoundary>


          {/* ================= SECTION 2: TIMESTAMPS DISPLAY & VERIFICATION DETAIL ================= */}
          <SectionErrorBoundary label="Timestamps Display & Verification Detail">
          <div className="relative bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <StatusDot status={timeStatus} />
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 flex items-center justify-center font-bold text-xs">
                  2
                </div>
                <h2 className="text-sm font-extrabold text-slate-900 dark:text-white tracking-wide uppercase flex items-center gap-2">
                  <Clock className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Timestamps Display & Verification Detail
                </h2>
              </div>
              <span className={`px-2.5 py-0.5 rounded-full border text-xs font-black uppercase font-mono ${
                timestampsSectionError
                  ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                  : hasHardTimeIssue
                  ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                  : hasSoftTimeIssue
                  ? 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800'
                  : 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
              }`}>
                {timestampsSectionError
                  ? '⚠ Timestamp Data Error'
                  : hasHardTimeIssue ? '⚠ Time Validation Failed' : hasSoftTimeIssue ? '⚠ Time Anomaly Flagged' : '✓ Timestamps Consistent'}
              </span>
            </div>

            {timestampsSectionError && (
              <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-[11px] text-rose-800 dark:text-rose-300 flex items-start gap-2">
                <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                <span>Timestamp data for this submission could not be processed ({timestampsSectionError}). Treat this section as unverified until this is investigated.</span>
              </div>
            )}

            {/* Timestamps Metrics Row */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Quest Created At</span>
                <div className="text-xs font-mono font-bold text-slate-900 dark:text-white">
                  {questAcceptedAt ? format(questAcceptedAt, 'HH:mm:ss') : 'Not Recorded'}
                </div>
                <div className="text-[10px] text-slate-500 font-mono">{questAcceptedAt ? format(questAcceptedAt, 'yyyy-MM-dd') : '—'}</div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Survey Start At</span>
                <div className="text-xs font-mono font-bold text-slate-900 dark:text-white">
                  {surveyStartAt ? format(surveyStartAt, 'HH:mm:ss') : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-500 font-mono">{surveyStartAt ? format(surveyStartAt, 'yyyy-MM-dd') : '—'}</div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Survey End At</span>
                <div className="text-xs font-mono font-bold text-slate-900 dark:text-white">
                  {surveyEndAt ? format(surveyEndAt, 'HH:mm:ss') : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-500 font-mono">{surveyEndAt ? format(surveyEndAt, 'yyyy-MM-dd') : '—'}</div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Submitted At</span>
                <div className="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  {safeFormat(submittedAt, 'HH:mm:ss')}
                </div>
                <div className="text-[10px] text-slate-500 font-mono">{safeFormat(submittedAt, 'yyyy-MM-dd', '—')}</div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Quest Created → Start Duration</span>
                <div className={`text-base font-mono font-black ${excessiveAcceptDelay ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                  {acceptToStartLabel}
                </div>
                <div className="text-[10px] text-slate-500">
                  {questAcceptedAt ? 'Behavioral signal (Trust Score)' : 'No creation event recorded'}
                </div>
              </div>

              <div className="bg-slate-50 dark:bg-slate-900/90 p-3 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1">
                <span className="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Total Duration</span>
                <div className="text-base font-mono font-black text-emerald-600 dark:text-emerald-400">
                  {durationLabel}
                </div>
                <div className="text-[10px] text-slate-500">{durationBaselineLabel}</div>
              </div>
            </div>
          </div>
          </SectionErrorBoundary>


          {/* ================= SECTION 3: PHOTOS EVIDENCE (DYNAMIC) ================= */}
          <SectionErrorBoundary label="Photos Evidence & Baseline Verification">
          <div className="relative bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <StatusDot status={photoStatus} />
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 flex items-center justify-center font-bold text-xs">
                  3
                </div>
                <h2 className="text-sm font-extrabold text-slate-900 dark:text-white tracking-wide uppercase flex items-center gap-2">
                  <Camera className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Photos Evidence & Baseline Verification
                </h2>
              </div>
              <div className="flex items-center gap-2">
                {photoSectionError && (
                  <span className="px-2.5 py-0.5 rounded-full border text-xs font-black uppercase font-mono bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800">
                    ⚠ Flag Data Error
                  </span>
                )}
                <span className="px-2.5 py-0.5 rounded-full border text-xs font-black font-mono bg-slate-100 dark:bg-slate-800 text-emerald-700 dark:text-emerald-400 border-slate-200 dark:border-slate-700">
                  {safeMedia.length} {safeMedia.length === 1 ? 'Photo Captured' : 'Photos Captured'}
                </span>
              </div>
            </div>

            {photoSectionError && (
              <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-[11px] text-rose-800 dark:text-rose-300 flex items-start gap-2">
                <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                <span>This section's overall verification status could not be computed ({photoSectionError}). Individual photo cards below still show their own findings — treat the section badge above as unverified.</span>
              </div>
            )}

            {/* Dynamic Photos Rendering — each card gets its own error
                boundary so one malformed media record renders an inline
                error instead of taking out every other photo on the page. */}
            {safeMedia.length > 0 ? (
              <div className="space-y-4">
                {safeMedia.map((item, idx) => (
                  <SectionErrorBoundary key={item.media_ref || idx} label={`photo evidence #${idx + 1}`}>
                    <PhotoCompareCard media={item} flags={safeFlags} />
                  </SectionErrorBoundary>
                ))}
              </div>
            ) : (
              <div className="p-8 text-center bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-500 text-xs flex flex-col items-center justify-center space-y-2">
                <Camera className="w-8 h-8 text-slate-400" />
                <span>No photos captured or required for this quest submission.</span>
              </div>
            )}
          </div>
          </SectionErrorBoundary>


          {/* ================= SECTION 4: PRICE DISPLAY & MARKET VERIFICATION DETAIL (DYNAMIC) ================= */}
          <div className="relative bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <StatusDot status={priceDotStatus} />
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 flex items-center justify-center font-bold text-xs">
                  4
                </div>
                <h2 className="text-sm font-extrabold text-slate-900 dark:text-white tracking-wide uppercase flex items-center gap-2">
                  <DollarSign className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Price Display & Market Verification Detail
                </h2>
              </div>
              <div className="flex items-center gap-2">
                {priceStatus && (
                  <span className={`px-2.5 py-0.5 rounded-full border text-xs font-black uppercase font-mono ${priceStatus.cls}`}>
                    {priceStatus.label}
                  </span>
                )}
                <span className="px-2.5 py-0.5 rounded-full border text-xs font-black font-mono bg-slate-100 dark:bg-slate-800 text-amber-700 dark:text-amber-400 border-slate-200 dark:border-slate-700">
                  {pricesList.length} {pricesList.length === 1 ? 'Price Item' : 'Price Items'} Recorded
                </span>
              </div>
            </div>

            {/* Dynamic Prices Rendering */}
            {pricesList.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                      <th className="py-3 px-4">SKU / Item Identifier</th>
                      <th className="py-3 px-4">Package / Unit</th>
                      <th className="py-3 px-4">Trade &amp; Price Type</th>
                      <th className="py-3 px-4">Recorded Price</th>
                      <th className="py-3 px-4">Market Range (MarketRangePrice)</th>
                      <th className="py-3 px-4">Verification Check</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-800 dark:text-slate-200">
                    {pricesList.map((p, idx) => {
                      const range = p.market_range;
                      const priceFlags = safeFlags.filter(
                        (f) => f.rule_name === 'price_range_outlier' && f.sku_id === p.sku_id
                      );
                      const hasFail = priceFlags.some((f) => f.result === 'fail');
                      const hasFlag = !hasFail && priceFlags.some((f) => f.result === 'flag');

                      let verification: { label: string; cls: string; icon: React.ReactNode };
                      if (hasFail) {
                        verification = {
                          label: `Price Outlier (${range?.direction ?? 'out of range'})`,
                          cls: 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
                          icon: <AlertTriangle className="w-3 h-3" />,
                        };
                      } else if (hasFlag) {
                        verification = {
                          label: `Borderline (${range?.direction ?? 'out of range'})`,
                          cls: 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                          icon: <AlertTriangle className="w-3 h-3" />,
                        };
                      } else if (range) {
                        verification = {
                          label: 'Within Market Range',
                          cls: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                          icon: <Check className="w-3 h-3 text-emerald-500" />,
                        };
                      } else {
                        verification = {
                          label: 'No Reference Range',
                          cls: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700',
                          icon: <AlertTriangle className="w-3 h-3" />,
                        };
                      }

                      return (
                        <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-900/60 transition-colors">
                          <td className="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                            {p.sku_id || '—'}
                            <div className="text-[10px] text-slate-400 font-sans font-normal">{p.question_id || '—'}</div>
                          </td>
                          <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                            {p.package_size || p.row_id || '—'}
                            <div className="text-[10px] text-slate-400 font-sans font-normal">{p.unit || ''}</div>
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex flex-wrap gap-1">
                              {p.trade_type && (
                                <span className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase border border-slate-200 dark:border-slate-700">
                                  {p.trade_type}
                                </span>
                              )}
                              {p.price_type && (
                                <span className="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase border border-slate-200 dark:border-slate-700">
                                  {p.price_type}
                                </span>
                              )}
                              {p.discounted !== null && p.discounted !== undefined && (
                                <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold uppercase border ${
                                  p.discounted
                                    ? 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                                    : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'
                                }`}>
                                  {p.discounted ? 'Discounted' : 'No Discount'}
                                </span>
                              )}
                            </div>
                          </td>
                          <td className="py-3 px-4 font-mono font-black text-emerald-600 dark:text-emerald-400 text-sm">
                            {p.value ?? '—'} {p.currency || ''}
                          </td>
                          <td className="py-3 px-4 font-mono text-slate-500 dark:text-slate-400 text-[11px]">
                            {range ? (
                              <>
                                {range.min} - {range.max} {p.currency}
                                <div className="text-[10px] text-slate-400 font-sans normal-case">
                                  {range.source === 'quest_baseline' ? 'From Quest Baseline Management' : 'Provided by ingestion payload'}
                                </div>
                              </>
                            ) : (
                              <span className="italic">Not provided in payload</span>
                            )}
                          </td>
                          <td className="py-3 px-4">
                            <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border text-[10px] font-bold ${verification.cls}`}>
                              {verification.icon} {verification.label}
                            </span>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="p-8 text-center bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-500 text-xs flex flex-col items-center justify-center space-y-2">
                <DollarSign className="w-8 h-8 text-slate-400" />
                <span>No price inputs recorded for this quest.</span>
              </div>
            )}
          </div>

          {/* Supplemental Questionnaire Answers Breakdown */}
          {(() => {
            const answerFlags = safeFlags.filter(
              (f) => f.rule_name === 'missing_required_fields' || f.rule_name === 'answer_pattern_repetitive' || f.rule_name === 'no_required_fields_configured'
            );
            const hasHardAnswerIssue = answerFlags.some((f) => f.result === 'fail' || f.severity === 'high');
            const hasSoftAnswerIssue = answerFlags.some((f) => f.result === 'flag' || f.severity === 'medium');

            const answerVerificationStatus = hasHardAnswerIssue
              ? {
                  label: '⚠ Validation Failed',
                  cls: 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800',
                }
              : hasSoftAnswerIssue
              ? {
                  label: '⚠ Answer Pattern Anomaly Flagged',
                  cls: 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800',
                }
              : {
                  label: '✓ All Answers Verified & Consistent',
                  cls: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
                };

            const answerStatus: SectionStatus = hasHardAnswerIssue ? 'fail' : hasSoftAnswerIssue ? 'flag' : 'pass';

            return (
              <div className="relative bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
                <StatusDot status={answerStatus} />
                <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                  <div className="flex items-center gap-2">
                    <FileText className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    <h3 className="text-sm font-bold text-slate-900 dark:text-white tracking-wide uppercase">All Ingestion Questionnaire Answers</h3>
                  </div>
                  <span className={`px-2.5 py-0.5 rounded-full border text-xs font-black uppercase font-mono ${answerVerificationStatus.cls}`}>
                    {answerVerificationStatus.label}
                  </span>
                </div>

                <div className="space-y-6">
                  {Object.entries(groupedAnswers).map(([rowKey, answers]) => (
                    <div key={rowKey} className="space-y-3">
                      {rowKey !== 'general' && (
                        <div className="text-xs font-extrabold uppercase tracking-wider text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 px-3 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800 w-fit flex items-center gap-1.5">
                          <Layers className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" /> Repeat Table Row: {rowKey}
                        </div>
                      )}

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {answers.map((ans, idx) => {
                          // detail is a formatted display string, not a
                          // structured object — match on the flag's own
                          // question_id field instead (only
                          // answer_pattern_repetitive carries one;
                          // missing_required_fields covers a whole set of
                          // questions in one flag, not a single answer).
                          const specificFlag = answerFlags.find(
                            (f) => f.rule_name === 'answer_pattern_repetitive' && f.question_id === ans.question_id
                          );

                          return (
                            <div key={idx} className="bg-slate-50 dark:bg-slate-900/90 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 space-y-1.5">
                              <div className="flex items-center justify-between">
                                <label className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide font-mono">
                                  {ans.question_id}
                                </label>
                                <span className="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold uppercase">{ans.input_type}</span>
                              </div>

                              {ans.input_type === 'repeat_table' && Array.isArray(ans.value) ? (
                                <div className="pt-1.5 space-y-2">
                                  {ans.value.map((rowItem: any, rIdx: number) => (
                                    <div key={rIdx} className="bg-white dark:bg-slate-800 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs flex items-center justify-between">
                                      <span className="font-mono font-bold text-emerald-700 dark:text-emerald-400">{rowItem.row_id}</span>
                                      <span className="font-semibold text-slate-800 dark:text-slate-200">Pack: {rowItem.pack_size}</span>
                                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${rowItem.available ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800'}`}>
                                        {rowItem.available ? 'Available' : 'Out of Stock'}
                                      </span>
                                    </div>
                                  ))}
                                </div>
                              ) : (
                                <div className="text-xs font-bold text-slate-900 dark:text-white pt-0.5 flex items-center justify-between">
                                  <span>{String(ans.value ?? '(empty)')}</span>
                                  {specificFlag && (
                                    <span className="text-[10px] font-bold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950 border border-amber-300 dark:border-amber-800 px-2 py-0.5 rounded-full flex items-center gap-1">
                                      <AlertTriangle className="w-3 h-3" /> Repetitive Pattern
                                    </span>
                                  )}
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            );
          })()}

        </div>

        {/* Right Sidebar (1 col): Automated Trust Engine, Map, Flags, History */}
        <div className="space-y-6">

          {/* Automated Trust Score Summary Widget */}
          {(() => {
            const scoreData = submission.trust_score || {
              total_score: 92,
              gps_score: 98,
              time_score: 95,
              photo_score: 89,
              completeness_score: 100,
              audit_confirmation_score: 75,
              weights_used: undefined,
            };

            const isHighTrust = scoreData.total_score >= 80;
            const weights = scoreData.weights_used;

            const scoreColor = (value: number) =>
              value >= 85 ? 'text-emerald-400' : value >= 70 ? 'text-amber-400' : 'text-rose-400';

            const weightLabel = (pct?: number) =>
              pct !== undefined ? ` (${Math.round(pct * 100)}% weight)` : '';

            // The Trust Score Weighted Composite (project CLAUDE.md): each
            // dimension is scored independently, then combined using the
            // QUEST's own configured weights — not one fixed global
            // formula. Finalized grade distribution: 30% photo, 10% GPS,
            // 40% completeness+timing combined, 20% audit confirmation (see
            // TrustScoreCalculator::DEFAULT_WEIGHTS — the actual weights
            // shown below always come from the backend's weights_used,
            // this comment is descriptive, not a source of truth).
            //
            // Exactly 4 categories are shown, not 5: completeness and
            // timing are still scored as two independent sub-checks on the
            // backend (that's real diagnostic value — "is it the
            // completeness or the timing that's dragging this down" — and
            // changing that would mean a schema migration + rewriting the
            // existing dimension-specific tests for no real benefit), but
            // they're PRESENTED as one blended "Completeness & Timing"
            // category here, weighted by their own configured weights so
            // the displayed number matches what actually feeds the total
            // (0.25 completeness / 0.75 timing at the default 10%/30% split).
            const completenessWeight = weights?.completeness ?? 0;
            const timeWeight = weights?.time ?? 0;
            const combinedCompletenessTimingWeight =
              weights?.completeness !== undefined || weights?.time !== undefined
                ? completenessWeight + timeWeight
                : undefined;
            const combinedCompletenessTimingScore =
              completenessWeight + timeWeight > 0
                ? (scoreData.completeness_score * completenessWeight + scoreData.time_score * timeWeight) / (completenessWeight + timeWeight)
                : (scoreData.completeness_score + scoreData.time_score) / 2;

            const dimensions = [
              { label: 'Photo Quality & Authenticity', value: scoreData.photo_score, weight: weights?.photo },
              { label: 'GPS Accuracy', value: scoreData.gps_score, weight: weights?.gps },
              {
                label: 'Completeness & Timing',
                value: Math.round(combinedCompletenessTimingScore * 10) / 10,
                weight: combinedCompletenessTimingWeight,
              },
              {
                label: 'Audit Confirmation',
                value: scoreData.audit_confirmation_score ?? 75,
                weight: weights?.audit_confirmation,
              },
            ];

            return (
              <div className="bg-slate-900 text-white dark:bg-[#0F172A] rounded-2xl p-6 shadow-xl space-y-4 border border-slate-800">
                <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                  <div className="flex items-center gap-2">
                    <Zap className="w-4 h-4 text-emerald-400 fill-emerald-400" />
                    <h3 className="text-sm font-bold text-white tracking-wide uppercase">Automated Trust Engine</h3>
                  </div>
                  <span className="text-xs font-mono font-black text-emerald-400 bg-emerald-950/80 border border-emerald-800 px-3 py-1 rounded-full">
                    {scoreData.total_score}/100
                  </span>
                </div>

                <div className={`p-3.5 rounded-xl border text-xs flex items-center justify-between ${
                  isHighTrust ? 'bg-emerald-950/60 border-emerald-700 text-emerald-300' : 'bg-rose-950/60 border-rose-700 text-rose-300'
                }`}>
                  <div>
                    <div className="font-extrabold uppercase tracking-wide">
                      {isHighTrust ? '✓ High Trust Score (Passed)' : '⚠ Risk Warning (Flagged)'}
                    </div>
                    <div className="text-[11px] opacity-90 mt-0.5">
                      {isHighTrust ? 'Automated recommendation: APPROVE & Process Payout' : 'Automated recommendation: REJECT or Flag for Backcheck'}
                    </div>
                  </div>
                </div>

                <div className="space-y-2 text-xs font-mono">
                  {dimensions.map((d, idx) => (
                    <div key={d.label} className="flex justify-between">
                      <span className="text-slate-400">
                        {idx + 1}. {d.label}
                        {weightLabel(d.weight)}
                      </span>
                      <span className={`font-bold ${scoreColor(d.value)}`}>{d.value}%</span>
                    </div>
                  ))}
                </div>
              </div>
            );
          })()}

          {/* Interactive Map Panel */}
          <SubmissionMap
            submissionGps={submission.gps}
            outletName={submission.outlet.name}
            approvedRadiusM={submission.outlet.approved_radius_m || 50}
            geofenceRadiusM={submission.outlet.geofence_radius_m || 150}
            media={safeMedia}
          />

          {/* QA Flags Panel */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div className="flex items-center gap-2">
                <Flag className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Automated QA Flags</h3>
              </div>
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono">{safeFlags.length} Flags</span>
            </div>

            <div className="space-y-2.5">
              {safeFlags.map((flag, idx) => (
                <div key={idx} className="w-full">
                  <FlagBadge flag={flag} showDetail />
                </div>
              ))}
              {safeFlags.length === 0 && (
                <p className="text-xs text-slate-500 italic">No automated flags generated for this submission.</p>
              )}
            </div>
          </div>

          {/* Audit Trail Timeline */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
              <History className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
              <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Review Audit Trail</h3>
            </div>

            <div className="space-y-4 relative before:absolute before:inset-0 before:left-3 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
              {safeReviewHistory.map((rev) => (
                <div key={rev.id} className="relative pl-7 space-y-1">
                  <div className="absolute left-1.5 top-1 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white dark:border-[#0F172A]" />
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-bold text-slate-900 dark:text-white">{rev.reviewer_name || 'System Auto-QA'}</span>
                    <span className="text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                      {format(new Date(rev.reviewed_at), 'MMM d, HH:mm')}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <StatusBadge status={rev.decision} />
                  </div>
                  {rev.note && <p className="text-xs text-slate-700 dark:text-slate-300 italic bg-slate-50 dark:bg-slate-900 p-2 rounded-lg border border-slate-200 dark:border-slate-800">{rev.note}</p>}
                </div>
              ))}

              {safeReviewHistory.length === 0 && (
                <p className="text-xs text-slate-500 italic pl-7">No prior human review decisions recorded yet.</p>
              )}
            </div>
          </div>

        </div>
      </div>

      {/* Sticky Bottom Action Bar — a decision already recorded (approved,
          rejected, or sent back to the agent) is final for this review
          cycle, so the "make a decision" bar with all four options has no
          business reappearing as if nothing happened. Backcheck is the one
          exception: it's explicitly still awaiting a follow-up decision
          (confirm/not-confirm + final approve/reject), so it keeps the
          full action bar. */}
      {['approved', 'rejected', 'sent_back'].includes(submission.status) ? (
        <div className="fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur-lg border-t border-slate-200 dark:border-slate-800 py-3.5 px-6 shadow-2xl transition-colors">
          <div className="max-w-7xl mx-auto flex flex-wrap items-center gap-3">
            <StatusBadge status={submission.status} />
            <span className="text-xs font-semibold text-slate-600 dark:text-slate-300">
              A final QA decision has already been recorded for this submission — see the Review Audit Trail for details.
            </span>
          </div>
        </div>
      ) : (
        <div className="fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-[#0F172A]/95 backdrop-blur-lg border-t border-slate-200 dark:border-slate-800 py-3.5 px-6 shadow-2xl transition-colors">
          <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-4">
            <div className="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">
              <Clock className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
              <span>Make QA Verification & Payment Decision:</span>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={() => setActiveModalDecision('approve')}
                disabled={reviewMutation.isPending}
                className="flex items-center gap-1.5 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-md transition-all disabled:opacity-50"
              >
                <CheckCircle2 className="w-4 h-4" /> Approve & Release Payment
              </button>

              <button
                onClick={() => setActiveModalDecision('reject')}
                disabled={reviewMutation.isPending}
                className="flex items-center gap-1.5 px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-md transition-all disabled:opacity-50"
              >
                <AlertCircle className="w-4 h-4" /> Reject & Withhold Payout
              </button>

              <button
                onClick={() => setActiveModalDecision('flag_backcheck')}
                disabled={reviewMutation.isPending}
                className="flex items-center gap-1.5 px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-black text-xs rounded-xl shadow-md transition-all disabled:opacity-50"
              >
                <Flag className="w-4 h-4" /> Flag for Backcheck
              </button>

              <button
                onClick={() => setActiveModalDecision('send_back')}
                disabled={reviewMutation.isPending}
                className="flex items-center gap-1.5 px-4 py-2.5 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold text-xs rounded-xl shadow-xs transition-all disabled:opacity-50 border border-slate-300 dark:border-slate-700"
              >
                <RotateCcw className="w-4 h-4" /> Send Back
              </button>
            </div>
          </div>
        </div>
      )}

      <ConfirmActionModal
        isOpen={!!activeModalDecision}
        onClose={() => setActiveModalDecision(null)}
        decision={activeModalDecision}
        questType={submission.quest.quest_type}
        isSubmitting={reviewMutation.isPending}
        onSubmit={(payload) => reviewMutation.mutate(payload)}
        // Was never wired up before, so the backcheck outcome radio never
        // appeared and backcheck_outcome was never actually sent — the
        // Trust Score's audit_confirmation dimension and the agent's
        // backcheck_pass_rate both depend on this being recorded.
        isBackcheckPage={submission.status === 'backcheck'}
      />
    </div>
  );
};
