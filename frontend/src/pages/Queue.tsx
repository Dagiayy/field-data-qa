import React from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getQueueApi } from '../api/queue';
import { getOutletsApi } from '../api/outlets';
import type { QueueItem } from '../types';
import { DataTable, type Column } from '../components/DataTable';
import { FlagBadge } from '../components/FlagBadge';
import { formatDistanceToNow, format } from 'date-fns';
import { Filter, RefreshCw, Zap } from 'lucide-react';

export const Queue: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  const questId = searchParams.get('quest_id') || '';
  const outletId = searchParams.get('outlet_id') || '';
  const agentId = searchParams.get('agent_id') || '';
  const hasFlags = searchParams.get('has_flags') === 'true';
  const page = parseInt(searchParams.get('page') || '1', 10);

  const updateFilter = (key: string, value: string | boolean) => {
    const next = new URLSearchParams(searchParams);
    if (value === '' || value === false) {
      next.delete(key);
    } else {
      next.set(key, String(value));
    }
    next.set('page', '1');
    setSearchParams(next);
  };

  const { data: queueResponse, isLoading, error, refetch } = useQuery({
    queryKey: ['qaQueue', questId, outletId, agentId, hasFlags, page],
    queryFn: () =>
      getQueueApi({
        quest_id: questId || undefined,
        outlet_id: outletId || undefined,
        agent_id: agentId || undefined,
        has_flags: hasFlags ? true : undefined,
        page,
      }),
  });

  const { data: outletsResponse } = useQuery({
    queryKey: ['outletsList'],
    queryFn: () => getOutletsApi(),
  });

  const columns: Column<QueueItem>[] = [
    {
      header: 'Quest',
      accessor: (item) => (
        <div>
          <div className="font-bold text-slate-900 dark:text-white tracking-tight">{item.quest.title}</div>
          <div className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{item.quest.form_code}</div>
        </div>
      ),
    },
    {
      header: 'Outlet',
      accessor: (item) => (
        <div>
          <div className="font-semibold text-slate-800 dark:text-slate-200">{item.outlet.name}</div>
          <div className="text-[11px] text-slate-500 dark:text-slate-400">{item.outlet.branch}</div>
        </div>
      ),
    },
    {
      header: 'Agent ID',
      accessor: (item) => (
        <span className="font-mono text-xs font-bold px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md text-emerald-700 dark:text-emerald-400">
          {item.agent_id}
        </span>
      ),
    },
    {
      header: 'Submitted',
      accessor: (item) => {
        const date = new Date(item.submitted_at);
        return (
          <div title={format(date, 'yyyy-MM-dd HH:mm:ss')}>
            <div className="font-semibold text-slate-800 dark:text-slate-200">{formatDistanceToNow(date, { addSuffix: true })}</div>
            <div className="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{format(date, 'MMM d, HH:mm')}</div>
          </div>
        );
      },
    },
    {
      header: 'Trust Score',
      accessor: (item) => {
        // The agent's overall standing (average Trust Score across every
        // submission of theirs that's been scored) — not this row's own
        // submission-level score. A reviewer scanning the queue is judging
        // whether to trust the AGENT, so that's the number that belongs here.
        const score = item.agent_overall_trust_score ?? item.trust_score?.total_score ?? 92;
        let colorBg = 'bg-emerald-50 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        if (score < 70) {
          colorBg = 'bg-rose-50 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800';
        } else if (score < 85) {
          colorBg = 'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        }
        return (
          <div className="flex items-center gap-2">
            <span
              className={`px-2.5 py-1 rounded-full border text-xs font-black font-mono shadow-xs ${colorBg}`}
              title="Agent's overall Trust Score (average across all their scored submissions)"
            >
              {Math.round(score)}/100
            </span>
          </div>
        );
      },
    },
    {
      header: 'Flags & Rules',
      accessor: (item) => (
        <div className="flex flex-wrap gap-1">
          {item.flags.map((flag, idx) => (
            <FlagBadge key={idx} flag={flag} />
          ))}
          {item.flags.length === 0 && <span className="text-slate-400 italic text-xs">Clean (No flags)</span>}
        </div>
      ),
    },
    {
      header: 'Payout Status',
      accessor: (item) => {
        const status = item.payment_status || 'pending';
        let badgeClass = 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700';
        if (status === 'paid') badgeClass = 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        if (status === 'under_review') badgeClass = 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        if (status === 'rejected') badgeClass = 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800';

        return (
          <div>
            <span className={`inline-block px-2.5 py-0.5 rounded-md border text-[10px] font-black uppercase tracking-wider ${badgeClass}`}>
              {status.replace('_', ' ')}
            </span>
            <div className="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">{item.payout_amount ? `${item.payout_amount} ETB` : '350 ETB'}</div>
          </div>
        );
      },
    },
    {
      header: 'Time in Queue',
      accessor: (item) => {
        const mins = Math.floor((Date.now() - new Date(item.submitted_at).getTime()) / 60000);
        return (
          <span className={`font-mono text-xs font-bold ${mins > 60 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-600 dark:text-slate-300'}`}>
            {mins}m
          </span>
        );
      },
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header Bar */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <Zap className="w-6 h-6 text-emerald-600 dark:text-emerald-400 fill-emerald-600 dark:fill-emerald-400" /> QA Review Queue
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Incoming Section 4.1 Mini App field submissions pending reviewer verification.
          </p>
        </div>

        <button
          onClick={() => refetch()}
          className="flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-all shadow-xs"
        >
          <RefreshCw className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
          Refresh Ingestion Queue
        </button>
      </div>

      {/* Filter Box */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-4 shadow-xs dark:shadow-xl space-y-3 transition-colors">
        <div className="flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-400 tracking-wide uppercase">
          <Filter className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
          <span>Queue Filters</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
          <div>
            <label className="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Quest</label>
            <select
              value={questId}
              onChange={(e) => updateFilter('quest_id', e.target.value)}
              className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-200 focus:border-emerald-500 focus:outline-none"
            >
              <option value="">All Quests</option>
              <option value="QST-OIL-SHOA-001">Edible Oils Facing & Price Audit</option>
              <option value="QST-DAIRY-002">Dairy & Beverages Price Audit</option>
              <option value="QST-COFFEE-009">Coffee Baseline Photo Verification</option>
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Outlet</label>
            <select
              value={outletId}
              onChange={(e) => updateFilter('outlet_id', e.target.value)}
              className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-200 focus:border-emerald-500 focus:outline-none"
            >
              <option value="">All Outlets</option>
              {outletsResponse?.data.map((o) => (
                <option key={o.id} value={o.id}>
                  {o.name} ({o.branch})
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Agent ID</label>
            <input
              type="text"
              placeholder="Filter agent ID..."
              value={agentId}
              onChange={(e) => updateFilter('agent_id', e.target.value)}
              className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 px-3 py-2 text-xs text-slate-900 dark:text-slate-200 focus:border-emerald-500 focus:outline-none font-mono"
            />
          </div>

          <div className="flex items-center pt-5">
            <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={hasFlags}
                onChange={(e) => updateFilter('has_flags', e.target.checked)}
                className="w-4 h-4 rounded border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-emerald-600 focus:ring-emerald-500"
              />
              <span>Show Flagged Submissions Only</span>
            </label>
          </div>
        </div>
      </div>

      {error && (
        <div className="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/80 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-xs flex items-center justify-between">
          <span>Failed to load submission queue from backend.</span>
          <button onClick={() => refetch()} className="font-bold underline">
            Retry
          </button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={queueResponse?.data || []}
        meta={queueResponse?.meta}
        isLoading={isLoading}
        onPageChange={(newPage) => updateFilter('page', String(newPage))}
        onRowClick={(item) => navigate(`/submissions/${item.id}`)}
        emptyMessage="The review queue is currently empty for selected criteria."
      />
    </div>
  );
};
