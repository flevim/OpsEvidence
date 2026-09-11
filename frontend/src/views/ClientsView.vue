<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { clientsApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { Client } from '@/types'

const clients = ref<Client[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const dialog = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)

const form = ref({ name: '', description: '', contact_name: '', contact_email: '' })

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    clients.value = (await clientsApi.list()).data
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
}

async function create(): Promise<void> {
  saving.value = true
  formError.value = null

  try {
    await clientsApi.create({ ...form.value })
    dialog.value = false
    form.value = { name: '', description: '', contact_name: '', contact_email: '' }
    await load()
  } catch (exception) {
    formError.value = errorMessage(exception)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-4">
      <div>
        <h1 class="text-h5 font-weight-bold mb-1">Clientes</h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Organizaciones cuya infraestructura administras.
        </p>
      </div>

      <v-spacer />

      <v-btn color="primary" prepend-icon="mdi-plus" @click="dialog = true">Nuevo cliente</v-btn>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="mb-4" />

    <v-card v-if="!loading && clients.length === 0" class="pa-6 text-medium-emphasis">
      Todavía no hay clientes. Crea el primero para empezar a recopilar evidencia.
    </v-card>

    <v-table v-else density="comfortable">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Activos</th>
          <th>Checks</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="client in clients" :key="client.id">
          <td>
            <router-link :to="`/clients/${client.id}`" class="text-decoration-none font-weight-medium">
              {{ client.name }}
            </router-link>
            <div v-if="client.contact_email" class="text-caption text-medium-emphasis">
              {{ client.contact_email }}
            </div>
          </td>
          <td>{{ client.assets_count ?? 0 }}</td>
          <td>{{ client.checks_count ?? 0 }}</td>
          <td>
            <v-chip :color="client.active ? 'success' : 'grey'" size="small" variant="tonal" label>
              {{ client.active ? 'Activo' : 'Inactivo' }}
            </v-chip>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="560">
      <v-card class="pa-4">
        <v-card-title>Nuevo cliente</v-card-title>

        <v-card-text>
          <v-alert v-if="formError" type="error" variant="tonal" density="compact" class="mb-4">
            {{ formError }}
          </v-alert>

          <v-text-field v-model="form.name" label="Nombre" required />
          <v-text-field v-model="form.contact_name" label="Contacto" />
          <v-text-field v-model="form.contact_email" label="Correo de contacto" type="email" />
          <v-textarea v-model="form.description" label="Descripción" rows="2" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancelar</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!form.name" @click="create">Crear</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
