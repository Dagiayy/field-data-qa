import { apiClient } from './client';
import type { PaginatedResponse, QuestBaselineConfig, QuestListItem } from '../types';

export async function listQuestsApi(): Promise<PaginatedResponse<QuestListItem>> {
  const response = await apiClient.get<PaginatedResponse<QuestListItem>>('/quests');
  return response.data;
}

export async function getQuestBaselineApi(questId: string): Promise<QuestBaselineConfig> {
  const response = await apiClient.get<QuestBaselineConfig>(`/quests/${questId}/baseline`);
  return response.data;
}

export async function updateQuestBaselineApi(
  questId: string,
  payload: {
    expected_duration_min_seconds: number | null;
    expected_duration_max_seconds: number | null;
    price_ranges: { sku_id: string; min: number; max: number; currency: string; unit?: string }[];
  }
): Promise<QuestBaselineConfig> {
  const response = await apiClient.put<QuestBaselineConfig>(`/quests/${questId}/baseline`, payload);
  return response.data;
}
