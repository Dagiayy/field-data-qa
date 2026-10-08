import React from 'react';
import type { QAFlag } from '../types';
import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react';

interface FlagBadgeProps {
  flag: QAFlag;
  showDetail?: boolean;
}

export const formatFlagRuleName = (ruleName: string): string => {
  switch (ruleName) {
    case 'possible_reused_photo':
      return 'Possible Reused / Duplicate Photo';
    case 'possible_wrong_location':
      return 'Photo Baseline Location Mismatch';
    case 'geofence_check':
      return 'Outlet Geofence Boundary Check';
    case 'price_range_outlier':
      return 'Price Value Outlier Detected';
    case 'timestamp_consistency':
      return 'Survey Completed Unusually Quickly';
    case 'flagged_for_field_backcheck':
      return 'Flagged for Supervisor Backcheck Visit';
    case 'all_checks_passed':
      return 'All Automated QA Checks Passed';
    case 'baseline_photo_mismatch':
      return 'Baseline Photo Comparison Mismatch';
    case 'low_photo_legibility':
      return 'Product Name Not Legible (OCR)';
    case 'poor_photo_lighting':
      return 'Poor Photo Lighting (Dark/Overexposed)';
    case 'small_product_in_frame':
      return 'Product Size in Frame Borderline/Too Small';
    case 'duplicate_gps_location':
      return 'Duplicate GPS Location Detected';
    case 'time_sequence_inconsistent':
      return 'Timestamp Sequence Inconsistent';
    case 'excessive_accept_to_start_delay':
      return 'Excessive Accept-to-Start Delay';
    case 'missing_required_fields':
      return 'Missing Required Fields';
    case 'poor_gps_accuracy':
      return 'Poor GPS Hardware Accuracy';
    case 'answer_pattern_repetitive':
      return 'Repetitive Low-Effort Answer Pattern';
    case 'low_photo_resolution':
      return 'Photo Resolution Below Minimum';
    case 'photo_too_blurry':
      return 'Photo Too Blurry / Out of Focus';
    case 'low_photo_contrast':
      return 'Photo Contrast Too Low';
    case 'excessive_photo_noise':
      return 'Excessive Photo Noise/Grain';
    case 'excessive_photo_glare':
      return 'Excessive Photo Glare/Reflection';
    case 'no_duration_baseline_configured':
      return 'Quest Has No Duration Baseline Configured';
    case 'survey_duration_outside_expected_range':
      return 'Survey Duration Outside Expected Range';
    case 'no_outlet_baseline_configured':
      return 'Outlet Has No GPS/Photo Baseline Configured';
    case 'no_required_fields_configured':
      return 'Quest Has No Required Fields Configured';
    case 'no_quest_acceptance_recorded':
      return 'No Quest Acceptance Event Recorded';
    default:
      return ruleName.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }
};

export const FlagBadge: React.FC<FlagBadgeProps> = ({ flag, showDetail = false }) => {
  const label = formatFlagRuleName(flag.rule_name);

  let bgClass = 'bg-gray-100 text-gray-700 border-gray-200';
  let icon = <Info className="w-3.5 h-3.5 text-gray-500 shrink-0" />;

  if (flag.result === 'fail' || flag.severity === 'high' || flag.rule_name.includes('reused') || flag.rule_name.includes('wrong_location')) {
    bgClass = 'bg-rose-50 text-rose-800 border-rose-200';
    icon = <AlertCircle className="w-3.5 h-3.5 text-rose-600 shrink-0" />;
  } else if (flag.result === 'flag' || flag.severity === 'medium' || flag.rule_name.includes('outlier') || flag.rule_name.includes('timestamp')) {
    bgClass = 'bg-amber-50 text-amber-800 border-amber-200';
    icon = <AlertTriangle className="w-3.5 h-3.5 text-amber-600 shrink-0" />;
  } else if (flag.result === 'pass') {
    bgClass = 'bg-emerald-50 text-emerald-800 border-emerald-200';
    icon = <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 shrink-0" />;
  }

  return (
    <div className={`inline-flex flex-col gap-1 rounded-lg border px-2.5 py-1 text-xs font-medium ${bgClass}`}>
      <div className="flex items-center gap-1.5">
        {icon}
        <span>{label}</span>
      </div>
      {showDetail && flag.detail && (
        <p className="text-[11px] font-normal opacity-90 pl-5 leading-tight">{flag.detail}</p>
      )}
    </div>
  );
};
