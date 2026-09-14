export type EvidenceStatus = 'HEALTHY' | 'WARNING' | 'CRITICAL' | 'UNKNOWN' | 'FAILED'
export type Freshness = 'fresh' | 'stale' | 'never_collected'

export type AssetType =
  | 'SERVER' | 'WEBSITE' | 'APPLICATION' | 'DATABASE'
  | 'CONTAINER_HOST' | 'REPOSITORY' | 'BACKUP_SOURCE' | 'OTHER'

export type UserRole = 'owner' | 'admin' | 'technician' | 'viewer'
export type IncidentSeverity = 'info' | 'warning' | 'critical'
export type IncidentStatus = 'open' | 'acknowledged' | 'resolved' | 'ignored'

export interface Account {
  id: number
  name: string
  slug: string
  plan: string
  plan_label: string
  status: string
  client_limit: number | null
}

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  role_label: string
  is_active: boolean
  last_login_at: string | null
  account?: Account
}

export interface Client {
  id: number
  name: string
  slug: string
  description: string | null
  contact_name: string | null
  contact_email: string | null
  active: boolean
  assets_count?: number
  checks_count?: number
  created_at: string
}

export interface Asset {
  id: number
  client_id: number
  environment_id: number | null
  name: string
  type: AssetType
  hostname: string | null
  address: string | null
  active: boolean
  last_evidence_at: string | null
  checks_count?: number
}

export interface Check {
  id: number
  asset_id: number
  type: string
  name: string
  interval_seconds: number
  freshness_ttl_seconds: number
  enabled: boolean
  last_status: EvidenceStatus | null
  last_success_at: string | null
  last_error: string | null
  consecutive_failures: number
  freshness: Freshness
  type_label: string
}

export interface Evidence {
  id: number
  asset_id: number
  type: string
  status: EvidenceStatus
  title: string
  value_text: string | null
  value_numeric: number | null
  unit: string | null
  collected_at: string
  asset?: Pick<Asset, 'id' | 'name' | 'type'>
}

export interface Incident {
  id: number
  client_id: number
  asset_id: number | null
  rule_key: string
  severity: IncidentSeverity
  status: IncidentStatus
  title: string
  description: string | null
  opened_at: string
  resolved_at: string | null
  metadata: { recommendation?: string } | null
  asset?: Pick<Asset, 'id' | 'name'>
  client?: Pick<Client, 'id' | 'name'>
}

export interface Paginated<T> {
  data: T[]
  links: Record<string, string | null>
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface Issue {
  id: number
  title: string
  severity: IncidentSeverity
  severity_label: string
  status: IncidentStatus
  status_label: string
  rule_key: string
  client_id: number
  asset_id: number | null
  opened_at: string
  recommendation: string | null
}

export interface DashboardTotals {
  clients: number
  assets: number
  checks: number
  healthy: number
  warning: number
  critical: number
  failed: number
  never_collected: number
  open_incidents: number
  critical_incidents: number
  failed_backups: number
  expiring_certificates: number
  pending_updates: number
}

export interface DashboardClientSummary {
  id: number
  name: string
  slug: string
  critical: number
  warning: number
  healthy: number
  open_incidents: number
  worst_status: EvidenceStatus
  without_data: boolean
}

export interface Dashboard {
  totals: DashboardTotals
  issues: Issue[]
  clients: DashboardClientSummary[]
}

export interface Report {
  id: number
  client_id: number
  period_start: string
  period_end: string
  status: string
  health_score: number | null
  summary: unknown
  sent_at: string | null
  client?: Pick<Client, 'id' | 'name'>
}

export interface ApiToken {
  id: number
  name: string
  prefix: string
  client: string | null
  asset: string | null
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  revoked_at: string | null
  usable: boolean
  token?: string
  warning?: string
}

export const STATUS_LABELS: Record<EvidenceStatus, string> = {
  HEALTHY: 'Saludable',
  WARNING: 'Atención',
  CRITICAL: 'Crítico',
  UNKNOWN: 'Desconocido',
  FAILED: 'Fallo de recolección',
}

export const STATUS_ICONS: Record<EvidenceStatus, string> = {
  HEALTHY: 'mdi-check-circle',
  WARNING: 'mdi-alert',
  CRITICAL: 'mdi-close-circle',
  UNKNOWN: 'mdi-help-circle',
  FAILED: 'mdi-sync-alert',
}

export const STATUS_COLORS: Record<EvidenceStatus, string> = {
  HEALTHY: 'success',
  WARNING: 'warning',
  CRITICAL: 'error',
  UNKNOWN: 'grey',
  FAILED: 'info',
}

export const ASSET_TYPE_LABELS: Record<AssetType, string> = {
  SERVER: 'Servidor',
  WEBSITE: 'Sitio web',
  APPLICATION: 'Aplicación',
  DATABASE: 'Base de datos',
  CONTAINER_HOST: 'Host de contenedores',
  REPOSITORY: 'Repositorio',
  BACKUP_SOURCE: 'Origen de backup',
  OTHER: 'Otro',
}

export const SEVERITY_LABELS: Record<IncidentSeverity, string> = {
  info: 'Informativo',
  warning: 'Advertencia',
  critical: 'Crítico',
}

export const INCIDENT_STATUS_LABELS: Record<IncidentStatus, string> = {
  open: 'Abierto',
  acknowledged: 'Reconocido',
  resolved: 'Resuelto',
  ignored: 'Ignorado',
}

export interface OnboardingStep {
  key: string
  label: string
  description: string
  hint: string
  done: boolean
}

export interface CheckTypeOption {
  value: string
  label: string
  asset_types: string[]
  collected_by_platform: boolean
  default_interval_seconds: number
}

export interface Onboarding {
  completed: number
  total: number
  completion: number
  is_complete: boolean
  next_step: OnboardingStep | null
  steps: OnboardingStep[]
}
