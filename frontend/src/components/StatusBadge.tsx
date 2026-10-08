import React from 'react';

interface StatusBadgeProps {
  status: 'pending' | 'approved' | 'rejected' | 'backcheck' | 'sent_back' | string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status }) => {
  let style = 'bg-slate-100 text-slate-700 border-slate-200';
  let label = status;

  switch (status) {
    case 'pending':
      style = 'bg-amber-50 text-amber-800 border-amber-200';
      label = 'Pending Review';
      break;
    case 'approved':
      style = 'bg-emerald-50 text-emerald-800 border-emerald-200';
      label = 'Approved';
      break;
    case 'rejected':
      style = 'bg-rose-50 text-rose-800 border-rose-200';
      label = 'Rejected';
      break;
    case 'backcheck':
      style = 'bg-purple-50 text-purple-800 border-purple-200';
      label = 'Backcheck Queued';
      break;
    case 'sent_back':
      style = 'bg-blue-50 text-blue-800 border-blue-200';
      label = 'Sent Back to Field';
      break;
  }

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border ${style}`}>
      <span className="w-1.5 h-1.5 rounded-full bg-current mr-1.5 opacity-75" />
      {label}
    </span>
  );
};
