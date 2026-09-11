import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

export default createVuetify({
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: { mdi },
  },
  theme: {
    defaultTheme: localStorage.getItem('opsevidence.theme') ?? 'light',
    themes: {
      light: {
        dark: false,
        colors: {
          primary: '#2f6f4e',
          secondary: '#3f4a57',
          surface: '#ffffff',
          background: '#f7f8f9',
          error: '#b3261e',
          warning: '#9a6400',
          success: '#1a7f4b',
          info: '#1f6feb',
        },
      },
      dark: {
        dark: true,
        colors: {
          primary: '#6fbf94',
          secondary: '#a8b3c0',
          surface: '#16181d',
          background: '#0f1115',
          error: '#ff8a80',
          warning: '#ffcc80',
          success: '#7fd6a4',
          info: '#8ab4f8',
        },
      },
    },
  },
  defaults: {
    VCard: { variant: 'outlined' },
    VBtn: { variant: 'flat' },
  },
})
