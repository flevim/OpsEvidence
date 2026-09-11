<script setup lang="ts">
import { computed } from 'vue'
import type { EvidenceStatus, Freshness } from '@/types'
import { STATUS_COLORS, STATUS_ICONS, STATUS_LABELS } from '@/types'

const props = defineProps<{
  status?: EvidenceStatus | null
  freshness?: Freshness | null
  label?: string
}>()

const effectiveStatus = computed<EvidenceStatus>(() => {
  if (props.freshness === 'never_collected') return 'UNKNOWN'
  return props.status ?? 'UNKNOWN'
})

const isUnknownBecauseNoData = computed(() => props.freshness === 'never_collected')

const text = computed(() => {
  if (isUnknownBecauseNoData.value) return 'Sin datos'
  return props.label ?? STATUS_LABELS[effectiveStatus.value]
})

const icon = computed(() => (isUnknownBecauseNoData.value ? 'mdi-database-off' : STATUS_ICONS[effectiveStatus.value]))
const color = computed(() => (isUnknownBecauseNoData.value ? 'grey' : STATUS_COLORS[effectiveStatus.value]))
</script>

<template>
  <v-chip :color="color" size="small" variant="tonal" label>
    <v-icon start size="14">{{ icon }}</v-icon>
    {{ text }}
    <v-tooltip v-if="freshness === 'stale'" activator="parent">
      Los datos están desactualizados: la última recolección fue hace más de lo esperado.
    </v-tooltip>
  </v-chip>
</template>
