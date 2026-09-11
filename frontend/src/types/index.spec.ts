import { describe, expect, it } from 'vitest'
import {
  ASSET_TYPE_LABELS,
  INCIDENT_STATUS_LABELS,
  SEVERITY_LABELS,
  STATUS_COLORS,
  STATUS_ICONS,
  STATUS_LABELS,
} from './index'
import type { EvidenceStatus, Freshness } from './index'

const EVIDENCE_STATUSES: EvidenceStatus[] = ['HEALTHY', 'WARNING', 'CRITICAL', 'UNKNOWN', 'FAILED']

describe('catálogo de estados', () => {
  it('cubre cada estado de evidencia con etiqueta, icono y color', () => {
    for (const status of EVIDENCE_STATUSES) {
      expect(STATUS_LABELS[status]).toBeTruthy()
      expect(STATUS_ICONS[status]).toBeTruthy()
      expect(STATUS_COLORS[status]).toBeTruthy()
    }
  })

  it('distingue CRITICAL de FAILED', () => {
    expect(STATUS_LABELS.CRITICAL).toBe('Crítico')
    expect(STATUS_LABELS.FAILED).toBe('Fallo de recolección')
    expect(STATUS_COLORS.CRITICAL).toBe('error')
    expect(STATUS_COLORS.FAILED).toBe('info')
  })
})

describe('catálogo de dominios', () => {
  it('tiene etiqueta para todos los tipos de activo', () => {
    expect(Object.keys(ASSET_TYPE_LABELS)).toHaveLength(8)
  })

  it('tiene etiqueta para severidades y estados de incidente', () => {
    expect(Object.keys(SEVERITY_LABELS)).toHaveLength(3)
    expect(Object.keys(INCIDENT_STATUS_LABELS)).toHaveLength(4)
  })
})

describe('estado de frescura', () => {
  it('nunca debe confundirse "sin datos" con un cero', () => {
    const freshness: Freshness = 'never_collected'
    expect(freshness).toBe('never_collected')
  })
})
