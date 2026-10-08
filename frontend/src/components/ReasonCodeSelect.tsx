import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { getRejectionReasonsApi } from '../api/submissions';

interface ReasonCodeSelectProps {
  value: string;
  onChange: (code: string) => void;
  questType?: string;
  required?: boolean;
}

export const ReasonCodeSelect: React.FC<ReasonCodeSelectProps> = ({ value, onChange, questType, required = false }) => {
  const { data, isLoading } = useQuery({
    queryKey: ['rejectionReasons'],
    queryFn: getRejectionReasonsApi,
  });

  const reasons = data?.data || [];
  const filtered = questType
    ? reasons.filter((r) => !r.applies_to_quest_types || r.applies_to_quest_types.includes(questType))
    : reasons;

  return (
    <div className="space-y-1">
      <label className="block text-xs font-semibold text-gray-700">
        Rejection Reason Code {required && <span className="text-rose-500">*</span>}
      </label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        required={required}
        disabled={isLoading}
        className="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-800 shadow-sm focus:border-[#2E7D4F] focus:outline-none focus:ring-1 focus:ring-[#2E7D4F]"
      >
        <option value="">-- Select reason for rejection --</option>
        {filtered.map((reason) => (
          <option key={reason.code} value={reason.code}>
            {reason.label} ({reason.code})
          </option>
        ))}
      </select>
    </div>
  );
};
