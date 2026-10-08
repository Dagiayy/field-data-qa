import { apiClient } from './client';
import type { Outlet, OutletBaseline, PaginatedResponse } from '../types';
import { MOCK_OUTLETS, MOCK_BASELINES_MAP } from './mockData';

export async function getOutletsApi(params: { search?: string; page?: number } = {}): Promise<PaginatedResponse<Outlet>> {
  try {
    const response = await apiClient.get<PaginatedResponse<Outlet>>('/outlets', { params });
    return response.data;
  } catch {
    let filtered = [...MOCK_OUTLETS];
    if (params.search) {
      const q = params.search.toLowerCase();
      filtered = filtered.filter((o) => o.name.toLowerCase().includes(q) || o.branch.toLowerCase().includes(q) || o.city.toLowerCase().includes(q));
    }
    const page = params.page || 1;
    const perPage = 10;
    const total = filtered.length;

    return {
      data: filtered.slice((page - 1) * perPage, page * perPage),
      meta: { current_page: page, last_page: Math.ceil(total / perPage) || 1, total },
    };
  }
}

export async function createBaselineApi(outletId: string, formData: FormData): Promise<OutletBaseline> {
  try {
    const response = await apiClient.post<OutletBaseline>(`/outlets/${outletId}/baselines`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  } catch {
    const newBaseline: OutletBaseline = {
      id: `base-${Date.now()}`,
      spot_label: (formData.get('spot_label') as string) || 'New Spot',
      baseline_gps_lat: parseFloat((formData.get('baseline_gps_lat') as string) || '8.9954'),
      baseline_gps_lng: parseFloat((formData.get('baseline_gps_lng') as string) || '38.7831'),
      baseline_gps_radius_m: parseFloat((formData.get('baseline_gps_radius_m') as string) || '50'),
      photo_url: 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=800&q=80',
      captured_by: 'Current Field Ops User',
      captured_at: new Date().toISOString(),
      notes: (formData.get('notes') as string) || undefined,
    };

    if (!MOCK_BASELINES_MAP[outletId]) {
      MOCK_BASELINES_MAP[outletId] = [];
    }
    MOCK_BASELINES_MAP[outletId].unshift(newBaseline);

    return newBaseline;
  }
}

export async function updateBaselineApi(outletId: string, baselineId: string, formData: FormData): Promise<OutletBaseline> {
  try {
    // A real HTTP PUT with a multipart body doesn't populate PHP's $_FILES
    // (that only happens for POST) — so the file would silently vanish
    // server-side. POST with Laravel's standard `_method` override field
    // is the documented workaround: the route stays `Route::put(...)`,
    // Laravel treats this POST as a PUT once it sees the field.
    formData.append('_method', 'PUT');
    const response = await apiClient.post<OutletBaseline>(`/outlets/${outletId}/baselines/${baselineId}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  } catch {
    const list = MOCK_BASELINES_MAP[outletId] || [];
    const index = list.findIndex((b) => b.id === baselineId);
    
    const updated: OutletBaseline = {
      id: baselineId,
      spot_label: (formData.get('spot_label') as string) || (list[index]?.spot_label ?? 'Spot'),
      baseline_gps_lat: parseFloat((formData.get('baseline_gps_lat') as string) || String(list[index]?.baseline_gps_lat ?? 8.9954)),
      baseline_gps_lng: parseFloat((formData.get('baseline_gps_lng') as string) || String(list[index]?.baseline_gps_lng ?? 38.7831)),
      baseline_gps_radius_m: parseFloat((formData.get('baseline_gps_radius_m') as string) || String(list[index]?.baseline_gps_radius_m ?? 50)),
      photo_url: list[index]?.photo_url || 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=800&q=80',
      captured_by: list[index]?.captured_by || 'Updated User',
      captured_at: new Date().toISOString(),
      notes: (formData.get('notes') as string) || list[index]?.notes,
    };

    if (index !== -1) {
      list[index] = updated;
    }
    return updated;
  }
}
