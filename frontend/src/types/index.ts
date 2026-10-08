export type Role = 'admin' | 'qa_reviewer' | 'qa_lead' | 'field_ops' | 'read_only';

export interface User {
  id: string;
  name: string;
  email: string;
  role: Role;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface QAFlag {
  rule_name: string;
  result: 'pass' | 'fail' | 'flag';
  severity?: 'high' | 'medium' | 'low';
  detail?: string;
  media_refs?: string[] | null;
  sku_id?: string | null;
  question_id?: string | null;
}

export interface RepeatTableRow {
  row_id: string;
  [key: string]: any;
}

export interface Answer {
  question_id: string;
  input_type: 'text' | 'numeric' | 'numeric_price' | 'dropdown' | 'single_choice' | 'multiple_choice' | 'checkbox' | 'photo' | 'repeat_table' | string;
  value?: any;
  row_id?: string;
  media_ref?: string;
  currency?: string;
}

export interface BaselineInfo {
  photo_url: string;
  spot_label: string;
  hash_distance: number | null;
  embedding_similarity: number | null;
  // 'open_clip_vit_b_32' when a real vision-embedding comparison was
  // possible; 'perceptual_hash_fallback' only if the image-service was
  // unreachable when either photo's embedding would have been computed.
  similarity_method: 'open_clip_vit_b_32' | 'perceptual_hash_fallback' | null;
  gps_distance_m: number;
}

export type QualityVerdict = 'pass' | 'flag' | 'fail' | null;

export interface ResolutionCheck {
  width: number | null;
  height: number | null;
  verdict: QualityVerdict;
  min_width: number;
  min_height: number;
  recommended_width: number;
  recommended_height: number;
}

export interface BlurCheck {
  laplacian_variance: number | null;
  verdict: QualityVerdict;
  hard_fail_variance: number;
  borderline_variance: number;
}

export interface ContrastCheck {
  std_dev: number | null;
  verdict: QualityVerdict;
  poor_threshold: number;
}

export interface GlareCheck {
  ratio_pct: number | null;
  verdict: QualityVerdict;
  warning_threshold_pct: number;
  reject_threshold_pct: number;
}

export interface NoiseCheck {
  sigma: number | null;
  verdict: QualityVerdict;
  warning_threshold: number;
  reject_threshold: number;
}

export interface LegibilityCheck {
  verdict: QualityVerdict;
  min_acceptable_confidence: number;
  good_confidence: number;
}

export interface SizeFramingCheck {
  verdict: QualityVerdict;
  hard_fail_ratio: number;
  borderline_ratio: number;
}

export interface PhotoAnalysis {
  ocr_text: string | null;
  ocr_avg_confidence: number | null;
  text_height_ratio: number | null;
  brightness_mean: number | null;
  is_dark: boolean | null;
  is_overexposed: boolean | null;
  resolution: ResolutionCheck;
  blur: BlurCheck;
  contrast: ContrastCheck;
  glare: GlareCheck;
  noise: NoiseCheck;
  legibility: LegibilityCheck;
  size_framing: SizeFramingCheck;
}

export interface Media {
  media_ref: string;
  question_id: string;
  row_id?: string;
  media_type: string;
  url?: string;
  file_ref?: string;
  captured_at: string;
  gps_at_capture: { lat: number; lng: number };
  baseline?: BaselineInfo;
  photo_analysis?: PhotoAnalysis | null;
}

export interface MarketRange {
  min: number;
  max: number;
  source: 'payload' | 'quest_baseline';
  direction: 'above' | 'below' | 'within' | null;
}

export interface Price {
  question_id: string;
  sku_id: string;
  row_id?: string;
  value: number;
  currency: string;
  unit: string;
  package_size?: string | null;
  trade_type?: string | null;
  price_type?: string | null;
  discounted?: boolean | null;
  market_range?: MarketRange | null;
}

export interface QAReview {
  id: string;
  reviewer_name: string | null;
  decision: 'approve' | 'reject' | 'flag_backcheck' | 'send_back';
  reason_code: string | null;
  reason_label?: string | null;
  note: string | null;
  reviewed_at: string;
}

export interface Quest {
  id: string;
  title: string;
  form_code: string;
  quest_type?: string;
}

export interface OutletSummary {
  id: string;
  name: string;
  branch: string;
  city?: string;
  gps_lat?: number;
  gps_lng?: number;
  approved_radius_m?: number;
  geofence_radius_m?: number;
}

export interface Timestamps {
  quest_accepted_at?: string | null;
  survey_start_at: string;
  survey_end_at: string;
  submitted_at: string;
  accept_to_start_seconds?: number | null;
}

export interface QuestBaselineInfo {
  has_duration_baseline: boolean;
  expected_duration_min_seconds: number | null;
  expected_duration_max_seconds: number | null;
}

export interface QuestBaselinePriceRange {
  sku_id: string;
  min: number;
  max: number;
  currency: string;
  unit?: string | null;
}

export interface QuestBaselineOutletSpot {
  id: string;
  spot_label: string;
  baseline_gps_lat: number;
  baseline_gps_lng: number;
  baseline_gps_radius_m: number;
  photo_url: string | null;
  notes?: string | null;
}

export interface QuestBaselineOutlet {
  id: string;
  name: string;
  gps_lat: number;
  gps_lng: number;
  baselines: QuestBaselineOutletSpot[];
}

export interface QuestBaselineConfig {
  quest_id: string;
  expected_duration_min_seconds: number | null;
  expected_duration_max_seconds: number | null;
  has_duration_baseline: boolean;
  price_ranges: QuestBaselinePriceRange[];
  outlet: QuestBaselineOutlet | null;
}

export interface QuestListItem {
  id: string;
  title: string;
  form_code: string;
  quest_type: string;
  active: boolean;
  has_duration_baseline: boolean;
  outlet_id?: string | null;
  outlet_name?: string | null;
}

export interface TrustScoreBreakdown {
  total_score: number;
  gps_score: number;
  gps_details?: {
    shop_actual_gps: { lat: number; lng: number };
    submission_gps: { lat: number; lng: number; accuracy_m: number };
    photo_gps: { lat: number; lng: number };
    distance_to_shop_m: number;
    photo_distance_to_shop_m: number;
  };
  time_score: number;
  time_details?: {
    duration_seconds: number;
    expected_seconds: number;
    passed: boolean;
  };
  photo_score: number;
  photo_details?: {
    clarity_pass: boolean;
    baseline_similarity: number;
  };
  // answer_score/price_score are legacy fields from the old client-side-only
  // mock scoring (src/lib/trustScore.ts, src/api/mockData.ts) — the real
  // backend composite dropped price and folded "answer" into
  // completeness_score + audit_confirmation_score below.
  answer_score?: number;
  answer_details?: {
    valid_answers_count: number;
    total_answers_count: number;
  };
  price_score?: number;
  price_details?: {
    outliers_count: number;
    total_prices_count: number;
  };
  completeness_score: number;
  completeness_details?: {
    completed_fields: number;
    required_fields: number;
  };
  // Layer 2 human-review dimension (see TrustScoreCalculator) — how much
  // audit/backcheck confirmation this submission has received. Only
  // present on real backend-computed scores.
  audit_confirmation_score?: number;
  weights: {
    gps: number;
    time: number;
    photo: number;
    answer: number;
    price: number;
    completeness: number;
  };
  // Present when the backend computed this via TrustScoreCalculator — the
  // admin-configured per-dimension weights actually used (quest-specific,
  // see project CLAUDE.md "Trust Score Weighted Composite"). Keyed by
  // dimension name, not the client-side `weights` shape above.
  weights_used?: {
    gps: number;
    photo: number;
    time: number;
    completeness: number;
    audit_confirmation: number;
  };
}

export type PaymentStatus = 'paid' | 'pending' | 'rejected' | 'under_review';

export interface QueueItem {
  id: string;
  quest: Quest;
  outlet: OutletSummary;
  agent_id: string;
  submitted_at: string;
  status: 'pending' | 'approved' | 'rejected' | 'backcheck' | 'sent_back';
  flags: QAFlag[];
  trust_score?: TrustScoreBreakdown;
  // The agent's overall Trust Score — the average of total_score across
  // every submission of theirs that's been scored, not just this row's own
  // score. This is what the QA Queue's "Trust Score" column shows.
  agent_overall_trust_score?: number | null;
  payment_status?: PaymentStatus;
  payout_amount?: number;
  // The most recent QA decision on this submission (null if never
  // reviewed) — carries the rejection reason/note directly in the queue
  // list, not just the automated QaFlag rows.
  latest_review?: QAReview | null;
}

export interface Submission {
  id: string;
  submission_id?: string;
  quest_id?: string;
  quest: Quest;
  outlet_id?: string;
  outlet: OutletSummary;
  agent_id: string;
  config_version: string;
  survey_start_at?: string;
  survey_end_at?: string;
  submitted_at: string;
  timestamps?: Timestamps;
  quest_baseline?: QuestBaselineInfo | null;
  gps: { lat: number; lng: number; accuracy_m: number; max_acceptable_accuracy_m?: number };
  status: 'pending' | 'approved' | 'rejected' | 'backcheck' | 'sent_back';
  answers: Answer[];
  media: Media[];
  prices: Price[];
  flags: QAFlag[];
  review_history: QAReview[];
  client_app_version?: string;
  trust_score?: TrustScoreBreakdown;
  agent_overall_trust_score?: number | null;
  payment_status?: PaymentStatus;
  payout_amount?: number;
}

export interface AgentAnswerPatternQuestion {
  question_id: string;
  dominant_value: string;
  dominant_count: number;
  total_evaluations: number;
  repetition_ratio: number;
  result: 'pass' | 'flag' | 'fail';
  severity: 'medium' | 'high' | null;
}

export interface AgentAnswerPatternQuest {
  quest_id: string | null;
  quest_title: string | null;
  questions: AgentAnswerPatternQuestion[];
}

export interface AgentAnswerPatternSummary {
  agent_id: string;
  questions_evaluated: number;
  questions_flagged: number;
  worst_repetition_ratio: number | null;
  quests: AgentAnswerPatternQuest[];
}

export interface RejectionReason {
  code: string;
  label: string;
  applies_to_quest_types?: string[];
}

export interface AgentListItem {
  agent_id: string;
  overall_trust_score: number | null;
  tier: 'Gold' | 'Silver' | 'Bronze' | 'Flagged';
  approval_rate: number;
  total_submissions: number;
  last_submitted_at: string;
}

export interface AgentTrustScore {
  agent_id: string;
  approval_rate: number;
  backcheck_pass_rate: number;
  tier: 'Gold' | 'Silver' | 'Bronze' | 'Flagged';
  rejection_breakdown: { reason_code: string; label: string; count: number }[];
  overall_trust_score?: number;
  recent_builds?: {
    submission_id: string;
    quest_title: string;
    date: string;
    trust_score: number;
    gps_score: number;
    time_score: number;
    photo_score: number;
    completeness_score: number;
    audit_confirmation_score: number;
    outcome: 'approved' | 'rejected' | 'pending';
  }[];
}

export interface ReviewerThroughput {
  reviewer_name: string;
  reviewed_count: number;
  avg_decision_time_minutes: number;
}

export interface TrustTierCount {
  tier: 'Gold' | 'Silver' | 'Bronze' | 'Flagged';
  count: number;
}

export interface TopQaFlag {
  rule_name: string;
  result: 'flag' | 'fail';
  severity: string;
  count: number;
}

export interface RejectionBreakdownItem {
  reason_code: string;
  label: string;
  count: number;
}

export interface PaymentFunnelItem {
  wallet_state: 'pending' | 'approved' | 'rejected' | 'paid' | 'bonus';
  currency: string | null;
  count: number;
  total_amount: number;
}

export interface QAReportOverview {
  queue_depth: number;
  queue_by_status: { pending_review: number; backcheck: number; sent_back: number };
  oldest_pending_age_hours: number | null;
  avg_time_in_queue_minutes: number | null;
  avg_trust_score: number | null;
  approval_rate: number | null;
  rejection_rate: number | null;
  backcheck_rate: number | null;
  trust_tier_distribution: TrustTierCount[];
  approval_rate_by_quest: { quest_title: string; approval_rate: number; total: number }[];
  approval_rate_by_city: { city: string; approval_rate: number; total: number }[];
  reviewer_throughput: ReviewerThroughput[];
  top_qa_flags: TopQaFlag[];
  rejection_breakdown: RejectionBreakdownItem[];
  payment_funnel: PaymentFunnelItem[];
}

export interface Outlet {
  id: string;
  name: string;
  chain: string;
  branch: string;
  city: string;
  gps_lat: number;
  gps_lng: number;
}

export interface OutletBaseline {
  id: string;
  spot_label: string;
  baseline_gps_lat: number;
  baseline_gps_lng: number;
  baseline_gps_radius_m: number;
  photo_url: string;
  captured_by: string;
  captured_at: string;
  notes?: string;
}


export interface PaginatedMeta {
  current_page: number;
  last_page: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginatedMeta;
}

export type ReviewDecision = 'approve' | 'reject' | 'flag_backcheck' | 'send_back';

export interface ReviewPayload {
  decision: ReviewDecision;
  reason_code?: string;
  note?: string;
  backcheck_outcome?: 'confirmed' | 'not_confirmed';
}
