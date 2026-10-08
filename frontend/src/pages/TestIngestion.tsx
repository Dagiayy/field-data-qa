import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { testIngestApi } from '../api/testIngest';
import { SAMPLE_PAYLOADS } from '../api/samplePayloads';
import { useToast } from '../components/ToastProvider';
import { FlaskConical, Wand2, RefreshCw, Send, CheckCircle2, AlertCircle, ArrowRight } from 'lucide-react';

type ResultState =
  | { kind: 'success'; submission_id: string; status: string; flags_raised: number }
  | { kind: 'error'; message: string; fieldErrors?: Record<string, string[]> }
  | null;

function withFreshSubmissionId(envelope: Record<string, unknown>): Record<string, unknown> {
  return { ...envelope, submission_id: crypto.randomUUID() };
}

export const TestIngestion: React.FC = () => {
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [jsonText, setJsonText] = useState('');
  const [parseError, setParseError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [result, setResult] = useState<ResultState>(null);

  const loadPreset = (key: string) => {
    const preset = SAMPLE_PAYLOADS.find((p) => p.key === key);
    if (!preset) return;
    setJsonText(JSON.stringify(withFreshSubmissionId(preset.envelope), null, 2));
    setParseError(null);
    setResult(null);
  };

  const formatJson = () => {
    try {
      const parsed = JSON.parse(jsonText);
      setJsonText(JSON.stringify(parsed, null, 2));
      setParseError(null);
    } catch (err: any) {
      setParseError(err.message || 'Invalid JSON.');
    }
  };

  const regenerateSubmissionId = () => {
    try {
      const parsed = JSON.parse(jsonText);
      setJsonText(JSON.stringify(withFreshSubmissionId(parsed), null, 2));
      setParseError(null);
    } catch (err: any) {
      setParseError(err.message || 'Invalid JSON — fix it before regenerating the submission ID.');
    }
  };

  const handleSubmit = async () => {
    let envelope: unknown;
    try {
      envelope = JSON.parse(jsonText);
    } catch (err: any) {
      setParseError(err.message || 'Invalid JSON.');
      return;
    }
    setParseError(null);
    setIsSubmitting(true);
    setResult(null);

    try {
      const response = await testIngestApi(envelope);
      setResult({ kind: 'success', submission_id: response.submission_id, status: response.status, flags_raised: response.flags_raised });
      showToast('Payload Ingested', `Submission ${response.submission_id.slice(0, 8)}... created (${response.flags_raised} flags raised)`, 'success');
    } catch (err: any) {
      const data = err.response?.data;
      setResult({
        kind: 'error',
        message: data?.message || 'Ingestion failed. Check the backend logs for details.',
        fieldErrors: data?.errors,
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
          <FlaskConical className="w-6 h-6 text-emerald-600 dark:text-emerald-400" /> Manual Ingestion Tester
        </h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Paste or edit a submission envelope and push it through the real ingestion pipeline — same validation, same
          QA rule engine, same Trust Score computation the live Mini App integration will trigger. Useful until that
          integration is actually feeding this system.
        </p>
      </div>

      {/* Presets */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
        <h2 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide mb-3">Load a Sample Payload</h2>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {SAMPLE_PAYLOADS.map((preset) => (
            <button
              key={preset.key}
              onClick={() => loadPreset(preset.key)}
              className="text-left p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 hover:border-emerald-300 dark:hover:border-emerald-700 transition-colors"
            >
              <div className="text-xs font-bold text-slate-900 dark:text-white">{preset.label}</div>
              <div className="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{preset.description}</div>
            </button>
          ))}
        </div>
      </div>

      {/* Editor */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Submission Envelope (JSON)</h2>
          <div className="flex gap-2">
            <button
              onClick={formatJson}
              disabled={!jsonText}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors disabled:opacity-40"
            >
              <Wand2 className="w-3.5 h-3.5" /> Format
            </button>
            <button
              onClick={regenerateSubmissionId}
              disabled={!jsonText}
              className="flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors disabled:opacity-40"
              title="Submission IDs are the idempotency key — resubmitting the same one returns the existing record untouched."
            >
              <RefreshCw className="w-3.5 h-3.5" /> New Submission ID
            </button>
          </div>
        </div>

        <textarea
          value={jsonText}
          onChange={(e) => {
            setJsonText(e.target.value);
            setParseError(null);
          }}
          placeholder="Load a sample payload above, or paste your own envelope JSON here..."
          rows={20}
          spellCheck={false}
          className="w-full font-mono text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 p-4 focus:border-emerald-500 focus:outline-none resize-y"
        />

        {parseError && (
          <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/80 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-xs flex items-start gap-2">
            <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" /> {parseError}
          </div>
        )}

        <div className="flex justify-end">
          <button
            onClick={handleSubmit}
            disabled={!jsonText || isSubmitting}
            className="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-md transition-all disabled:opacity-50"
          >
            {isSubmitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
            {isSubmitting ? 'Ingesting...' : 'Submit to Ingestion Pipeline'}
          </button>
        </div>
      </div>

      {/* Result */}
      {result && result.kind === 'success' && (
        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-emerald-200 dark:border-emerald-800 shadow-sm p-6 space-y-3">
          <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 font-bold text-sm">
            <CheckCircle2 className="w-5 h-5" /> Ingested Successfully
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div>
              <div className="text-slate-400 uppercase font-bold text-[10px] mb-1">Submission ID</div>
              <div className="font-mono font-bold text-slate-900 dark:text-white break-all">{result.submission_id}</div>
            </div>
            <div>
              <div className="text-slate-400 uppercase font-bold text-[10px] mb-1">Status</div>
              <div className="font-mono font-bold text-slate-900 dark:text-white capitalize">{result.status}</div>
            </div>
            <div>
              <div className="text-slate-400 uppercase font-bold text-[10px] mb-1">Flags Raised</div>
              <div className="font-mono font-bold text-slate-900 dark:text-white">{result.flags_raised}</div>
            </div>
          </div>
          <button
            onClick={() => navigate(`/submissions/${result.submission_id}`)}
            className="flex items-center gap-1.5 px-4 py-2 bg-slate-900 dark:bg-emerald-600 hover:bg-slate-800 dark:hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-colors"
          >
            View Submission <ArrowRight className="w-3.5 h-3.5" />
          </button>
        </div>
      )}

      {result && result.kind === 'error' && (
        <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-rose-200 dark:border-rose-800 shadow-sm p-6 space-y-2">
          <div className="flex items-center gap-2 text-rose-700 dark:text-rose-400 font-bold text-sm">
            <AlertCircle className="w-5 h-5" /> Ingestion Failed
          </div>
          <p className="text-xs text-slate-600 dark:text-slate-300">{result.message}</p>
          {result.fieldErrors && (
            <ul className="text-xs text-rose-700 dark:text-rose-300 list-disc list-inside space-y-0.5 pt-1">
              {Object.entries(result.fieldErrors).map(([field, messages]) => (
                <li key={field}>
                  <span className="font-mono font-bold">{field}</span>: {messages.join(', ')}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
};
