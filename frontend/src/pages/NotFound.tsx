import React from 'react';
import { useNavigate } from 'react-router-dom';
import { HelpCircle } from 'lucide-react';

export const NotFound: React.FC = () => {
  const navigate = useNavigate();

  return (
    <div className="min-h-[60vh] flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl p-8 max-w-md w-full border border-gray-150 shadow-xl text-center space-y-4">
        <div className="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto">
          <HelpCircle className="w-8 h-8" />
        </div>
        <h2 className="text-xl font-extrabold text-gray-900">404 - Page Not Found</h2>
        <p className="text-xs text-gray-500 leading-relaxed">
          The route you are looking for does not exist in the Metrix QA Dashboard.
        </p>
        <button
          onClick={() => navigate('/queue')}
          className="px-5 py-2.5 bg-[#2E7D4F] hover:bg-[#24653F] text-white text-xs font-bold rounded-xl shadow-xs transition-colors"
        >
          Go to Review Queue
        </button>
      </div>
    </div>
  );
};
