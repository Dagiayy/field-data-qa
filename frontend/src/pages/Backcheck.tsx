import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { getQueueApi } from '../api/queue';
import type { QueueItem } from '../types';
import { DataTable, type Column } from '../components/DataTable';
import { FlagBadge } from '../components/FlagBadge';
import { StatusBadge } from '../components/StatusBadge';
import { formatDistanceToNow, format } from 'date-fns';
import { CheckSquare, RefreshCw, XCircle } from 'lucide-react';

type Tab = 'backcheck' | 'rejected';

export const Backcheck: React.FC = () => {
  const navigate = useNavigate();
  const [tab, setTab] = useState<Tab>('backcheck');
  const [page, setPage] = useState(1);

  const { data: response, isLoading, refetch } = useQuery({
    queryKey: ['backcheckQueue', tab, page],
    queryFn: () => getQueueApi({ status: tab, page }),
  });

  const changeTab = (next: Tab) => {
    setTab(next);
    setPage(1);
  };

  const sharedColumns: Column<QueueItem>[] = [
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
        <span className="font-mono text-xs font-bold px-2 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300 border border-purple-200 dark:border-purple-800 rounded-md">
          {item.agent_id}
        </span>
      ),
    },
  ];

  const backcheckColumns: Column<QueueItem>[] = [
    ...sharedColumns,
    {
      header: 'Backcheck Flagged',
      accessor: (item) => {
        const date = new Date(item.submitted_at);
        return (
          <div>
            <div className="font-semibold text-slate-800 dark:text-slate-200">{formatDistanceToNow(date, { addSuffix: true })}</div>
            <div className="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{format(date, 'MMM d, HH:mm')}</div>
          </div>
        );
      },
    },
    {
      header: 'Status',
      accessor: (item) => <StatusBadge status={item.status} />,
    },
    {
      header: 'Flags & Backcheck Reason',
      accessor: (item) => (
        <div className="flex flex-wrap gap-1">
          {item.flags.map((flag, idx) => (
            <FlagBadge key={idx} flag={flag} />
          ))}
          {item.flags.length === 0 && <span className="text-slate-400 italic">No automated flags</span>}
        </div>
      ),
    },
  ];

  const rejectedColumns: Column<QueueItem>[] = [
    ...sharedColumns,
    {
      header: 'Rejected At',
      accessor: (item) => {
        const dateStr = item.latest_review?.reviewed_at || item.submitted_at;
        const date = new Date(dateStr);
        return (
          <div>
            <div className="font-semibold text-slate-800 dark:text-slate-200">{formatDistanceToNow(date, { addSuffix: true })}</div>
            <div className="text-[10px] text-slate-500 dark:text-slate-400 font-mono">{format(date, 'MMM d, HH:mm')}</div>
          </div>
        );
      },
    },
    {
      header: 'Reason',
      accessor: (item) => (
        <div>
          {item.latest_review?.reason_code ? (
            <span className="inline-block px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-[10px] font-bold uppercase">
              {item.latest_review.reason_label || item.latest_review.reason_code}
            </span>
          ) : (
            <span className="text-slate-400 italic">No reason recorded</span>
          )}
        </div>
      ),
    },
    {
      header: 'Reviewer Note',
      accessor: (item) => (
        <p className="text-slate-600 dark:text-slate-300 max-w-xs truncate" title={item.latest_review?.note || ''}>
          {item.latest_review?.note || '—'}
        </p>
      ),
    },
    {
      header: 'Automated Flags',
      accessor: (item) => (
        <div className="flex flex-wrap gap-1">
          {item.flags.map((flag, idx) => (
            <FlagBadge key={idx} flag={flag} />
          ))}
          {item.flags.length === 0 && <span className="text-slate-400 italic">Clean</span>}
        </div>
      ),
    },
  ];

  const tabs: { key: Tab; label: string; icon: React.ReactNode; emptyMessage: string }[] = [
    {
      key: 'backcheck',
      label: 'Needs Human Verification',
      icon: <CheckSquare className="w-4 h-4" />,
      emptyMessage: 'There are currently no submissions queued for field backcheck.',
    },
    {
      key: 'rejected',
      label: 'Rejected',
      icon: <XCircle className="w-4 h-4" />,
      emptyMessage: 'No submissions have been rejected yet.',
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <CheckSquare className="w-6 h-6 text-purple-600 dark:text-purple-400" /> Backcheck & Rejection Review Queue
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Submissions requiring supervisor physical re-inspection, plus every submission already rejected and why.
          </p>
        </div>

        <button
          onClick={() => refetch()}
          className="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-xs"
        >
          <RefreshCw className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" /> Refresh
        </button>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 border-b border-slate-200 dark:border-slate-800">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => changeTab(t.key)}
            className={`flex items-center gap-1.5 px-4 py-2.5 text-xs font-bold rounded-t-xl border-b-2 transition-colors ${
              tab === t.key
                ? 'border-purple-600 text-purple-700 dark:text-purple-400 bg-purple-50/60 dark:bg-purple-950/30'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
            }`}
          >
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      <DataTable
        columns={tab === 'backcheck' ? backcheckColumns : rejectedColumns}
        data={response?.data || []}
        meta={response?.meta}
        isLoading={isLoading}
        onPageChange={setPage}
        onRowClick={(item) => navigate(`/submissions/${item.id}`)}
        emptyMessage={tabs.find((t) => t.key === tab)?.emptyMessage}
      />
    </div>
  );
};
