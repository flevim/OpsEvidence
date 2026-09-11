<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { incidentsApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { Incident } from '@/types'
import { INCIDENT_STATUS_LABELS, SEVERITY_LABELS } from '@/types'

const incidents = ref<Incident[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const statusFilter = ref<string | null>('open')

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const params = statusFilter.value ? { status: statusFilter.value } : {}
    incidents.value = (await incidentsApi.list(params)).data
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
}

async function changeStatus(incident: Incident, status: string): Promise<void> {
  try {
    await incidentsApi.update(incident.id, status)
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-h5 font-weight-bold mb-1">Problemas</h1>
    <p class="text-body-2 text-medium-emphasis mb-4">
      Situaciones detectadas automáticamente por el motor de reglas.
    </p>

    <v-btn-toggle v-model="statusFilter" mandatory density="comfortable" class="mb-4" @update:model-value="load">
      <v-btn value="open">Abiertos</v-btn>
      <v-btn value="acknowledged">Reconocidos</v-btn>
      <v-btn value="resolved">Resueltos</v-btn>
    </v-btn-toggle>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <v-card v-if="!loading && incidents.length === 0" class="pa-6 text-medium-emphasis">
      No hay problemas con este filtro.
    </v-card>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>Problema</th>
          <th>Cliente</th>
          <th>Severidad</th>
          <th>Estado</th>
          <th>Abierto</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="incident in incidents" :key="incident.id">
          <td>
            <div class="font-weight-medium">{{ incident.title }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ incident.metadata?.recommendation ?? incident.description }}
            </div>
          </td>
          <td class="text-caption">{{ incident.client?.name ?? '—' }}</td>
          <td>
            <v-chip
              :color="incident.severity === 'critical' ? 'error' : 'warning'"
              size="small"
              variant="tonal"
              label
            >
              {{ SEVERITY_LABELS[incident.severity] }}
            </v-chip>
          </td>
          <td>{{ INCIDENT_STATUS_LABELS[incident.status] }}</td>
          <td class="text-caption">{{ new Date(incident.opened_at).toLocaleDateString('es-CL') }}</td>
          <td class="text-right">
            <v-btn
              v-if="incident.status === 'open'"
              size="small"
              variant="text"
              @click="changeStatus(incident, 'acknowledged')"
            >
              Reconocer
            </v-btn>
            <v-btn
              v-if="incident.status !== 'resolved'"
              size="small"
              variant="text"
              color="primary"
              @click="changeStatus(incident, 'resolved')"
            >
              Resolver
            </v-btn>
            <v-btn
              v-if="incident.status !== 'ignored'"
              size="small"
              variant="text"
              @click="changeStatus(incident, 'ignored')"
            >
              Ignorar
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>
