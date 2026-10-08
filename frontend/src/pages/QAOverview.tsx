import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getReportOverviewApi } from '../api/reports';
import type { ReviewerThroughput } from '../types';
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, CartesianGrid, Cell } from 'recharts';
import {
  BarChart3, Clock, Layers, Calendar, ArrowUpDown, ShieldCheck,
  AlertTriangle, XCircle, Flag, CreditCard, Award, Hourglass,
} from 'lucide-react';
import { useTheme } from '../context/ThemeContext';

const TIER_COLORS: Record<string, string> = {
  Gold: '#F59E0B',
  Silver: '#94A3B8',
  Bronze: '#B45309',
  Flagged: '#EF4444',
};

const SEVERITY_COLORS: Record<string, string> = {
  high: '#F43F5E',
  medium: '#F59E0B',
  low: '#38BDF8',
};

const WALLET_STATE_META: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  paid: { label: 'Paid', color: 'text-emerald-600 dark:text-emerald-400', icon: <CreditCard className="w-5 h-5" /> },
  pending: { label: 'Pending', color: 'text-amber-600 dark:text-amber-400', icon: <Clock className="w-5 h-5" /> },
  under_review: { label: 'Under Review', color: 'text-amber-600 dark:text-amber-400', icon: <Clock className="w-5 h-5" /> },
  approved: { label: 'Approved', color: 'text-sky-600 dark:text-sky-400', icon: <ShieldCheck className="w-5 h-5" /> },
  rejected: { label: 'Rejected', color: 'text-rose-600 dark:text-rose-400', icon: <XCircle className="w-5 h-5" /> },
  bonus: { label: 'Bonus', color: 'text-emerald-600 dark:text-emerald-400', icon: <Award className="w-5 h-5" /> },
};

const fmtPercent = (val: number | null): string => (val === null ? '—' : `${val}%`);
const fmtAmount = (val: number, currency: string | null): string =>
  `${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency ?? ''}`.trim();

export const QAOverview: React.FC = () => {
  const { theme } = useTheme();
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [sortField, setSortField] = useState<keyof ReviewerThroughput>('reviewed_count');
  const [sortAsc, setSortAsc] = useState(false);

  const { data: overview, isLoading } = useQuery({
    queryKey: ['qaReportOverview', dateFrom, dateTo],
    queryFn: () => getReportOverviewApi({ date_from: dateFrom || undefined, date_to: dateTo || undefined }),
  });

  const sortedThroughput = [...(overview?.reviewer_throughput || [])].sort((a, b) => {
    const aVal = a[sortField];
    const bVal = b[sortField];
    if (typeof aVal === 'number' && typeof bVal === 'number') {
      return sortAsc ? aVal - bVal : bVal - aVal;
    }
    return sortAsc ? String(aVal).localeCompare(String(bVal)) : String(bVal).localeCompare(String(aVal));
  });

  const handleSort = (field: keyof ReviewerThroughput) => {
    if (sortField === field) {
      setSortAsc(!sortAsc);
    } else {
      setSortField(field);
      setSortAsc(false);
    }
  };

  const chartTooltipStyle = {
    borderRadius: '12px',
    background: theme === 'dark' ? '#0F172A' : '#FFFFFF',
    border: theme === 'dark' ? '1px solid #334155' : '1px solid #E2E8F0',
    color: theme === 'dark' ? '#FFF' : '#000',
  };
  const gridStroke = theme === 'dark' ? '#1E293B' : '#E2E8F0';
  const tickStyle = { fontSize: 11, fill: theme === 'dark' ? '#94A3B8' : '#64748B' };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <BarChart3 className="w-6 h-6 text-emerald-600 dark:text-emerald-400" /> QA Lead Dashboard
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Queue health, quality outcomes, systemic QA flags, and the payment funnel — every figure below is a live database aggregate, not a cached snapshot.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3 bg-white dark:bg-[#0F172A] p-2 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs dark:shadow-xl transition-colors">
          <div className="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-300 font-semibold px-2">
            <Calendar className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" /> Date Range:
          </div>
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="px-2.5 py-1 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500 font-mono"
          />
          <span className="text-xs text-slate-400 dark:text-slate-500">to</span>
          <input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="px-2.5 py-1 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500 font-mono"
          />
        </div>
      </div>

      {isLoading ? (
        <div className="h-96 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 animate-pulse" />
      ) : overview ? (
        <div className="space-y-6">
          {/* Top KPI row */}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl transition-colors">
              <div className="flex items-center justify-between">
                <div>
                  <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Queue Depth</span>
                  <div className="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-1">{overview.queue_depth} Items</div>
                </div>
                <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center">
                  <Layers className="w-5 h-5" />
                </div>
              </div>
              <div className="flex items-center gap-2 mt-3 flex-wrap">
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">Pending {overview.queue_by_status.pending_review}</span>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400">Backcheck {overview.queue_by_status.backcheck}</span>
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-400">Sent Back {overview.queue_by_status.sent_back}</span>
              </div>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl flex items-center justify-between transition-colors">
              <div>
                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Avg Decision Time</span>
                <div className="text-3xl font-black text-amber-600 dark:text-amber-400 font-mono mt-1">
                  {overview.avg_time_in_queue_minutes !== null ? `${overview.avg_time_in_queue_minutes}m` : '—'}
                </div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Submission to reviewer decision</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex items-center justify-center">
                <Clock className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl flex items-center justify-between transition-colors">
              <div>
                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Avg Trust Score</span>
                <div className="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono mt-1">
                  {overview.avg_trust_score !== null ? `${overview.avg_trust_score} / 100` : '—'}
                </div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Across every scored submission</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center font-black">
                <ShieldCheck className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl flex items-center justify-between transition-colors">
              <div>
                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Approval Rate</span>
                <div className="text-3xl font-black text-sky-600 dark:text-sky-400 font-mono mt-1">{fmtPercent(overview.approval_rate)}</div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">Of decided submissions in range</p>
              </div>
              <div className="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 flex items-center justify-center">
                <BarChart3 className="w-5 h-5" />
              </div>
            </div>
          </div>

          {/* Secondary stat strip */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs dark:shadow-xl flex items-center gap-3 transition-colors">
              <div className="w-9 h-9 rounded-lg bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                <Hourglass className="w-4.5 h-4.5" />
              </div>
              <div>
                <div className="text-lg font-black text-slate-900 dark:text-white font-mono">
                  {overview.oldest_pending_age_hours !== null ? `${overview.oldest_pending_age_hours}h` : '—'}
                </div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Oldest item still awaiting review — SLA risk</p>
              </div>
            </div>
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs dark:shadow-xl flex items-center gap-3 transition-colors">
              <div className="w-9 h-9 rounded-lg bg-rose-50 dark:bg-rose-950 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                <XCircle className="w-4.5 h-4.5" />
              </div>
              <div>
                <div className="text-lg font-black text-slate-900 dark:text-white font-mono">{fmtPercent(overview.rejection_rate)}</div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Rejection rate of decided submissions</p>
              </div>
            </div>
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs dark:shadow-xl flex items-center gap-3 transition-colors">
              <div className="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <Flag className="w-4.5 h-4.5" />
              </div>
              <div>
                <div className="text-lg font-black text-slate-900 dark:text-white font-mono">{fmtPercent(overview.backcheck_rate)}</div>
                <p className="text-[11px] text-slate-400 dark:text-slate-500">Flagged for supervisor backcheck</p>
              </div>
            </div>
          </div>

          {/* Trust Tier Distribution */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Agent Trust Tier Distribution</h3>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              {overview.trust_tier_distribution.map((t) => (
                <div key={t.tier} className="rounded-xl border border-slate-200 dark:border-slate-800 p-3 text-center" style={{ borderTopColor: TIER_COLORS[t.tier], borderTopWidth: 3 }}>
                  <div className="text-2xl font-black font-mono" style={{ color: TIER_COLORS[t.tier] }}>{t.count}</div>
                  <div className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide mt-0.5">{t.tier}</div>
                </div>
              ))}
            </div>
          </div>

          {/* Charts Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">QA Approval Rate by Quest</h3>
              <div className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={overview.approval_rate_by_quest} margin={{ top: 10, right: 20, left: -10, bottom: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={gridStroke} />
                    <XAxis dataKey="quest_title" tick={tickStyle} />
                    <YAxis domain={[0, 100]} unit="%" tick={tickStyle} />
                    <Tooltip formatter={(val: any) => [`${val}%`, 'Approval Rate']} contentStyle={chartTooltipStyle} />
                    <Bar dataKey="approval_rate" fill="#10B981" radius={[6, 6, 0, 0]} maxBarSize={40} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
              {overview.approval_rate_by_quest.length === 0 && (
                <p className="text-xs text-slate-400 dark:text-slate-500 text-center">No decided submissions in range yet.</p>
              )}
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">QA Approval Rate by City</h3>
              <div className="h-64 w-full">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={overview.approval_rate_by_city} margin={{ top: 10, right: 20, left: -10, bottom: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={gridStroke} />
                    <XAxis dataKey="city" tick={tickStyle} />
                    <YAxis domain={[0, 100]} unit="%" tick={tickStyle} />
                    <Tooltip formatter={(val: any) => [`${val}%`, 'Approval Rate']} contentStyle={chartTooltipStyle} />
                    <Bar dataKey="approval_rate" fill="#38BDF8" radius={[6, 6, 0, 0]} maxBarSize={40} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
              {overview.approval_rate_by_city.length === 0 && (
                <p className="text-xs text-slate-400 dark:text-slate-500 text-center">No decided submissions in range yet.</p>
              )}
            </div>
          </div>

          {/* Systemic QA issues */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-amber-500" /> Top QA Flags Firing
              </h3>
              <p className="text-[11px] text-slate-400 dark:text-slate-500 -mt-2">
                Automated Layer 1 checks most often flagging/failing — a rule dominating this list (e.g. a missing-baseline flag) points at a systemic configuration gap, not agent misconduct.
              </p>
              {overview.top_qa_flags.length > 0 ? (
                <div className="h-64 w-full">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={overview.top_qa_flags} layout="vertical" margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                      <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke={gridStroke} />
                      <XAxis type="number" allowDecimals={false} tick={tickStyle} />
                      <YAxis
                        type="category"
                        dataKey="rule_name"
                        width={150}
                        tick={{ fontSize: 10, fill: theme === 'dark' ? '#94A3B8' : '#64748B' }}
                        tickFormatter={(val: string) => val.replace(/_/g, ' ')}
                      />
                      <Tooltip formatter={(val: any, _name, props: any) => [`${val} occurrences (${props.payload.severity} severity)`, props.payload.rule_name.replace(/_/g, ' ')]} contentStyle={chartTooltipStyle} />
                      <Bar dataKey="count" radius={[0, 6, 6, 0]} maxBarSize={18}>
                        {overview.top_qa_flags.map((f, idx) => (
                          <Cell key={idx} fill={SEVERITY_COLORS[f.severity] ?? '#94A3B8'} />
                        ))}
                      </Bar>
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              ) : (
                <p className="text-xs text-slate-400 dark:text-slate-500 text-center py-8">No QA flags fired in range.</p>
              )}
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
              <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide flex items-center gap-2">
                <XCircle className="w-4 h-4 text-rose-500" /> Rejection Reasons Breakdown
              </h3>
              {overview.rejection_breakdown.length > 0 ? (
                <div className="space-y-2">
                  {overview.rejection_breakdown.map((r) => {
                    const max = Math.max(...overview.rejection_breakdown.map((x) => x.count), 1);
                    return (
                      <div key={r.reason_code} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-semibold text-slate-700 dark:text-slate-300">{r.label}</span>
                          <span className="font-mono font-bold text-rose-600 dark:text-rose-400">{r.count}</span>
                        </div>
                        <div className="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                          <div className="h-full bg-rose-500 rounded-full" style={{ width: `${(r.count / max) * 100}%` }} />
                        </div>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <p className="text-xs text-slate-400 dark:text-slate-500 text-center py-8">No rejections recorded in range.</p>
              )}
            </div>
          </div>

          {/* Payment Funnel */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide flex items-center gap-2">
              <CreditCard className="w-4 h-4 text-emerald-500" /> Payment Funnel
            </h3>
            <p className="text-[11px] text-slate-400 dark:text-slate-500 -mt-2">
              Wallet state is derived strictly from QA decisions (PaymentStatusDeriver) — this is exactly what agents will see in their payout status.
            </p>
            {overview.payment_funnel.length > 0 ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {overview.payment_funnel.map((p) => {
                  const meta = WALLET_STATE_META[p.wallet_state] ?? WALLET_STATE_META.pending;
                  return (
                    <div key={`${p.wallet_state}-${p.currency}`} className="rounded-xl border border-slate-200 dark:border-slate-800 p-4">
                      <div className={`flex items-center gap-2 ${meta.color}`}>
                        {meta.icon}
                        <span className="text-xs font-bold uppercase tracking-wide">{meta.label}</span>
                      </div>
                      <div className="text-2xl font-black text-slate-900 dark:text-white font-mono mt-2">{p.count}</div>
                      <p className="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">{fmtAmount(p.total_amount, p.currency)}</p>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-xs text-slate-400 dark:text-slate-500 text-center py-4">No payment records in range.</p>
            )}
          </div>

          {/* Reviewer Performance Table */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Reviewer Performance & Throughput</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                    <th
                      onClick={() => handleSort('reviewer_name')}
                      className="py-3 px-4 cursor-pointer hover:text-slate-900 dark:hover:text-white select-none"
                    >
                      <div className="flex items-center gap-1">
                        Reviewer Name <ArrowUpDown className="w-3 h-3 text-slate-400 dark:text-slate-500" />
                      </div>
                    </th>
                    <th
                      onClick={() => handleSort('reviewed_count')}
                      className="py-3 px-4 cursor-pointer hover:text-slate-900 dark:hover:text-white select-none"
                    >
                      <div className="flex items-center gap-1">
                        Submissions Reviewed <ArrowUpDown className="w-3 h-3 text-slate-400 dark:text-slate-500" />
                      </div>
                    </th>
                    <th
                      onClick={() => handleSort('avg_decision_time_minutes')}
                      className="py-3 px-4 cursor-pointer hover:text-slate-900 dark:hover:text-white select-none"
                    >
                      <div className="flex items-center gap-1">
                        Avg Decision Time <ArrowUpDown className="w-3 h-3 text-slate-400 dark:text-slate-500" />
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-800 dark:text-slate-200">
                  {sortedThroughput.length > 0 ? sortedThroughput.map((rev, idx) => (
                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-900/80 transition-colors">
                      <td className="py-3 px-4 font-bold text-slate-900 dark:text-white">{rev.reviewer_name}</td>
                      <td className="py-3 px-4 font-mono font-bold text-emerald-700 dark:text-emerald-400">{rev.reviewed_count}</td>
                      <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">{rev.avg_decision_time_minutes} mins</td>
                    </tr>
                  )) : (
                    <tr>
                      <td colSpan={3} className="py-6 text-center text-slate-400 dark:text-slate-500">No reviewer decisions recorded in range.</td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
};
