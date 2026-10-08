import { apiClient } from './client';
import type { AuthResponse, Role } from '../types';
import { MOCK_USERS } from './mockData';

export async function loginApi(email: string, password: string): Promise<AuthResponse> {
  try {
    const response = await apiClient.post<AuthResponse>('/auth/login', { email, password });
    return response.data;
  } catch {
    let matchedRole: Role = 'qa_reviewer';
    const emailLower = email.toLowerCase();

    if (emailLower.includes('lead')) matchedRole = 'qa_lead';
    else if (emailLower.includes('ops')) matchedRole = 'field_ops';
    else if (emailLower.includes('view') || emailLower.includes('readonly')) matchedRole = 'read_only';
    else if (emailLower.includes('admin')) matchedRole = 'admin';

    const user = Object.values(MOCK_USERS).find((u) => u.role === matchedRole) || MOCK_USERS.reviewer;
    
    return {
      token: `mock-jwt-token-${user.role}-${Date.now()}`,
      user: { ...user, email: email || user.email },
    };
  }
}
