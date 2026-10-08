import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet as RouterOutlet } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from './context/AuthContext';
import { ThemeProvider } from './context/ThemeContext';
import { ToastProvider } from './components/ToastProvider';
import { RequireRole } from './components/RequireRole';
import { Sidebar } from './components/Sidebar';
import { TopNav } from './components/TopNav';

// Pages
import { Queue } from './pages/Queue';
import { SubmissionReview } from './pages/SubmissionReview';
import { Backcheck } from './pages/Backcheck';
import { AgentTrustScore } from './pages/AgentTrustScore';
import { PaymentStatus } from './pages/PaymentStatus';
import { QAOverview } from './pages/QAOverview';
import { BaselineManagement } from './pages/BaselineManagement';
import { ImageTest } from './pages/ImageTest';
import { TestIngestion } from './pages/TestIngestion';
import { NotAuthorized } from './pages/NotAuthorized';
import { NotFound } from './pages/NotFound';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: 1,
      staleTime: 1000 * 60 * 5,
    },
  },
});

const MainLayout: React.FC = () => {
  return (
    <div className="flex min-h-screen bg-[#F8FAFC] dark:bg-[#0B0F19] text-slate-900 dark:text-slate-100 transition-colors">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <TopNav />
        <main className="p-6 flex-1 overflow-x-hidden">
          <RouterOutlet />
        </main>
      </div>
    </div>
  );
};

export const App: React.FC = () => {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <AuthProvider>
          <ToastProvider>
            <BrowserRouter>
            <Routes>
              {/* Redirect /login to main queue */}
              <Route path="/login" element={<Navigate to="/queue" replace />} />

              {/* Main App Routes */}
              <Route element={<MainLayout />}>
                <Route index element={<Navigate to="/queue" replace />} />

                <Route
                  path="/queue"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin']}>
                      <Queue />
                    </RequireRole>
                  }
                />

                <Route
                  path="/submissions/:id"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin']}>
                      <SubmissionReview />
                    </RequireRole>
                  }
                />

                <Route
                  path="/backcheck"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin']}>
                      <Backcheck />
                    </RequireRole>
                  }
                />

                <Route
                  path="/agents"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'field_ops', 'admin']}>
                      <AgentTrustScore />
                    </RequireRole>
                  }
                />

                <Route
                  path="/payments"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin', 'field_ops']}>
                      <PaymentStatus />
                    </RequireRole>
                  }
                />

                <Route
                  path="/overview"
                  element={
                    <RequireRole roles={['qa_lead', 'admin']}>
                      <QAOverview />
                    </RequireRole>
                  }
                />

                <Route
                  path="/outlets"
                  element={
                    <RequireRole roles={['field_ops', 'admin']}>
                      <BaselineManagement />
                    </RequireRole>
                  }
                />

                <Route
                  path="/image-test"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin', 'field_ops']}>
                      <ImageTest />
                    </RequireRole>
                  }
                />

                <Route
                  path="/test-ingestion"
                  element={
                    <RequireRole roles={['qa_reviewer', 'qa_lead', 'admin', 'field_ops']}>
                      <TestIngestion />
                    </RequireRole>
                  }
                />

                <Route path="/not-authorized" element={<NotAuthorized />} />
                <Route path="*" element={<NotFound />} />
              </Route>
            </Routes>
          </BrowserRouter>
        </ToastProvider>
      </AuthProvider>
    </ThemeProvider>
  </QueryClientProvider>
  );
};

export default App;
