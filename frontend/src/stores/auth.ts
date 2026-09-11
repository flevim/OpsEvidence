import { defineStore } from 'pinia'
import { authApi } from '@/api'
import { TOKEN_KEY } from '@/api/http'
import type { User } from '@/types'

const ROLE_LEVEL: Record<string, number> = {
  viewer: 10,
  technician: 20,
  admin: 30,
  owner: 40,
}

interface State {
  token: string | null
  user: User | null
  loading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): State => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state): boolean => Boolean(state.token),
    accountName: (state): string => state.user?.account?.name ?? 'OpsEvidence',
    roleLevel: (state): number => (state.user ? (ROLE_LEVEL[state.user.role] ?? 0) : 0),
  },

  actions: {
    can(level: number): boolean {
      return this.roleLevel >= level
    },

    async login(email: string, password: string): Promise<void> {
      this.loading = true

      try {
        const data = await authApi.login(email, password)
        this.token = data.token
        this.user = data.user
        localStorage.setItem(TOKEN_KEY, data.token)
      } finally {
        this.loading = false
      }
    },

    async fetchMe(): Promise<void> {
      if (!this.token) return

      try {
        this.user = await authApi.me()
      } catch {
        this.clear()
      }
    },

    async logout(): Promise<void> {
      try {
        await authApi.logout()
      } catch {
        // El token pudo expirar ya: cerrar sesión local igualmente.
      }

      this.clear()
    },

    clear(): void {
      this.token = null
      this.user = null
      localStorage.removeItem(TOKEN_KEY)
    },
  },
})
