import { apiClient } from './client';
import type { Submission, RejectionReason, ReviewPayload, PaginatedResponse } from '../types';
import { MOCK_SUBMISSIONS_MAP, MOCK_REJECTION_REASONS } from './mockData';

export async function getSubmissionApi(id: string): Promise<Submission> {
  try {
    const response = await apiClient.get<Submission>(`/qa/submissions/${id}`);
    return response.data;
  } catch {
    const mock = MOCK_SUBMISSIONS_MAP[id] || MOCK_SUBMISSIONS_MAP['sub-101'];
    return { ...mock, id };
  }
}

export async function reviewSubmissionApi(
  id: string,
  payload: ReviewPayload
): Promise<{ success: boolean; submission: { id: string; status: string } }> {
  try {
    const response = await apiClient.post<{ success: boolean; submission: { id: string; status: string } }>(
      `/qa/submissions/${id}/review`,
      payload
    );
    return response.data;
  } catch {
    let newStatus = 'pending';
    if (payload.decision === 'approve') newStatus = 'approved';
    else if (payload.decision === 'reject') newStatus = 'rejected';
    else if (payload.decision === 'flag_backcheck') newStatus = 'backcheck';
    else if (payload.decision === 'send_back') newStatus = 'sent_back';

    if (MOCK_SUBMISSIONS_MAP[id]) {
      MOCK_SUBMISSIONS_MAP[id].status = newStatus as any;
      MOCK_SUBMISSIONS_MAP[id].review_history.push({
        id: `rev-${Date.now()}`,
        reviewer_name: 'Current Reviewer',
        decision: payload.decision,
        reason_code: payload.reason_code || null,
        note: payload.note ? `${payload.note}${payload.backcheck_outcome ? ` (Outcome: ${payload.backcheck_outcome})` : ''}` : null,
        reviewed_at: new Date().toISOString(),
      });
    }

    return {
      success: true,
      submission: { id, status: newStatus },
    };
  }
}

export async function getRejectionReasonsApi(): Promise<PaginatedResponse<RejectionReason>> {
  try {
    const response = await apiClient.get<PaginatedResponse<RejectionReason>>('/qa/rejection-reasons');
    return response.data;
  } catch {
    return {
      data: MOCK_REJECTION_REASONS,
      meta: { current_page: 1, last_page: 1, total: MOCK_REJECTION_REASONS.length },
    };
  }
}
