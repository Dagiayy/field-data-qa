import React from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import { ShieldCheck, Radio, Sun, Moon } from 'lucide-react';

export const TopNav: React.FC = () => {
  const { user } = useAuth();
  const { theme, toggleTheme } = useTheme();

  return (
    <header className="h-16 bg-white dark:bg-[#0F172A] border-b border-slate-200 dark:border-slate-800 px-6 flex items-center justify-between sticky top-0 z-20 shadow-xs transition-colors">
      <div className="flex items-center gap-3">
        <h2 className="text-sm font-extrabold text-slate-900 dark:text-white tracking-wide">
          Metrix QA Ingestion Platform
        </h2>

      </div>

      <div className="flex items-center gap-4">
        {/* Dark / Light Mode Toggle Button */}
        <button
          onClick={toggleTheme}
          aria-label="Toggle Theme"
          className="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all flex items-center gap-2 text-xs font-bold shadow-2xs"
        >
          {theme === 'dark' ? (
            <>
              <Sun className="w-4 h-4 text-amber-400 fill-amber-400" />
              <span className="hidden sm:inline">Light Mode</span>
            </>
          ) : (
            <>
              <Moon className="w-4 h-4 text-slate-600 fill-slate-600" />
              <span className="hidden sm:inline">Dark Mode</span>
            </>
          )}
        </button>


      </div>
    </header>
  );
};
