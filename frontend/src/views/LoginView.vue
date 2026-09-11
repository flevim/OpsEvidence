<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/api/http'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('owner@opsevidence.test')
const password = ref('password')
const error = ref<string | null>(null)

async function submit(): Promise<void> {
  error.value = null

  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    void router.push(redirect)
  } catch (exception) {
    error.value = errorMessage(exception)
  }
}
</script>

<template>
  <v-container class="fill-height" fluid>
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="5" lg="4">
        <div class="mb-6 text-center">
          <h1 class="text-h5 font-weight-bold">OpsEvidence</h1>
          <p class="text-body-2 text-medium-emphasis">
            Evidencia técnica de infraestructura, convertida en informes para tus clientes.
          </p>
        </div>

        <v-card class="pa-6">
          <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
            {{ error }}
          </v-alert>

          <v-form @submit.prevent="submit">
            <v-text-field
              v-model="email"
              label="Correo electrónico"
              type="email"
              autocomplete="username"
              prepend-inner-icon="mdi-email-outline"
              required
            />

            <v-text-field
              v-model="password"
              label="Contraseña"
              type="password"
              autocomplete="current-password"
              prepend-inner-icon="mdi-lock-outline"
              required
            />

            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              class="mt-2"
              :loading="auth.loading"
            >
              Iniciar sesión
            </v-btn>
          </v-form>
        </v-card>

        <p class="text-caption text-medium-emphasis text-center mt-4">
          Entorno de demostración: owner@opsevidence.test / password
        </p>
      </v-col>
    </v-row>
  </v-container>
</template>
