import React from 'react';
import { useNavigate } from 'react-router-dom';
import { ShieldX } from 'lucide-react';

export const NotAuthorized: React.FC = () => {
  const navigate = useNavigate();

  return (
    <div className="min-h-[60vh] flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl p-8 max-w-md w-full border border-gray-150 shadow-xl text-center space-y-4">
        <div className="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
          <ShieldX className="w-8 h-8" />
        </div>
        <h2 className="text-xl font-extrabold text-gray-900">Access Restricted</h2>
        <p className="text-xs text-gray-500 leading-relaxed">
          Your current user role does not have permission to view or execute actions on this page. If you require access, please contact your QA Lead or System Administrator.
        </p>
        <button
          onClick={() => navigate('/queue')}
          className="px-5 py-2.5 bg-[#2E7D4F] hover:bg-[#24653F] text-white text-xs font-bold rounded-xl shadow-xs transition-colors"
        >
          Return to Allowed Dashboard
        </button>
      </div>
    </div>
  );
};
