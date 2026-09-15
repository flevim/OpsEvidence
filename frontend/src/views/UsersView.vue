<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { usersApi } from '@/api'
import { errorMessage } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import type { User, UserRole } from '@/types'
import { USER_ROLE_LABELS } from '@/types'

const auth = useAuthStore()
const users = ref<User[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const feedback = ref<string | null>(null)
const dialog = ref(false)
const deleteDialog = ref(false)
const selected = ref<User | null>(null)
const pendingDelete = ref<User | null>(null)
const form = ref({ name: '', email: '', password: '', role: 'technician' as UserRole, is_active: true })

const roleOptions = computed(() => Object.entries(USER_ROLE_LABELS)
  .filter(([value]) => auth.user?.role === 'owner' || value !== 'owner')
  .map(([value, title]) => ({ value, title })))

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    users.value = await usersApi.list()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  selected.value = null
  form.value = { name: '', email: '', password: '', role: 'technician', is_active: true }
  dialog.value = true
}

function openEdit(user: User): void {
  selected.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.role,
    is_active: user.is_active,
  }
  dialog.value = true
}

async function save(): Promise<void> {
  saving.value = true
  error.value = null
  try {
    if (selected.value) {
      const payload: Parameters<typeof usersApi.update>[1] = {
        name: form.value.name,
        email: form.value.email,
        role: form.value.role,
        is_active: form.value.is_active,
      }
      if (form.value.password) payload.password = form.value.password
      await usersApi.update(selected.value.id, payload)
      feedback.value = 'Usuario actualizado.'
    } else {
      await usersApi.create(form.value)
      feedback.value = 'Usuario creado.'
    }
    dialog.value = false
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    saving.value = false
  }
}

function askDelete(user: User): void {
  pendingDelete.value = user
  deleteDialog.value = true
}

async function remove(): Promise<void> {
  if (!pendingDelete.value) return
  saving.value = true
  try {
    await usersApi.remove(pendingDelete.value.id)
    deleteDialog.value = false
    pendingDelete.value = null
    feedback.value = 'Usuario eliminado.'
    await load()
  } catch (exception) {
    error.value = errorMessage(exception)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-1">
      <div>
        <h1 class="text-h5 font-weight-bold">Usuarios</h1>
        <p class="text-body-2 text-medium-emphasis">Accesos, roles y estado de los miembros de la cuenta.</p>
      </div>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-account-plus-outline" @click="openCreate">Agregar usuario</v-btn>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="my-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate class="my-4" />

    <v-table v-if="!loading" density="comfortable" class="mt-6">
      <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th /></tr></thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td><div class="font-weight-medium">{{ user.name }}</div><div class="text-caption">{{ user.email }}</div></td>
          <td><v-chip size="small" variant="tonal" label>{{ user.role_label }}</v-chip></td>
          <td><v-chip :color="user.is_active ? 'success' : 'grey'" size="small" variant="tonal" label>{{ user.is_active ? 'Activo' : 'Inactivo' }}</v-chip></td>
          <td class="text-caption">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleString('es-CL') : 'Nunca' }}</td>
          <td class="text-right">
            <v-btn size="small" variant="text" @click="openEdit(user)">Editar</v-btn>
            <v-btn v-if="user.id !== auth.user?.id" size="small" variant="text" color="error" @click="askDelete(user)">Eliminar</v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>

    <v-dialog v-model="dialog" max-width="580">
      <v-card class="pa-4">
        <v-card-title>{{ selected ? 'Editar usuario' : 'Agregar usuario' }}</v-card-title>
        <v-card-text>
          <v-text-field v-model="form.name" label="Nombre" required />
          <v-text-field v-model="form.email" label="Correo" type="email" required />
          <v-text-field v-model="form.password" :label="selected ? 'Nueva contraseña (opcional)' : 'Contraseña'" type="password" :required="!selected" />
          <v-select v-model="form.role" :items="roleOptions" item-title="title" item-value="value" label="Rol" />
          <v-switch v-if="selected" v-model="form.is_active" label="Usuario activo" color="primary" :disabled="selected.id === auth.user?.id" />
        </v-card-text>
        <v-card-actions><v-spacer /><v-btn variant="text" @click="dialog = false">Cancelar</v-btn><v-btn color="primary" :disabled="!form.name || !form.email || (!selected && form.password.length < 8)" :loading="saving" @click="save">Guardar</v-btn></v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="deleteDialog" max-width="460">
      <v-card class="pa-4"><v-card-title>Eliminar usuario</v-card-title><v-card-text>Se revocará el acceso de <strong>{{ pendingDelete?.name }}</strong>. Esta acción no elimina su historial de auditoría.</v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="deleteDialog = false">Cancelar</v-btn><v-btn color="error" :loading="saving" @click="remove">Eliminar</v-btn></v-card-actions></v-card>
    </v-dialog>

    <v-snackbar :model-value="feedback !== null" color="success" :timeout="4000" @update:model-value="feedback = null">{{ feedback }}</v-snackbar>
  </div>
</template>
