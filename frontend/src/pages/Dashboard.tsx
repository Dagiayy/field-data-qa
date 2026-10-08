import { useEffect, useState } from "react";
import { api, ApiError } from "../lib/api";

type HealthResponse = {
  status: string;
  service: string;
};

type CheckState =
  | { phase: "loading" }
  | { phase: "ok"; data: HealthResponse }
  | { phase: "error"; message: string };

export default function Dashboard() {
  const [check, setCheck] = useState<CheckState>({ phase: "loading" });

  useEffect(() => {
    let cancelled = false;

    api
      .get<HealthResponse>("/api/health")
      .then((data) => {
        if (!cancelled) setCheck({ phase: "ok", data });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof ApiError ? `${err.status} ${err.message}` : String(err);
        setCheck({ phase: "error", message });
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
      <div className="max-w-md w-full rounded-lg border border-slate-800 bg-slate-900 p-8 shadow-xl">
        <h1 className="text-2xl font-semibold mb-1">Metrix QA Dashboard</h1>
        <p className="text-slate-400 text-sm mb-6">Scaffolding placeholder — backend connectivity check</p>

        {check.phase === "loading" && (
          <p className="text-slate-400">Checking connection to backend…</p>
        )}

        {check.phase === "ok" && (
          <div className="rounded-md bg-emerald-950 border border-emerald-800 p-4">
            <p className="text-emerald-400 font-medium">Backend reachable ✓</p>
            <pre className="text-xs text-emerald-300 mt-2">{JSON.stringify(check.data, null, 2)}</pre>
          </div>
        )}

        {check.phase === "error" && (
          <div className="rounded-md bg-red-950 border border-red-800 p-4">
            <p className="text-red-400 font-medium">Could not reach backend</p>
            <p className="text-xs text-red-300 mt-2">{check.message}</p>
          </div>
        )}
      </div>
    </div>
  );
}
