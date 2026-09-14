<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { assetsApi, checkTypesApi, checksApi, clientsApi } from '@/api'
import { errorMessage } from '@/api/http'
import StatusBadge from '@/components/StatusBadge.vue'
import type {
  Asset,
  AssetType,
  Check,
  CheckTypeOption,
  Evidence,
  Onboarding,
  OnboardingStep,
} from '@/types'
import { ASSET_TYPE_LABELS } from '@/types'

const props = defineProps<{ id: string }>()

const clientId = Number(props.id)
const summary = ref<Record<string, unknown> | null>(null)
const assets = ref<Asset[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const feedback = ref<string | null>(null)

interface EvidenceRow {
  id: number
  type_label: string
  status: string
  status_label: string
  title: string
  asset: string | null
  collected_at: string | null
}

const checkTypes = ref<CheckTypeOption[]>([])

const assetDialog = ref(false)
const savingAsset = ref(false)
const assetForm = ref<{ name: string; type: AssetType; hostname: string; address: string }>({
  name: '',
  type: 'SERVER',
  hostname: '',
  address: '',
})

const checksDialog = ref(false)
const checksAsset = ref<Asset | null>(null)
const assetChecks = ref<Check[]>([])
const savingCheck = ref(false)
const checkForm = ref({ type: '', name: '', url: '' })

const assetTypeOptions = Object.entries(ASSET_TYPE_LABELS).map(([value, title]) => ({ value, title }))

const availableCheckTypes = computed<CheckTypeOption[]>(() => {
  const assetType = checksAsset.value?.type
  if (!assetType) return []
  return checkTypes.value.filter((type) => type.asset_types.includes(assetType))
})

const selectedCheckType = computed<CheckTypeOption | null>(
  () => checkTypes.value.find((type) => type.value === checkForm.value.type) ?? null,
)

const needsUrl = computed<boolean>(() =>
  ['HTTP_STATUS', 'HTTP_RESPONSE_TIME', 'SSL_EXPIRATION'].includes(selectedCheckType.value?.value ?? ''),
)

async function load(): Promise<void> {
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
}

async function loadCheckTypes(): Promise<void> {
  try {
    checkTypes.value = await checkTypesApi.list()
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

async function createAsset(): Promise<void> {
  savingAsset.value = true
  error.value = null

  try {
    await assetsApi.create(clientId, { ...assetForm.value })
    assetDialog.value = false
    assetForm.value = { name: '', type: 'SERVER', hostname: '', address: '' }
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    savingAsset.value = false
  }
}

async function openChecks(asset: Asset): Promise<void> {
  checksAsset.value = asset
  checksDialog.value = true
  checkForm.value = { type: '', name: '', url: '' }
  await loadChecks()
}

async function loadChecks(): Promise<void> {
  if (!checksAsset.value) return

  try {
    assetChecks.value = await assetsApi.checks(checksAsset.value.id)
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

function isRunnable(check: Check): boolean {
  return checkTypes.value.find((type) => type.value === check.type)?.collected_by_platform ?? false
}

async function createCheck(): Promise<void> {
  if (!checksAsset.value) return

  savingCheck.value = true
  error.value = null

  try {
    await checksApi.create(checksAsset.value.id, {
      type: checkForm.value.type,
      name: checkForm.value.name,
      configuration: needsUrl.value ? { url: checkForm.value.url } : {},
    })

    checkForm.value = { type: '', name: '', url: '' }
    await loadChecks()
    await load()
    feedback.value = 'Comprobación creada.'
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    savingCheck.value = false
  }
}

async function toggleCheck(check: Check): Promise<void> {
  try {
    await checksApi.update(check.id, { enabled: !check.enabled })
    await loadChecks()
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

async function runCheck(check: Check): Promise<void> {
  try {
    await checksApi.run(check.id)
    feedback.value = 'Comprobación en cola: en unos segundos habrá evidencia nueva.'
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

const clientName = computed<string>(() => {
  const client = summary.value?.client as { name?: string } | undefined
  return client?.name ?? 'Cliente'
})

const onboarding = computed<Onboarding | null>(
  () => (summary.value?.onboarding as Onboarding | undefined) ?? null,
)

const pendingSteps = computed<OnboardingStep[]>(
  () => onboarding.value?.steps.filter((step) => !step.done) ?? [],
)

function totals(): Record<string, number> {
  return (summary.value?.totals ?? {}) as Record<string, number>
}

function recentEvidence(): EvidenceRow[] {
  return (summary.value?.evidence ?? []) as EvidenceRow[]
}

onMounted(async () => {
  await Promise.all([load(), loadCheckTypes()])
})
</script>

<template>
  <div>
    <v-btn variant="text" prepend-icon="mdi-arrow-left" class="mb-2" to="/clients">
      Volver a clientes
    </v-btn>

    <h1 class="text-h5 font-weight-bold mb-4">{{ clientName }}</h1>

    <v-card v-if="onboarding && !onboarding.is_complete" class="pa-4 mb-6">
      <div class="d-flex align-center">
        <div>
          <div class="text-subtitle-2 font-weight-bold">Configuración inicial</div>
          <div class="text-caption text-medium-emphasis">
            {{ onboarding.completed }} de {{ onboarding.total }} pasos completados
          </div>
        </div>
        <v-spacer />
        <div class="text-h6">{{ onboarding.completion }}%</div>
      </div>

      <v-progress-linear
        :model-value="onboarding.completion"
        color="primary"
        height="6"
        rounded
        class="my-3"
      />

      <div class="text-caption font-weight-bold text-medium-emphasis mb-1">Qué falta</div>

      <v-list density="compact" class="pa-0">
        <v-list-item
          v-for="step in pendingSteps"
          :key="step.key"
          prepend-icon="mdi-circle-outline"
          :title="step.label"
          :subtitle="step.hint"
        />
      </v-list>
    </v-card>

    <v-alert
      v-else-if="onboarding?.is_complete"
      type="success"
      variant="tonal"
      density="compact"
      class="mb-6"
    >
      Cliente completamente configurado: los {{ onboarding.total }} pasos están completos.
    </v-alert>

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

      <div class="d-flex align-center mt-8 mb-2">
        <h2 class="text-subtitle-1 font-weight-bold mb-0">Activos</h2>
        <v-spacer />
        <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="assetDialog = true">
          Agregar activo
        </v-btn>
      </div>

      <v-card v-if="assets.length === 0" class="pa-6 text-medium-emphasis">
        Este cliente todavía no tiene activos. Agrega el primero para empezar a recolectar.
      </v-card>

      <v-table v-else density="comfortable">
        <thead>
          <tr>
            <th>Activo</th>
            <th>Tipo</th>
            <th>Host / dirección</th>
            <th>Checks</th>
            <th>Última evidencia</th>
            <th />
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
            <td class="text-right">
              <v-btn size="small" variant="text" @click="openChecks(asset)">Checks</v-btn>
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

    <v-dialog v-model="assetDialog" max-width="560">
      <v-card class="pa-4">
        <v-card-title>Agregar activo</v-card-title>

        <v-card-text>
          <v-text-field v-model="assetForm.name" label="Nombre" placeholder="web-server-01" required />
          <v-select
            v-model="assetForm.type"
            :items="assetTypeOptions"
            item-title="title"
            item-value="value"
            label="Tipo"
          />
          <v-text-field v-model="assetForm.hostname" label="Hostname (opcional)" />
          <v-text-field
            v-model="assetForm.address"
            label="Dirección o URL (opcional)"
            placeholder="https://api.example.cl"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="assetDialog = false">Cancelar</v-btn>
          <v-btn color="primary" :disabled="!assetForm.name" :loading="savingAsset" @click="createAsset">
            Agregar
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="checksDialog" max-width="720">
      <v-card class="pa-4">
        <v-card-title>Comprobaciones de {{ checksAsset?.name }}</v-card-title>

        <v-card-text>
          <v-card v-if="assetChecks.length === 0" variant="tonal" class="pa-4 mb-4 text-medium-emphasis">
            Sin comprobaciones. Agrega la primera abajo.
          </v-card>

          <v-table v-else density="compact" class="mb-4">
            <thead>
              <tr>
                <th>Comprobación</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="check in assetChecks" :key="check.id">
                <td>
                  <div class="font-weight-medium">{{ check.name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ check.type_label }} · cada {{ Math.round(check.interval_seconds / 60) }} min
                  </div>
                </td>
                <td>
                  <StatusBadge :status="check.last_status" :freshness="check.freshness" />
                </td>
                <td class="text-right">
                  <v-btn
                    v-if="isRunnable(check)"
                    size="small"
                    variant="text"
                    @click="runCheck(check)"
                  >
                    Ejecutar
                  </v-btn>
                  <v-btn size="small" variant="text" @click="toggleCheck(check)">
                    {{ check.enabled ? 'Desactivar' : 'Activar' }}
                  </v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>

          <v-divider class="mb-4" />

          <div class="text-subtitle-2 font-weight-bold mb-2">Nueva comprobación</div>

          <v-select
            v-model="checkForm.type"
            :items="availableCheckTypes"
            item-title="label"
            item-value="value"
            label="Tipo"
            hide-details="auto"
            class="mb-2"
          />
          <v-text-field
            v-model="checkForm.name"
            label="Nombre"
            :placeholder="selectedCheckType?.label"
            hide-details="auto"
            class="mb-2"
          />
          <v-text-field
            v-if="needsUrl"
            v-model="checkForm.url"
            label="URL a comprobar"
            placeholder="https://api.example.cl"
            hide-details="auto"
          />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="checksDialog = false">Cerrar</v-btn>
          <v-btn
            color="primary"
            :disabled="!checkForm.type || !checkForm.name"
            :loading="savingCheck"
            @click="createCheck"
          >
            Agregar comprobación
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
