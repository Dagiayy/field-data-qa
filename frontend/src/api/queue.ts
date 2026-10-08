import { apiClient } from './client';
import type { QueueItem, PaginatedResponse } from '../types';
import { MOCK_QUEUE_ITEMS } from './mockData';

export interface QueueQueryParams {
  quest_id?: string;
  outlet_id?: string;
  agent_id?: string;
  date_from?: string;
  date_to?: string;
  has_flags?: boolean;
  page?: number;
  status?: string;
}

export async function getQueueApi(params: QueueQueryParams = {}): Promise<PaginatedResponse<QueueItem>> {
  try {
    const response = await apiClient.get<PaginatedResponse<QueueItem>>('/qa/queue', { params });
    return response.data;
  } catch {
    let filtered = [...MOCK_QUEUE_ITEMS];

    if (params.status) {
      filtered = filtered.filter((i) => i.status === params.status);
    } else {
      filtered = filtered.filter((i) => i.status === 'pending' || i.status === 'backcheck' || i.status === 'sent_back');
    }

    if (params.quest_id) {
      filtered = filtered.filter((i) => i.quest.id === params.quest_id);
    }
    if (params.outlet_id) {
      filtered = filtered.filter((i) => i.outlet.id === params.outlet_id);
    }
    if (params.agent_id) {
      filtered = filtered.filter((i) => i.agent_id.toLowerCase().includes(params.agent_id!.toLowerCase()));
    }
    if (params.has_flags) {
      filtered = filtered.filter((i) => i.flags.some((f) => f.result === 'fail' || f.result === 'flag'));
    }

    filtered.sort((a, b) => {
      const aFlagged = a.flags.some((f) => f.result === 'fail' || f.result === 'flag') ? 1 : 0;
      const bFlagged = b.flags.some((f) => f.result === 'fail' || f.result === 'flag') ? 1 : 0;
      if (bFlagged !== aFlagged) return bFlagged - aFlagged;
      return new Date(a.submitted_at).getTime() - new Date(b.submitted_at).getTime();
    });

    const page = params.page || 1;
    const perPage = 10;
    const total = filtered.length;
    const last_page = Math.ceil(total / perPage) || 1;
    const paginatedData = filtered.slice((page - 1) * perPage, page * perPage);

    return {
      data: paginatedData,
      meta: {
        current_page: page,
        last_page,
        total,
      },
    };
  }
}
