<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { clientsApi, tokensApi } from '@/api'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import type { ApiToken, Client } from '@/types'

const auth = useAuthStore()
const tokens = ref<ApiToken[]>([])
const clients = ref<Client[]>([])
const error = ref<string | null>(null)
const dialog = ref(false)
const saving = ref(false)
const issued = ref<ApiToken | null>(null)

const form = ref({ name: '', client_id: null as number | null })

async function load(): Promise<void> {
  try {
    const [tokensData, clientsData] = await Promise.all([tokensApi.list(), clientsApi.list()])
    tokens.value = tokensData
    clients.value = clientsData.data
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

async function create(): Promise<void> {
  saving.value = true
  error.value = null

  try {
    issued.value = await tokensApi.create(form.value)
    form.value = { name: '', client_id: null }
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    saving.value = false
  }
}

async function revoke(token: ApiToken): Promise<void> {
  try {
    await tokensApi.revoke(token.id)
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <h1 class="text-h5 font-weight-bold mb-1">Ajustes</h1>
    <p class="text-body-2 text-medium-emphasis mb-6">Cuenta, sesión y tokens de agente.</p>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <v-card class="pa-4 mb-6">
      <div class="text-subtitle-2 font-weight-bold mb-2">Cuenta</div>
      <div class="text-body-2">{{ auth.user?.name }} · {{ auth.user?.email }}</div>
      <div class="text-caption text-medium-emphasis">
        {{ auth.user?.account?.name }} — plan {{ auth.user?.account?.plan_label }}
        (hasta {{ auth.user?.account?.client_limit ?? '∞' }} clientes)
      </div>
    </v-card>

    <div class="d-flex align-center mb-2">
      <div class="text-subtitle-2 font-weight-bold">Tokens de agente</div>
      <v-spacer />
      <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="dialog = true">Emitir token</v-btn>
    </div>

    <v-alert type="info" variant="tonal" density="compact" class="mb-4">
      El token solo se muestra una vez al emitirlo. El agente lo usa para enviar evidencia
      y nunca puede leer datos de tu cuenta.
    </v-alert>

    <v-card v-if="tokens.length === 0" class="pa-6 text-medium-emphasis">
      No hay tokens emitidos.
    </v-card>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Prefijo</th>
          <th>Cliente</th>
          <th>Último uso</th>
          <th>Estado</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="token in tokens" :key="token.id">
          <td class="font-weight-medium">{{ token.name }}</td>
          <td class="text-caption">{{ token.prefix }}…</td>
          <td class="text-caption">{{ token.client ?? 'Toda la cuenta' }}</td>
          <td class="text-caption">
            {{ token.last_used_at ? new Date(token.last_used_at).toLocaleString('es-CL') : 'Nunca' }}
          </td>
          <td>
            <v-chip :color="token.usable ? 'success' : 'grey'" size="small" variant="tonal" label>
              {{ token.usable ? 'Activo' : 'Revocado' }}
            </v-chip>
          </td>
          <td class="text-right">
            <v-btn v-if="token.usable" size="small" variant="text" color="error" @click="revoke(token)">
              Revocar
            </v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="560">
      <v-card class="pa-4">
        <v-card-title>Emitir token de agente</v-card-title>

        <v-card-text>
          <div v-if="issued?.token">
            <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
              {{ issued.warning }}
            </v-alert>
            <v-text-field :model-value="issued.token" label="Token" readonly />
            <p class="text-caption text-medium-emphasis">
              Úsalo en el servidor con: <code>opsevidence-agent enroll --token &lt;token&gt;</code>
            </p>
          </div>

          <template v-else>
            <v-text-field v-model="form.name" label="Nombre del token" required />
            <v-select
              v-model="form.client_id"
              :items="clients"
              item-title="name"
              item-value="id"
              label="Cliente (opcional)"
              clearable
            />
          </template>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn v-if="issued" variant="text" @click="((dialog = false), (issued = null))">Cerrar</v-btn>
          <template v-else>
            <v-btn variant="text" @click="dialog = false">Cancelar</v-btn>
            <v-btn color="primary" :disabled="!form.name" :loading="saving" @click="create">Emitir</v-btn>
          </template>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
