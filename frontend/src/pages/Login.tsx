import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { CheckCircle2, Lock, Mail, AlertCircle } from 'lucide-react';

export const Login: React.FC = () => {
  const [email, setEmail] = useState('reviewer@metrix.qa');
  const [password, setPassword] = useState('password123');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const from = (location.state as any)?.from?.pathname || '/queue';

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      await login(email, password);
      navigate(from, { replace: true });
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid email or password');
    } finally {
      setIsSubmitting(false);
    }
  };

  const fillQuickUser = (userEmail: string) => {
    setEmail(userEmail);
    setPassword('password123');
  };

  return (
    <div className="min-h-screen bg-[#F5F4FA] flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-3xl p-8 shadow-xl border border-gray-150 space-y-6">
        <div className="text-center space-y-2">
          <div className="inline-flex w-12 h-12 rounded-2xl bg-[#EAF5EC] text-[#2E7D4F] items-center justify-center mb-1">
            <CheckCircle2 className="w-7 h-7 stroke-[2.5]" />
          </div>
          <h1 className="text-2xl font-black text-[#2E7D4F]">Metrix QA Portal</h1>
          <p className="text-xs text-gray-500 font-medium">Field Data Verification & Review System</p>
        </div>

        {error && (
          <div className="flex items-center gap-2 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1">
            <label className="block text-xs font-semibold text-gray-700">Email Address</label>
            <div className="relative">
              <Mail className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full rounded-xl border border-gray-300 bg-white pl-9 pr-3 py-2.5 text-xs text-gray-800 focus:border-[#2E7D4F] focus:outline-none focus:ring-1 focus:ring-[#2E7D4F]"
              />
            </div>
          </div>

          <div className="space-y-1">
            <label className="block text-xs font-semibold text-gray-700">Password</label>
            <div className="relative">
              <Lock className="w-4 h-4 text-gray-400 absolute left-3 top-3" />
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full rounded-xl border border-gray-300 bg-white pl-9 pr-3 py-2.5 text-xs text-gray-800 focus:border-[#2E7D4F] focus:outline-none focus:ring-1 focus:ring-[#2E7D4F]"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full py-3 px-4 bg-[#2E7D4F] hover:bg-[#24653F] text-white font-bold text-xs rounded-xl shadow-md transition-all disabled:opacity-50"
          >
            {isSubmitting ? 'Authenticating...' : 'Sign In to Dashboard'}
          </button>
        </form>

        {/* Preset quick fill buttons for review convenience */}
        <div className="pt-4 border-t border-gray-100 space-y-2">
          <span className="text-[11px] font-semibold text-gray-400 uppercase tracking-wider block text-center">
            Quick Fill Demo Roles
          </span>
          <div className="grid grid-cols-2 gap-2 text-[11px]">
            <button
              onClick={() => fillQuickUser('reviewer@metrix.qa')}
              className="px-2.5 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 font-medium text-left truncate"
            >
              Reviewer
            </button>
            <button
              onClick={() => fillQuickUser('lead@metrix.qa')}
              className="px-2.5 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 font-medium text-left truncate"
            >
              QA Lead
            </button>
            <button
              onClick={() => fillQuickUser('ops@metrix.qa')}
              className="px-2.5 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 font-medium text-left truncate"
            >
              Field Ops
            </button>
            <button
              onClick={() => fillQuickUser('admin@metrix.qa')}
              className="px-2.5 py-1.5 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-700 font-medium text-left truncate"
            >
              Admin
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
