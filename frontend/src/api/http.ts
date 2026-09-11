import axios, { type AxiosError } from 'axios'

export const TOKEN_KEY = 'opsevidence.token'

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '',
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)

      if (window.location.pathname !== '/login') {
        window.location.assign('/login')
      }
    }

    return Promise.reject(error)
  },
)

export function errorMessage(error: unknown): string {
  const axiosError = error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>

  if (axiosError.response?.status === 429) {
    return 'Demasiadas peticiones. Espera unos segundos e inténtalo de nuevo.'
  }

  const errors = axiosError.response?.data?.errors

  if (errors) {
    const first = Object.values(errors)[0]
    if (first && first.length > 0) return first[0]
  }

  return axiosError.response?.data?.message ?? 'Ocurrió un error inesperado.'
}
