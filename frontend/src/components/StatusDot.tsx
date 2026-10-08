import React from 'react';

export type SectionStatus = 'pass' | 'flag' | 'fail';

// Small traffic-light indicator pinned to a validation card's top-right
// corner: green once every automated check for that card came back clean,
// yellow when something needs a human's judgement call, red when an
// automated check actually failed. Shared by the per-section cards on the
// submission review page and by each individual photo card.
export const StatusDot: React.FC<{ status: SectionStatus }> = ({ status }) => {
  const config = {
    pass: { color: 'bg-emerald-500', ring: 'ring-emerald-200 dark:ring-emerald-900/60', label: 'Verified — no issues detected' },
    flag: { color: 'bg-amber-400', ring: 'ring-amber-200 dark:ring-amber-900/60', label: 'Needs human review' },
    fail: { color: 'bg-rose-500', ring: 'ring-rose-200 dark:ring-rose-900/60', label: 'Failed — requires attention' },
  }[status];

  return (
    <span className="absolute top-4 right-4" title={config.label}>
      <span className={`block w-3 h-3 rounded-full ${config.color} ring-4 ${config.ring}`} />
    </span>
  );
};
