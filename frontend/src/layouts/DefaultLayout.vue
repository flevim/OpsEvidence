<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTheme } from 'vuetify'
import { useAuthStore } from '@/stores/auth'

const drawer = ref(true)
const auth = useAuthStore()
const router = useRouter()
const theme = useTheme()

const items = computed(() => [
  { title: 'Panel', icon: 'mdi-view-dashboard-outline', to: '/' },
  { title: 'Clientes', icon: 'mdi-domain', to: '/clients' },
  { title: 'Problemas', icon: 'mdi-alert-outline', to: '/issues' },
  { title: 'Informes', icon: 'mdi-file-document-outline', to: '/reports' },
  ...(auth.user?.role === 'owner' || auth.user?.role === 'admin'
    ? [{ title: 'Usuarios', icon: 'mdi-account-group-outline', to: '/users' }]
    : []),
  { title: 'Ajustes', icon: 'mdi-cog-outline', to: '/settings' },
])

const isDark = computed(() => theme.global.name.value === 'dark')

function toggleTheme(): void {
  const next = isDark.value ? 'light' : 'dark'
  theme.global.name.value = next
  localStorage.setItem('opsevidence.theme', next)
}

async function signOut(): Promise<void> {
  await auth.logout()
  void router.push({ name: 'login' })
}
</script>

<template>
  <v-navigation-drawer v-model="drawer" :rail="false" width="248">
    <div class="pa-4">
      <div class="text-subtitle-1 font-weight-bold">OpsEvidence</div>
      <div class="text-caption text-medium-emphasis">{{ auth.accountName }}</div>
    </div>

    <v-divider />

    <v-list density="comfortable" nav>
      <v-list-item v-for="item in items" :key="item.to" :to="item.to" :prepend-icon="item.icon">
        <v-list-item-title>{{ item.title }}</v-list-item-title>
      </v-list-item>
    </v-list>
  </v-navigation-drawer>

  <v-app-bar flat border density="comfortable">
    <v-app-bar-nav-icon @click="drawer = !drawer" />

    <v-app-bar-title>Evidencia de infraestructura</v-app-bar-title>

    <v-spacer />

    <v-btn
      :icon="isDark ? 'mdi-weather-sunny' : 'mdi-weather-night'"
      variant="text"
      :aria-label="isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'"
      @click="toggleTheme"
    />

    <v-menu>
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="text-none">
          <v-icon start>mdi-account-circle-outline</v-icon>
          {{ auth.user?.name ?? 'Cuenta' }}
        </v-btn>
      </template>

      <v-list density="compact">
        <v-list-item :subtitle="auth.user?.email" :title="auth.user?.name" disabled />
        <v-divider />
        <v-list-item prepend-icon="mdi-logout" title="Cerrar sesión" @click="signOut" />
      </v-list>
    </v-menu>
  </v-app-bar>

  <v-main>
    <v-container fluid class="pa-6">
      <router-view />
    </v-container>
  </v-main>
</template>
