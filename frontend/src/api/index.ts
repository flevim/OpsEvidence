import { http } from './http'
import type {
  ApiToken,
  Asset,
  Check,
  Client,
  Dashboard,
  Evidence,
  Incident,
  Paginated,
  Report,
  User,
} from '@/types'

export const authApi = {
  async login(email: string, password: string) {
    const { data } = await http.post<{ token: string; user: User }>('/api/auth/login', {
      email,
      password,
      device_name: 'web',
    })
    return data
  },
  async logout() {
    await http.post('/api/auth/logout')
  },
  async me() {
    const { data } = await http.get<{ user: User }>('/api/auth/me')
    return data.user
  },
}

export const dashboardApi = {
  async overview() {
    const { data } = await http.get<Dashboard>('/api/dashboard')
    return data
  },
  async forClient(clientId: number) {
    const { data } = await http.get<Record<string, unknown>>('/api/dashboard', {
      params: { client_id: clientId },
    })
    return data
  },
}

export const clientsApi = {
  async list(params: Record<string, unknown> = {}) {
    const { data } = await http.get<Paginated<Client>>('/api/clients', { params })
    return data
  },
  async create(payload: Partial<Client>) {
    const { data } = await http.post<Client>('/api/clients', payload)
    return data
  },
  async summary(clientId: number) {
    const { data } = await http.get<Record<string, unknown>>(`/api/clients/${clientId}/summary`)
    return data
  },
  async assets(clientId: number) {
    const { data } = await http.get<Paginated<Asset>>(`/api/clients/${clientId}/assets`)
    return data
  },
}

export const assetsApi = {
  async create(clientId: number, payload: Partial<Asset>) {
    const { data } = await http.post<Asset>(`/api/clients/${clientId}/assets`, payload)
    return data
  },
  async checks(assetId: number) {
    const { data } = await http.get<{ data: Check[] }>(`/api/assets/${assetId}/checks`)
    return data.data
  },
  async evidence(assetId: number) {
    const { data } = await http.get<Paginated<Evidence>>(`/api/assets/${assetId}/evidence`)
    return data
  },
}

export const incidentsApi = {
  async list(params: Record<string, unknown> = {}) {
    const { data } = await http.get<Paginated<Incident>>('/api/incidents', { params })
    return data
  },
  async update(id: number, status: string, note?: string) {
    const { data } = await http.patch<Incident>(`/api/incidents/${id}`, { status, note })
    return data
  },
}

export const reportsApi = {
  async list(params: Record<string, unknown> = {}) {
    const { data } = await http.get<Paginated<Report>>('/api/reports', { params })
    return data
  },
  async generate(clientId: number, periodStart: string, periodEnd: string) {
    const { data } = await http.post<Report>('/api/reports', {
      client_id: clientId,
      period_start: periodStart,
      period_end: periodEnd,
    })
    return data
  },
  async markSent(id: number) {
    const { data } = await http.post<Report>(`/api/reports/${id}/mark-sent`)
    return data
  },
  async send(id: number, payload: { email?: string; note?: string } = {}) {
    const { data } = await http.post<{ message: string; sent_to: string; sent_at: string }>(
      `/api/reports/${id}/send`,
      payload,
    )
    return data
  },
}

export const tokensApi = {
  async list() {
    const { data } = await http.get<{ data: ApiToken[] }>('/api/api-tokens')
    return data.data
  },
  async create(payload: { name: string; client_id?: number | null }) {
    const { data } = await http.post<ApiToken>('/api/api-tokens', payload)
    return data
  },
  async revoke(id: number) {
    await http.delete(`/api/api-tokens/${id}`)
  },
}
