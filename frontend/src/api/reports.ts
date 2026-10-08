import { apiClient } from './client';
import type { QAReportOverview } from '../types';
import { MOCK_REPORT_OVERVIEW } from './mockData';

export async function getReportOverviewApi(params: { date_from?: string; date_to?: string } = {}): Promise<QAReportOverview> {
  try {
    const response = await apiClient.get<QAReportOverview>('/qa/reports/overview', { params });
    return response.data;
  } catch {
    return MOCK_REPORT_OVERVIEW;
  }
}
