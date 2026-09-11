<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { dashboardApi } from '@/api'
import { errorMessage } from '@/api/http'
import StatusBadge from '@/components/StatusBadge.vue'
import type { Dashboard } from '@/types'
import { SEVERITY_LABELS } from '@/types'

const dashboard = ref<Dashboard | null>(null)
const error = ref<string | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    dashboard.value = await dashboardApi.overview()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
})

function card(label: string, value: number | undefined, icon: string, tone?: string) {
  return { label, value: value ?? 0, icon, tone }
}
</script>

<template>
  <div>
    <h1 class="text-h5 font-weight-bold mb-1">Panel</h1>
    <p class="text-body-2 text-medium-emphasis mb-6">
      Estado actual de la infraestructura que administras.
    </p>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <template v-if="dashboard">
      <v-row dense>
        <v-col
          v-for="item in [
            card('Clientes activos', dashboard.totals.clients, 'mdi-domain'),
            card('Activos', dashboard.totals.assets, 'mdi-server'),
            card('Checks', dashboard.totals.checks, 'mdi-check-network-outline'),
          ]"
          :key="item.label"
          cols="12"
          sm="6"
          md="4"
        >
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">{{ item.label }}</div>
            <div class="text-h5 font-weight-bold">{{ item.value }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row dense class="mt-2">
        <v-col
          v-for="item in [
            card('Saludables', dashboard.totals.healthy, 'mdi-check-circle', 'success'),
            card('Con atención', dashboard.totals.warning, 'mdi-alert', 'warning'),
            card('Críticos', dashboard.totals.critical, 'mdi-close-circle', 'error'),
            card('Sin datos', dashboard.totals.never_collected, 'mdi-database-off', 'grey'),
          ]"
          :key="item.label"
          cols="6"
          md="3"
        >
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">
              <v-icon size="14" :color="item.tone">{{ item.icon }}</v-icon>
              {{ item.label }}
            </div>
            <div class="text-h6 font-weight-bold">{{ item.value }}</div>
          </v-card>
        </v-col>
      </v-row>

      <v-row dense class="mt-2">
        <v-col
          v-for="item in [
            card('Incidentes abiertos', dashboard.totals.open_incidents, 'mdi-alert-octagon', 'error'),
            card('Backups fallidos', dashboard.totals.failed_backups, 'mdi-backup-restore', 'error'),
            card('Certificados por vencer', dashboard.totals.expiring_certificates, 'mdi-certificate', 'warning'),
            card('Servidores con updates', dashboard.totals.pending_updates, 'mdi-package-up', 'warning'),
          ]"
          :key="item.label"
          cols="6"
          md="3"
        >
          <v-card class="pa-4">
            <div class="text-caption text-medium-emphasis">
              <v-icon size="14" :color="item.tone">{{ item.icon }}</v-icon>
              {{ item.label }}
            </div>
            <div class="text-h6 font-weight-bold">{{ item.value }}</div>
          </v-card>
        </v-col>
      </v-row>

      <h2 class="text-subtitle-1 font-weight-bold mt-8 mb-2">Problemas abiertos</h2>

      <v-card v-if="dashboard.issues.length === 0" class="pa-6 text-medium-emphasis">
        No hay problemas abiertos en ninguna cuenta de cliente.
      </v-card>

      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>Problema</th>
            <th>Severidad</th>
            <th>Abierto</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="issue in dashboard.issues" :key="issue.id">
            <td>
              <div class="font-weight-medium">{{ issue.title }}</div>
              <div v-if="issue.recommendation" class="text-caption text-medium-emphasis">
                {{ issue.recommendation }}
              </div>
            </td>
            <td>{{ SEVERITY_LABELS[issue.severity] }}</td>
            <td class="text-caption">{{ new Date(issue.opened_at).toLocaleString('es-CL') }}</td>
          </tr>
        </tbody>
      </v-table>

      <h2 class="text-subtitle-1 font-weight-bold mt-8 mb-2">Clientes</h2>

      <v-table density="comfortable">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Críticos</th>
            <th>Atención</th>
            <th>Incidentes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in dashboard.clients" :key="client.id">
            <td>
              <router-link :to="`/clients/${client.id}`" class="text-decoration-none">
                {{ client.name }}
              </router-link>
            </td>
            <td><StatusBadge :status="client.worst_status" /></td>
            <td>{{ client.critical }}</td>
            <td>{{ client.warning }}</td>
            <td>{{ client.open_incidents }}</td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </div>
</template>
