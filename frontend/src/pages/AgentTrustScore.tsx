import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { getAgentTrustScoreApi, getAgentAnswerPatternsApi, getAgentsListApi } from '../api/agents';
import { ResponsiveContainer, BarChart, Bar, XAxis, YAxis, Tooltip, CartesianGrid } from 'recharts';
import { ShieldCheck, Search, Award, CheckCircle2, UserCheck, Repeat, AlertTriangle, Users } from 'lucide-react';
import { useTheme } from '../context/ThemeContext';
import { formatDistanceToNow } from 'date-fns';

const TIER_BADGE_CLASS: Record<string, string> = {
  Gold: 'bg-emerald-100 dark:bg-emerald-950 text-emerald-900 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
  Silver: 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700',
  Bronze: 'bg-amber-100 dark:bg-amber-950 text-amber-900 dark:text-amber-300 border-amber-300 dark:border-amber-800',
  Flagged: 'bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-300 border-rose-300 dark:border-rose-800',
};

export const AgentTrustScore: React.FC = () => {
  const { theme } = useTheme();
  const [searchAgentId, setSearchAgentId] = useState('AGT-10432');
  const [selectedAgentId, setSelectedAgentId] = useState('AGT-10432');

  const { data: agentsList, isLoading: agentsListLoading } = useQuery({
    queryKey: ['agentsList'],
    queryFn: () => getAgentsListApi(),
  });

  const { data: score, isLoading } = useQuery({
    queryKey: ['agentTrustScore', selectedAgentId],
    queryFn: () => getAgentTrustScoreApi(selectedAgentId),
    enabled: !!selectedAgentId,
  });

  const { data: answerPatterns, isLoading: patternsLoading } = useQuery({
    queryKey: ['agentAnswerPatterns', selectedAgentId],
    queryFn: () => getAgentAnswerPatternsApi(selectedAgentId),
    enabled: !!selectedAgentId,
  });

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchAgentId.trim()) {
      setSelectedAgentId(searchAgentId.trim());
    }
  };

  let tierColor = 'bg-amber-100 dark:bg-amber-950 text-amber-900 dark:text-amber-300 border-amber-300 dark:border-amber-800';
  if (score?.tier === 'Gold') tierColor = 'bg-emerald-100 dark:bg-emerald-950 text-emerald-900 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800';
  else if (score?.tier === 'Silver') tierColor = 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700';
  else if (score?.tier === 'Flagged') tierColor = 'bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-300 border-rose-300 dark:border-rose-800';

  const recentBuilds = score?.recent_builds || [
    {
      submission_id: 'a41f9e2c-88b1-4e3a-9c2d-7f001a3b55e0',
      quest_title: 'Edible Oils Facing & Price Audit',
      date: '2026-07-21 09:18',
      trust_score: 92,
      gps_score: 98,
      time_score: 95,
      photo_score: 89,
      completeness_score: 100,
      audit_confirmation_score: 100,
      outcome: 'approved' as const,
    },
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <UserCheck className="w-6 h-6 text-emerald-600 dark:text-emerald-400" /> Agent Trust Score & Parameter Analytics
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Filter user agents to view detailed parameter builds (Photo, GPS, Completeness, Timing, Audit Confirmation) and submission history.
          </p>
        </div>

        <form onSubmit={handleSearchSubmit} className="flex items-center gap-2">
          <div className="relative">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
            <input
              type="text"
              placeholder="Search Agent ID (e.g. AGT-10432)..."
              value={searchAgentId}
              onChange={(e) => setSearchAgentId(e.target.value)}
              className="pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white w-64 focus:border-emerald-500 focus:outline-none shadow-xs font-mono"
            />
          </div>
          <button
            type="submit"
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-colors"
          >
            Lookup Agent
          </button>
        </form>
      </div>

      {/* All Agents Overview */}
      <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
        <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
          <div className="flex items-center gap-2">
            <Users className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">All Agents</h3>
          </div>
          <span className="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono">
            {agentsList?.length ?? 0} Agent{agentsList?.length === 1 ? '' : 's'}
          </span>
        </div>

        {agentsListLoading ? (
          <div className="h-32 animate-pulse bg-slate-100 dark:bg-slate-800 rounded-xl" />
        ) : !agentsList || agentsList.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-10 text-slate-400 text-xs gap-2">
            <Users className="w-8 h-8 text-slate-300 dark:text-slate-600" />
            <span>No agents have submitted anything yet.</span>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                  <th className="py-2.5 px-3">Agent ID</th>
                  <th className="py-2.5 px-3">Overall Trust Score</th>
                  <th className="py-2.5 px-3">Tier</th>
                  <th className="py-2.5 px-3">Approval Rate</th>
                  <th className="py-2.5 px-3">Submissions</th>
                  <th className="py-2.5 px-3">Last Active</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                {agentsList.map((agent) => (
                  <tr
                    key={agent.agent_id}
                    onClick={() => {
                      setSearchAgentId(agent.agent_id);
                      setSelectedAgentId(agent.agent_id);
                    }}
                    className={`cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/80 transition-colors ${
                      selectedAgentId === agent.agent_id ? 'bg-emerald-50/60 dark:bg-emerald-950/30' : ''
                    }`}
                  >
                    <td className="py-2.5 px-3 font-mono font-bold text-emerald-700 dark:text-emerald-400">{agent.agent_id}</td>
                    <td className="py-2.5 px-3 font-mono font-bold text-slate-800 dark:text-slate-200">
                      {agent.overall_trust_score !== null ? `${agent.overall_trust_score}/100` : 'N/A'}
                    </td>
                    <td className="py-2.5 px-3">
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-black uppercase border ${TIER_BADGE_CLASS[agent.tier]}`}>
                        {agent.tier}
                      </span>
                    </td>
                    <td className="py-2.5 px-3 font-mono text-slate-600 dark:text-slate-300">{agent.approval_rate}%</td>
                    <td className="py-2.5 px-3 font-mono text-slate-600 dark:text-slate-300">{agent.total_submissions}</td>
                    <td className="py-2.5 px-3 text-slate-500 dark:text-slate-400">
                      {formatDistanceToNow(new Date(agent.last_submitted_at), { addSuffix: true })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {isLoading ? (
        <div className="h-64 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 animate-pulse" />
      ) : score ? (
        <div className="space-y-6">
          {/* KPI Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-2 transition-colors">
              <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Overall Trust Score
              </div>
              <div className="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono tracking-tight">
                {score.overall_trust_score || score.approval_rate}/100
              </div>
              <p className="text-[11px] text-slate-400 dark:text-slate-500">Weighted parameter aggregate score</p>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-2 transition-colors">
              <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <ShieldCheck className="w-4 h-4 text-sky-600 dark:text-sky-400" /> Backcheck Pass Rate
              </div>
              <div className="text-3xl font-black text-slate-900 dark:text-white font-mono tracking-tight">{score.backcheck_pass_rate}%</div>
              <p className="text-[11px] text-slate-400 dark:text-slate-500">Supervisor field re-check confirmation rate</p>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-2 transition-colors">
              <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <Award className="w-4 h-4 text-amber-500 dark:text-amber-400" /> Reliability Tier
              </div>
              <div className="pt-1">
                <span className={`px-3 py-1 rounded-full text-xs font-black border ${tierColor}`}>
                  {score.tier} Tier Agent
                </span>
              </div>
              <p className="text-[11px] text-slate-400 dark:text-slate-500 pt-1">Automated payout tier calculation</p>
            </div>

            <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-5 shadow-xs dark:shadow-xl space-y-2 transition-colors">
              <div className="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                <CheckCircle2 className="w-4 h-4 text-emerald-600 dark:text-emerald-400" /> Approval Rate
              </div>
              <div className="text-3xl font-black text-slate-900 dark:text-white font-mono tracking-tight">{score.approval_rate}%</div>
              <p className="text-[11px] text-slate-400 dark:text-slate-500">Approved vs. rejected, among decided submissions</p>
            </div>
          </div>

          {/* Recent Trust Score Builds Table */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div>
                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Recent Trust Score Builds & Submission History</h3>
                <p className="text-xs text-slate-500 dark:text-slate-400">Granular parameter breakdown for recent quest submissions by {score.agent_id}</p>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                    <th className="py-2.5 px-3">Submission ID</th>
                    <th className="py-2.5 px-3">Quest Title</th>
                    <th className="py-2.5 px-3">Date</th>
                    <th className="py-2.5 px-3">Photo</th>
                    <th className="py-2.5 px-3">GPS</th>
                    <th className="py-2.5 px-3">Completeness &amp; Timing</th>
                    <th className="py-2.5 px-3">Audit Confirm.</th>
                    <th className="py-2.5 px-3">Total Score</th>
                    <th className="py-2.5 px-3">Outcome</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                  {recentBuilds.map((build, idx) => (
                    <tr key={idx} className="hover:bg-slate-50 dark:hover:bg-slate-900/80 transition-colors">
                      <td className="py-2.5 px-3 font-mono font-bold text-emerald-700 dark:text-emerald-400">{build.submission_id.slice(0, 8)}...</td>
                      <td className="py-2.5 px-3 font-semibold text-slate-800 dark:text-slate-200">{build.quest_title}</td>
                      <td className="py-2.5 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{build.date}</td>
                      <td className="py-2.5 px-3 font-mono font-bold text-emerald-700 dark:text-emerald-400">{build.photo_score}%</td>
                      <td className="py-2.5 px-3 font-mono font-bold text-emerald-700 dark:text-emerald-400">{build.gps_score}%</td>
                      {/* Completeness (10%) and Timing (30%) are still scored
                          independently on the backend — real diagnostic value
                          in knowing which one is dragging a score down — but
                          shown as one blended 40% category here, matching the
                          4-category Trust Score breakdown everywhere else.
                          Recent-builds rows don't carry per-submission
                          weights_used, so this uses the default 25%/75% split
                          (10%/30% of the total) that every quest actually uses
                          today. */}
                      <td className="py-2.5 px-3 font-mono font-bold text-emerald-700 dark:text-emerald-400">
                        {Math.round((build.completeness_score * 0.25 + build.time_score * 0.75) * 10) / 10}%
                      </td>
                      <td className="py-2.5 px-3 font-mono font-bold text-amber-600 dark:text-amber-400">{build.audit_confirmation_score}%</td>
                      <td className="py-2.5 px-3">
                        <span className="px-2.5 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 font-black font-mono">
                          {build.trust_score}/100
                        </span>
                      </td>
                      <td className="py-2.5 px-3">
                        <span className={`px-2 py-0.5 rounded text-[10px] font-black uppercase ${
                          build.outcome === 'approved' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' :
                          build.outcome === 'rejected' ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-800' : 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800'
                        }`}>
                          {build.outcome}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div>
                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">Rejection Breakdown by Reason Code</h3>
                <p className="text-xs text-slate-500 dark:text-slate-400">Total historical rejections categorized by rule trigger</p>
              </div>
            </div>

            <div className="h-72 w-full pt-4">
              {score.rejection_breakdown.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={score.rejection_breakdown} margin={{ top: 10, right: 30, left: 0, bottom: 25 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke={theme === 'dark' ? '#1E293B' : '#E2E8F0'} />
                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: theme === 'dark' ? '#94A3B8' : '#64748B' }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: theme === 'dark' ? '#94A3B8' : '#64748B' }} />
                    <Tooltip
                      contentStyle={{
                        borderRadius: '12px',
                        background: theme === 'dark' ? '#0F172A' : '#FFFFFF',
                        border: theme === 'dark' ? '1px solid #334155' : '1px solid #E2E8F0',
                        color: theme === 'dark' ? '#FFF' : '#000',
                      }}
                    />
                    <Bar dataKey="count" fill="#10B981" radius={[6, 6, 0, 0]} maxBarSize={50} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="flex flex-col items-center justify-center h-full text-slate-400 text-xs">
                  <CheckCircle2 className="w-8 h-8 text-emerald-500 mb-2" />
                  <span>No rejections recorded for this agent! Perfect record.</span>
                </div>
              )}
            </div>
          </div>

          {/* Answer Pattern Validation */}
          <div className="bg-white dark:bg-[#0F172A] rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs dark:shadow-xl space-y-4 transition-colors">
            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
              <div>
                <h3 className="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide flex items-center gap-1.5">
                  <Repeat className="w-4 h-4 text-slate-500 dark:text-slate-400" /> Answer Pattern Analysis
                </h3>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  Per-question repetition rate across this agent's own submission history for each quest — a low-variance answer suggests low engagement, not just an unlucky outlet.
                </p>
              </div>
              {answerPatterns && (
                <span
                  className={`px-3 py-1 rounded-full text-xs font-black border shrink-0 ${
                    answerPatterns.questions_flagged > 0
                      ? 'bg-rose-100 dark:bg-rose-950 text-rose-900 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                      : 'bg-emerald-100 dark:bg-emerald-950 text-emerald-900 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                  }`}
                >
                  {answerPatterns.questions_flagged} of {answerPatterns.questions_evaluated} flagged
                </span>
              )}
            </div>

            {patternsLoading ? (
              <div className="h-24 animate-pulse bg-slate-100 dark:bg-slate-800 rounded-xl" />
            ) : !answerPatterns || answerPatterns.quests.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-10 text-slate-400 text-xs gap-2">
                <CheckCircle2 className="w-8 h-8 text-emerald-500" />
                <span>No repetitive-answer patterns detected — not enough history yet, or answers are healthily varied.</span>
              </div>
            ) : (
              <div className="space-y-5">
                {answerPatterns.quests.map((quest) => (
                  <div key={quest.quest_id ?? 'unknown'}>
                    <p className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
                      {quest.quest_title ?? quest.quest_id} <span className="font-mono text-slate-400 dark:text-slate-500">({quest.quest_id})</span>
                    </p>
                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs border-collapse">
                        <thead>
                          <tr className="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 font-bold border-b border-slate-200 dark:border-slate-800 text-[11px]">
                            <th className="py-2 px-3">Question ID</th>
                            <th className="py-2 px-3">Dominant Answer</th>
                            <th className="py-2 px-3">Occurrences</th>
                            <th className="py-2 px-3">Repetition Rate</th>
                            <th className="py-2 px-3">Result</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                          {quest.questions.map((q) => (
                            <tr key={q.question_id} className="hover:bg-slate-50 dark:hover:bg-slate-900/80 transition-colors">
                              <td className="py-2 px-3 font-mono text-slate-700 dark:text-slate-300">{q.question_id}</td>
                              <td className="py-2 px-3 font-mono text-slate-600 dark:text-slate-400">"{q.dominant_value}"</td>
                              <td className="py-2 px-3 font-mono text-slate-500 dark:text-slate-400">
                                {q.dominant_count} / {q.total_evaluations}
                              </td>
                              <td className="py-2 px-3 font-mono font-bold text-slate-800 dark:text-slate-200">{q.repetition_ratio}%</td>
                              <td className="py-2 px-3">
                                {q.result === 'pass' ? (
                                  <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                    <CheckCircle2 className="w-3 h-3" /> Pass
                                  </span>
                                ) : (
                                  <span
                                    className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black uppercase border ${
                                      q.result === 'fail'
                                        ? 'bg-rose-100 dark:bg-rose-950 text-rose-800 dark:text-rose-300 border-rose-200 dark:border-rose-800'
                                        : 'bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                                    }`}
                                  >
                                    <AlertTriangle className="w-3 h-3" /> {q.result}
                                  </span>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      ) : null}
    </div>
  );
};
