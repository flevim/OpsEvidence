<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { clientsApi, reportsApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { Client, Report } from '@/types'

const reports = ref<Report[]>([])
const clients = ref<Client[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const dialog = ref(false)
const generating = ref(false)

const firstOfMonth = new Date()
firstOfMonth.setDate(1)

const form = ref({
  client_id: null as number | null,
  period_start: firstOfMonth.toISOString().slice(0, 10),
  period_end: new Date().toISOString().slice(0, 10),
})

const canGenerate = computed(() => form.value.client_id !== null)

// El HTML y el PDF viven detras de la autenticacion por Bearer: una navegacion
// directa del navegador no envia la cabecera, asi que se descargan con axios y
// se abren desde un blob local.
async function openReport(report: Report): Promise<void> {
  try {
    const html = await reportsApi.html(report.id)
    const url = URL.createObjectURL(new Blob([html], { type: 'text/html' }))
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

async function openPdf(report: Report): Promise<void> {
  try {
    const blob = await reportsApi.pdfBlob(report.id)
    const url = URL.createObjectURL(blob)
    window.open(url, '_blank', 'noopener')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

const sendingId = ref<number | null>(null)
const feedback = ref<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const [reportsData, clientsData] = await Promise.all([reportsApi.list(), clientsApi.list()])
    reports.value = reportsData.data
    clients.value = clientsData.data
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
}

async function generate(): Promise<void> {
  generating.value = true
  error.value = null

  try {
    const report = await reportsApi.generate(form.value.client_id!, form.value.period_start, form.value.period_end)
    dialog.value = false
    await load()
    window.open(reportUrl(report.id), '_blank', 'noopener')
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    generating.value = false
  }
}

async function markSent(report: Report): Promise<void> {
  try {
    await reportsApi.markSent(report.id)
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

async function sendReport(report: Report): Promise<void> {
  sendingId.value = report.id
  error.value = null

  try {
    const result = await reportsApi.send(report.id)
    feedback.value = result.message
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    sendingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <div>
        <h1 class="text-h5 font-weight-bold mb-1">Informes</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          El documento que recibes por cliente y mes, listo para enviar.
        </p>
      </div>

      <v-spacer />

      <v-btn color="primary" prepend-icon="mdi-file-plus-outline" @click="dialog = true">Generar informe</v-btn>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <v-card v-if="!loading && reports.length === 0" class="pa-6 text-medium-emphasis">
      Todavía no se ha generado ningún informe.
    </v-card>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Periodo</th>
          <th>Índice de salud</th>
          <th>Estado</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="report in reports" :key="report.id">
          <td class="font-weight-medium">{{ report.client?.name ?? '—' }}</td>
          <td class="text-caption">
            {{ report.period_start }} → {{ report.period_end }}
          </td>
          <td>
            <span v-if="report.health_score !== null">{{ report.health_score }}/100</span>
            <span v-else class="text-medium-emphasis">Sin datos</span>
          </td>
          <td>{{ report.status }}</td>
          <td class="text-right">
            <v-btn size="small" variant="text" @click="openReport(report)">Ver informe</v-btn>
            <v-btn size="small" variant="text" @click="openPdf(report)">PDF</v-btn>
            <v-btn
              size="small"
              variant="text"
              color="primary"
              :loading="sendingId === report.id"
              @click="sendReport(report)"
            >
              Enviar al cliente
            </v-btn>
            <v-btn
              v-if="report.status !== 'sent'"
              size="small"
              variant="text"
              color="primary"
              @click="markSent(report)"
            >
              Marcar enviado
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="520">
      <v-card class="pa-4">
        <v-card-title>Generar informe mensual</v-card-title>

        <v-card-text>
          <v-select
            v-model="form.client_id"
            :items="clients"
            item-title="name"
            item-value="id"
            label="Cliente"
          />
          <v-text-field v-model="form.period_start" label="Desde" type="date" />
          <v-text-field v-model="form.period_end" label="Hasta" type="date" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancelar</v-btn>
          <v-btn color="primary" :disabled="!canGenerate" :loading="generating" @click="generate">
            Generar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar
      :model-value="feedback !== null"
      color="success"
      :timeout="4000"
      @update:model-value="feedback = null"
    >
      {{ feedback }}
    </v-snackbar>
  </div>
</template>
