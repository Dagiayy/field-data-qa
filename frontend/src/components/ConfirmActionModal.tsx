import React, { useState } from 'react';
import type { ReviewDecision, ReviewPayload } from '../types';
import { ReasonCodeSelect } from './ReasonCodeSelect';
import { AlertCircle, CheckCircle2, Flag, RotateCcw, X } from 'lucide-react';

interface ConfirmActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  decision: ReviewDecision | null;
  questType?: string;
  isSubmitting: boolean;
  onSubmit: (payload: ReviewPayload) => void;
  isBackcheckPage?: boolean;
}

export const ConfirmActionModal: React.FC<ConfirmActionModalProps> = ({
  isOpen,
  onClose,
  decision,
  questType,
  isSubmitting,
  onSubmit,
  isBackcheckPage = false,
}) => {
  const [reasonCode, setReasonCode] = useState('');
  const [note, setNote] = useState('');
  const [backcheckOutcome, setBackcheckOutcome] = useState<'confirmed' | 'not_confirmed'>('confirmed');

  if (!isOpen || !decision) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSubmit({
      decision,
      reason_code: decision === 'reject' ? reasonCode : undefined,
      note: note || undefined,
      backcheck_outcome: isBackcheckPage ? backcheckOutcome : undefined,
    });
  };

  let title = 'Confirm Decision';
  let buttonBg = 'bg-[#2E7D4F] hover:bg-[#24653F] text-white';
  let icon = <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />;

  if (decision === 'approve') {
    title = 'Approve Submission';
    buttonBg = 'bg-emerald-700 hover:bg-emerald-800 text-white';
    icon = <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />;
  } else if (decision === 'reject') {
    title = 'Reject Submission';
    buttonBg = 'bg-rose-600 hover:bg-rose-700 text-white';
    icon = <AlertCircle className="w-5 h-5 text-rose-600 shrink-0" />;
  } else if (decision === 'flag_backcheck') {
    title = 'Flag for Field Backcheck';
    buttonBg = 'bg-purple-600 hover:bg-purple-700 text-white';
    icon = <Flag className="w-5 h-5 text-purple-600 shrink-0" />;
  } else if (decision === 'send_back') {
    title = 'Send Back to Field Agent';
    buttonBg = 'bg-blue-600 hover:bg-blue-700 text-white';
    icon = <RotateCcw className="w-5 h-5 text-blue-600 shrink-0" />;
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-fade-in">
      <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-150 space-y-5">
        <div className="flex items-center justify-between pb-3 border-b border-gray-100">
          <div className="flex items-center gap-2.5">
            {icon}
            <h3 className="text-base font-bold text-gray-900">{title}</h3>
          </div>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {isBackcheckPage && (
            <div className="space-y-1 bg-purple-50 p-3 rounded-xl border border-purple-100">
              <label className="block text-xs font-semibold text-purple-900">Recorded Backcheck Outcome</label>
              <div className="flex gap-4 pt-1">
                <label className="flex items-center gap-1.5 text-xs text-purple-900 cursor-pointer">
                  <input
                    type="radio"
                    name="backcheck_outcome"
                    value="confirmed"
                    checked={backcheckOutcome === 'confirmed'}
                    onChange={() => setBackcheckOutcome('confirmed')}
                    className="accent-purple-600"
                  />
                  <span>Confirmed Valid</span>
                </label>
                <label className="flex items-center gap-1.5 text-xs text-purple-900 cursor-pointer">
                  <input
                    type="radio"
                    name="backcheck_outcome"
                    value="not_confirmed"
                    checked={backcheckOutcome === 'not_confirmed'}
                    onChange={() => setBackcheckOutcome('not_confirmed')}
                    className="accent-purple-600"
                  />
                  <span>Not Confirmed (Discrepancy)</span>
                </label>
              </div>
            </div>
          )}

          {decision === 'reject' && (
            <ReasonCodeSelect value={reasonCode} onChange={setReasonCode} questType={questType} required />
          )}

          <div className="space-y-1">
            <label className="block text-xs font-semibold text-gray-700">Reviewer Note (Optional)</label>
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={3}
              placeholder="Provide context or explanation for trace logging..."
              className="w-full rounded-xl border border-gray-300 bg-white p-2.5 text-xs text-gray-800 shadow-sm focus:border-[#2E7D4F] focus:outline-none focus:ring-1 focus:ring-[#2E7D4F]"
            />
          </div>

          <div className="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="px-4 py-2 text-xs font-medium text-gray-600 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={isSubmitting || (decision === 'reject' && !reasonCode)}
              className={`px-5 py-2 text-xs font-semibold rounded-xl shadow-sm transition-all disabled:opacity-50 ${buttonBg}`}
            >
              {isSubmitting ? 'Saving Decision...' : 'Confirm Action'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
