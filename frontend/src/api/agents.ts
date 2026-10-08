import { apiClient } from './client';
import type { AgentTrustScore, AgentAnswerPatternSummary, AgentListItem } from '../types';
import { MOCK_TRUST_SCORES } from './mockData';

// All-agents overview (App\Http\Controllers\Qa\AgentListController) — no
// mock fallback, an empty list is a legitimate ("no submissions yet")
// result, not a loading failure.
export async function getAgentsListApi(): Promise<AgentListItem[]> {
  const response = await apiClient.get<{ data: AgentListItem[] }>('/agents');
  return response.data.data;
}

export async function getAgentTrustScoreApi(agentId: string): Promise<AgentTrustScore> {
  try {
    const response = await apiClient.get<AgentTrustScore>(`/agents/${agentId}/trust-score`);
    return response.data;
  } catch {
    const score = MOCK_TRUST_SCORES[agentId] || {
      agent_id: agentId,
      approval_rate: 88.0,
      backcheck_pass_rate: 92.5,
      tier: 'Silver',
      rejection_breakdown: [
        { reason_code: 'BLURRY_PHOTO', label: 'Blurry Photo', count: 4 },
        { reason_code: 'OUT_OF_GEOFENCE', label: 'Outside Geofence', count: 2 },
      ],
    };
    return score;
  }
}

// Answer Pattern Validation: per-agent, per-(quest, question) breakdown of
// how often this agent gives the same answer, surfaced from the real Layer 1
// QA engine (App\Services\Qa\AgentAnswerPatternAnalyzer) — no mock fallback,
// an empty breakdown is a legitimate ("clean") result, not a loading failure.
export async function getAgentAnswerPatternsApi(agentId: string): Promise<AgentAnswerPatternSummary> {
  const response = await apiClient.get<AgentAnswerPatternSummary>(`/qa/agents/${agentId}/answer-patterns`);
  return response.data;
}
