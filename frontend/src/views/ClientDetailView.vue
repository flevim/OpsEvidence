<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { clientsApi } from '@/api'
import { errorMessage } from '@/api/http'
import StatusBadge from '@/components/StatusBadge.vue'
import type { Asset, Evidence } from '@/types'
import { ASSET_TYPE_LABELS } from '@/types'

const props = defineProps<{ id: string }>()

const clientId = Number(props.id)
const summary = ref<Record<string, unknown> | null>(null)
const assets = ref<Asset[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

interface EvidenceRow {
  id: number
  type_label: string
  status: string
  status_label: string
  title: string
  asset: string | null
  collected_at: string | null
}

onMounted(async () => {
  try {
    const [summaryData, assetsData] = await Promise.all([
      clientsApi.summary(clientId),
      clientsApi.assets(clientId),
    ])

    summary.value = summaryData
    assets.value = assetsData.data
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
})

function totals(): Record<string, number> {
  return (summary.value?.totals ?? {}) as Record<string, number>
}

const clientName = computed<string>(() => {
  const client = summary.value?.client as { name?: string } | undefined
  return client?.name ?? 'Cliente'
})

function recentEvidence(): EvidenceRow[] {
  return (summary.value?.evidence ?? []) as EvidenceRow[]
}
</script>

<template>
  <div>
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-2" to="/clients">
      Volver a clientes
    </v-btn>

    <h1 class="text-h5 font-weight-bold mb-4">{{ clientName }}</h1>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <template v-if="summary">
      <v-row dense>
        <v-col cols="6" md="3">
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">Activos</div>
            <div class="text-h6 font-weight-bold">{{ totals().assets ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" md="3">
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">Saludables</div>
            <div class="text-h6 font-weight-bold">{{ totals().healthy ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" md="3">
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">Con atención</div>
            <div class="text-h6 font-weight-bold">{{ totals().warning ?? 0 }}</div>
          </v-card>
        </v-col>
        <v-col cols="6" md="3">
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">Críticos</div>
            <div class="text-h6 font-weight-bold">{{ totals().critical ?? 0 }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-alert
        v-if="(totals().never_collected ?? 0) > 0"
        type="warning"
        variant="tonal"
        density="compact"
        class="mt-4"
      >
        {{ totals().never_collected }} comprobación(es) no han reportado datos todavía.
        Se muestran como «Sin datos», no como cero.
      </v-alert>

      <h2 class="text-subtitle-1 font-weight-bold mt-8 mb-2">Activos</h2>

      <v-card v-if="assets.length === 0" class="pa-6 text-medium-emphasis">
        Este cliente todavía no tiene activos registrados.
      </v-card>

      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>Activo</th>
            <th>Tipo</th>
            <th>Host / dirección</th>
            <th>Checks</th>
            <th>Última evidencia</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="asset in assets" :key="asset.id">
            <td class="font-weight-medium">{{ asset.name }}</td>
            <td>{{ ASSET_TYPE_LABELS[asset.type] }}</td>
            <td class="text-caption">{{ asset.hostname ?? asset.address ?? '—' }}</td>
            <td>{{ asset.checks_count ?? 0 }}</td>
            <td class="text-caption">
              {{ asset.last_evidence_at ? new Date(asset.last_evidence_at).toLocaleString('es-CL') : 'Sin datos' }}
            </td>
          </tr>
        </tbody>
      </v-table>

      <h2 class="text-subtitle-1 font-weight-bold mt-8 mb-2">Evidencia reciente</h2>

      <v-card v-if="recentEvidence().length === 0" class="pa-6 text-medium-emphasis">
        Sin evidencia registrada en los últimos 30 días.
      </v-card>

      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>Evidencia</th>
            <th>Activo</th>
            <th>Estado</th>
            <th>Recopilada</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in recentEvidence()" :key="row.id">
            <td>
              <div class="font-weight-medium">{{ row.title }}</div>
              <div class="text-caption text-medium-emphasis">{{ row.type_label }}</div>
            </td>
            <td class="text-caption">{{ row.asset ?? '—' }}</td>
            <td><StatusBadge :status="row.status as Evidence['status']" /></td>
            <td class="text-caption">
              {{ row.collected_at ? new Date(row.collected_at).toLocaleString('es-CL') : '—' }}
            </td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </div>
</template>
