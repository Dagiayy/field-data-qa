import React from 'react';
import { NavLink } from 'react-router-dom';
import {
  ListFilter,
  CheckSquare,
  ShieldCheck,
  BarChart3,
  MapPin,
  CreditCard,
  Zap,
  Image as ImageIcon,
  FlaskConical,
} from 'lucide-react';

interface NavItem {
  label: string;
  to: string;
  icon: React.ReactNode;
  badge?: string;
}

const NAV_ITEMS: NavItem[] = [
  {
    label: 'QA Queue',
    to: '/queue',
    icon: <ListFilter className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Backcheck Queue',
    to: '/backcheck',
    icon: <CheckSquare className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Agent Trust Scores',
    to: '/agents',
    icon: <ShieldCheck className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Payment Status',
    to: '/payments',
    icon: <CreditCard className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'QA Lead Dashboard',
    to: '/overview',
    icon: <BarChart3 className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Baseline Management',
    to: '/outlets',
    icon: <MapPin className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Image Testing',
    to: '/image-test',
    icon: <ImageIcon className="w-4.5 h-4.5 shrink-0" />,
  },
  {
    label: 'Manual Ingestion Tester',
    to: '/test-ingestion',
    icon: <FlaskConical className="w-4.5 h-4.5 shrink-0" />,
  },
];

export const Sidebar: React.FC = () => {
  return (
    <aside className="w-64 bg-white dark:bg-[#0F172A] border-r border-slate-200 dark:border-slate-800 flex flex-col h-screen sticky top-0 shrink-0 select-none z-30 transition-colors">
      {/* Brand Header */}
      <div className="h-16 px-6 flex items-center border-b border-slate-200 dark:border-slate-800/80 justify-between">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-black shadow-md">
            <Zap className="w-5 h-5 fill-white stroke-none" />
          </div>
          <div>
            <h1 className="text-base font-black tracking-tight text-slate-900 dark:text-white leading-none flex items-center gap-1.5">
              METRIX <span className="text-emerald-700 dark:text-emerald-400 font-bold text-xs uppercase px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-200 dark:border-emerald-800">QA Engine</span>
            </h1>
            <span className="text-[10px] text-slate-500 dark:text-slate-400 font-semibold tracking-wider uppercase">Field Data Verification</span>
          </div>
        </div>
      </div>

      {/* Navigation items */}
      <div className="p-4 flex-1 space-y-6 overflow-y-auto">
        <div>
          <div className="px-3 mb-2.5 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">
            Main Review Portal
          </div>
          <nav className="space-y-1.5">
            {NAV_ITEMS.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all ${
                    isActive
                      ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30 shadow-xs'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                  }`
                }
              >
                <div className="flex items-center gap-3">
                  {item.icon}
                  <span>{item.label}</span>
                </div>
                {item.badge && (
                  <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    {item.badge}
                  </span>
                )}
              </NavLink>
            ))}
          </nav>
        </div>
      </div>


    </aside>
  );
};
