import { apiClient } from './client';

export interface TestIngestResponse {
  success: boolean;
  submission_id: string;
  status: string;
  flags_raised: number;
}

export interface TestIngestValidationError {
  message: string;
  errors: Record<string, string[]>;
}

// Manual payload tester — hits the same IngestionController@store action
// the real Mini App pipeline will call, minus the shared-secret key (the QA
// dashboard is already a trusted caller). No mock fallback: this exists
// specifically to exercise the real backend before that integration is live.
export async function testIngestApi(envelope: unknown): Promise<TestIngestResponse> {
  const response = await apiClient.post<TestIngestResponse>('/qa/test-ingest', envelope);
  return response.data;
}
