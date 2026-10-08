import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getQueueApi } from '../api/queue';
import { CreditCard, CheckCircle2, Clock, AlertTriangle, RefreshCw, Filter } from 'lucide-react';

// Payment status must ONLY ever be derived from the real QA decision (see
// project CLAUDE.md non-negotiable rules) — this reads the backend's own
// payment_status/payout_amount fields (App\Services\Payments\PaymentStatusDeriver)
// rather than re-deriving anything from trust score on the frontend.
const PAYMENT_LABELS: Record<string, { label: string; detail: string }> = {
  paid: { label: 'Paid', detail: 'QA Approved — Payout Settled' },
  under_review: { label: 'Under Review', detail: 'Approved, Awaiting Payout Batch' },
  rejected: { label: 'Rejected', detail: 'QA Verification Failed — Payout Withheld' },
  pending: { label: 'Pending', detail: 'Awaiting QA Reviewer Decision' },
};

export const PaymentStatus: React.FC = () => {
  const [filterStatus, setFilterStatus] = useState<string>('all');

  // The default /qa/queue view only returns submissions still awaiting a
  // reviewer (pending/backcheck/sent_back) — this page's whole point is
  // showing payment OUTCOMES, so it also needs the already-decided
  // (approved/rejected) submissions the default view deliberately excludes.
  const { data: pendingQueue, isLoading: pendingLoading, refetch: refetchPending } = useQuery({
    queryKey: ['paymentStatusQueue', 'pending'],
    queryFn: () => getQueueApi({ page: 1 }),
  });
  const { data: approvedQueue, isLoading: approvedLoading, refetch: refetchApproved } = useQuery({
    queryKey: ['paymentStatusQueue', 'approved'],
    queryFn: () => getQueueApi({ page: 1, status: 'approved' }),
  });
  const { data: rejectedQueue, isLoading: rejectedLoading, refetch: refetchRejected } = useQuery({
    queryKey: ['paymentStatusQueue', 'rejected'],
    queryFn: () => getQueueApi({ page: 1, status: 'rejected' }),
  });

  const isLoading = pendingLoading || approvedLoading || rejectedLoading;
  const refetch = () => {
    refetchPending();
    refetchApproved();
    refetchRejected();
  };

  const items = [
    ...(pendingQueue?.data || []),
    ...(approvedQueue?.data || []),
    ...(rejectedQueue?.data || []),
  ];

  const paymentRecords = items.map((item) => {
    const paymentStatus = item.payment_status || 'pending';
    const meta = PAYMENT_LABELS[paymentStatus] || PAYMENT_LABELS.pending;

    return {
      ...item,
      trustScore: Math.round(item.agent_overall_trust_score ?? item.trust_score?.total_score ?? 92),
      derivedPaymentStatus: meta.label as 'Paid' | 'Pending' | 'Under Review' | 'Rejected',
      detailMsg: meta.detail,
      payoutAmount: item.payout_amount ?? 0,
    };
  });

  const filteredRecords = paymentRecords.filter((rec) => {
    if (filterStatus === 'all') return true;
    return rec.derivedPaymentStatus.toLowerCase().replace(' ', '_') === filterStatus;
  });

  const totalPaid = paymentRecords.filter(r => r.derivedPaymentStatus === 'Paid').reduce((acc, r) => acc + r.payoutAmount, 0);
  const totalPending = paymentRecords.filter(r => r.derivedPaymentStatus === 'Pending' || r.derivedPaymentStatus === 'Under Review').reduce((acc, r) => acc + r.payoutAmount, 0);
  const totalRejected = paymentRecords.filter(r => r.derivedPaymentStatus === 'Rejected').reduce((acc, r) => acc + r.payoutAmount, 0);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <CreditCard className="w-6 h-6 text-emerald-600 dark:text-emerald-400" /> QA Payout & Payment Verification Stage
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Payout is settled the instant a submission is approved, at that quest's configured reward amount — withheld on rejection, held pending otherwise.
          </p>
        </div>

        <button
          onClick={() => refetch()}
          className="flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-xs"
        >
          <RefreshCw className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
          Refresh Payout Statuses
        </button>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-1 transition-colors">
          <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Total Paid Out
          </div>
          <div className="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">{totalPaid} ETB</div>
          <p className="text-[11px] text-slate-400 dark:text-slate-500">QA Approved — Settled</p>
        </div>

        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-1 transition-colors">
          <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <Clock className="w-4 h-4 text-amber-600 dark:text-amber-400" /> Pending / Under Review
          </div>
          <div className="text-3xl font-black text-amber-600 dark:text-amber-400 font-mono tracking-tight">{totalPending} ETB</div>
          <p className="text-[11px] text-slate-400 dark:text-slate-500">Pending review or manual evaluation</p>
        </div>

        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-1 transition-colors">
          <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <AlertTriangle className="w-4 h-4 text-rose-600 dark:text-rose-400" /> Rejected / Withheld
          </div>
          <div className="text-3xl font-black text-rose-600 dark:text-rose-400 font-mono tracking-tight">{totalRejected} ETB</div>
          <p className="text-[11px] text-slate-400 dark:text-slate-500">Failed verification or rule violation</p>
        </div>
      </div>

      {/* Filters Bar */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs dark:shadow-xl flex flex-wrap items-center justify-between gap-3 transition-colors">
        <div className="flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">
          <Filter className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
          <span>Payment Status Filters</span>
        </div>

        <div className="flex flex-wrap gap-2">
          {[
            { label: 'All Payouts', value: 'all' },
            { label: 'Paid', value: 'paid' },
            { label: 'Pending', value: 'pending' },
            { label: 'Under Review', value: 'under_review' },
            { label: 'Rejected', value: 'rejected' },
          ].map((f) => (
            <button
              key={f.value}
              onClick={() => setFilterStatus(f.value)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${
                filterStatus === f.value
                  ? 'bg-emerald-600 text-white shadow-xs'
                  : 'bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800 hover:bg-slate-200 dark:hover:bg-slate-800'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl overflow-hidden transition-colors">
        {isLoading ? (
          <div className="p-8 text-center text-xs text-slate-400">Loading payment statuses...</div>
        ) : filteredRecords.length === 0 ? (
          <div className="p-8 text-center text-xs text-slate-400">No payment records found for selected filter.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                  <th className="py-3.5 px-4">Agent ID</th>
                  <th className="py-3.5 px-4">Submission ID</th>
                  <th className="py-3.5 px-4">Quest Title</th>
                  <th className="py-3.5 px-4">Outlet</th>
                  <th className="py-3.5 px-4">Trust Score</th>
                  <th className="py-3.5 px-4">Verification Stage</th>
                  <th className="py-3.5 px-4">Payment Status</th>
                  <th className="py-3.5 px-4">Amount</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-800 dark:text-slate-200">
                {filteredRecords.map((rec) => {
                  let statusBadgeClass = 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
                  if (rec.derivedPaymentStatus === 'Paid') statusBadgeClass = 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
                  if (rec.derivedPaymentStatus === 'Under Review') statusBadgeClass = 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800';
                  if (rec.derivedPaymentStatus === 'Rejected') statusBadgeClass = 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800';

                  return (
                    <tr key={rec.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/80 transition-colors">
                      <td className="py-3.5 px-4 font-mono font-bold text-emerald-700 dark:text-emerald-400">{rec.agent_id}</td>
                      <td className="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400">{rec.id.slice(0, 8)}...</td>
                      <td className="py-3.5 px-4 font-semibold text-slate-900 dark:text-slate-200">{rec.quest.title}</td>
                      <td className="py-3.5 px-4 text-slate-600 dark:text-slate-300">{rec.outlet.name}</td>
                      <td className="py-3.5 px-4">
                        <span className="font-mono font-black px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                          {rec.trustScore}/100
                        </span>
                      </td>
                      <td className="py-3.5 px-4">
                        <span className="capitalize font-semibold text-slate-700 dark:text-slate-300">{rec.status}</span>
                      </td>
                      <td className="py-3.5 px-4">
                        <div>
                          <span className={`inline-block px-2.5 py-0.5 rounded-full border text-[10px] font-black uppercase tracking-wide ${statusBadgeClass}`}>
                            {rec.derivedPaymentStatus}
                          </span>
                          <div className="text-[10px] text-slate-400 dark:text-slate-500 pt-0.5">{rec.detailMsg}</div>
                        </div>
                      </td>
                      <td className="py-3.5 px-4 font-mono font-extrabold text-emerald-700 dark:text-emerald-400 text-sm">{rec.payoutAmount} ETB</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
