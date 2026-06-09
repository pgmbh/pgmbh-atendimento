<template>
  <v-card>
    <v-card-title v-if="projectName" class="text-h6 pa-4">
      Cronograma — {{ projectName }}
    </v-card-title>
    <v-progress-linear v-if="loading" indeterminate color="primary" />
    <div v-if="!loading && items.length === 0" class="pa-8 text-center text-grey">
      <v-icon size="48" color="grey-lighten-1">mdi-calendar-blank-outline</v-icon>
      <p class="mt-2">Nenhuma demanda encontrada para os filtros selecionados.</p>
    </div>
    <v-data-table
      v-else
      v-model:sort-by="sortBy"
      :headers="headers"
      :items="items"
      :loading="loading"
      item-value="id"
      hover
      @update:sort-by="$emit('update:sortBy', $event)"
    >
      <template #item.date_start="{ item }">{{ formatDate(item.date_start) }}</template>
      <template #item.date_conclusion="{ item }">
        <v-chip v-if="!item.date_conclusion && isInProgress(item.status)" size="small" color="orange">
          Em andamento
        </v-chip>
        <v-chip v-else-if="!item.date_conclusion" size="small" color="blue-grey">
          Não iniciado
        </v-chip>
        <span v-else>{{ formatDate(item.date_conclusion) }}</span>
      </template>
      <template #item.status="{ item }">
        <v-chip :color="statusColor(item.status)" size="small">{{ translateStatus(item.status) }}</v-chip>
      </template>
      <template #item.observation="{ item }">
        <v-tooltip :text="item.observation" location="top">
          <template #activator="{ props }">
            <span v-bind="props" class="observation-text">{{ truncate(item.observation, 60) }}</span>
          </template>
        </v-tooltip>
      </template>
      <template #item.priority="{ item }">
        <v-chip :color="priorityColor(item.priority)" size="x-small">{{ item.priority }}</v-chip>
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  items: { type: Array, default: () => [] },
  projectName: { type: String, default: '' },
  loading: { type: Boolean, default: false },
})
defineEmits(['update:sortBy'])

const sortBy = ref([])

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '60px' },
  { title: 'Atividade', key: 'title', sortable: true },
  { title: 'Início', key: 'date_start', sortable: true },
  { title: 'Conclusão', key: 'date_conclusion', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Observação', key: 'observation', sortable: false },
]

const STATUS_LABEL = {
  NOVO: 'Novo', NEW: 'Novo',
  ABERTO: 'Aberto', OPEN: 'Aberto',
  IN_PROGRESS: 'Em Andamento', 'EM ANDAMENTO': 'Em Andamento',
  RESOLVED: 'Resolvido',
  CONCLUDED: 'Concluído', CONCLUIDO: 'Concluído',
  CANCELADO: 'Cancelado',
}
const STATUS_COLOR = {
  NOVO: 'blue-grey', NEW: 'blue-grey',
  ABERTO: 'blue', OPEN: 'blue',
  IN_PROGRESS: 'orange', 'EM ANDAMENTO': 'orange',
  RESOLVED: 'teal',
  CONCLUDED: 'green', CONCLUIDO: 'green',
  CANCELADO: 'grey',
}
function translateStatus(s) { return STATUS_LABEL[s] ?? s }
function statusColor(s) { return STATUS_COLOR[s] ?? 'grey' }
function isInProgress(s) { return s === 'IN_PROGRESS' || s === 'EM ANDAMENTO' }
function priorityColor(p) {
  return { URGENTE: 'red', ALTA: 'orange', NORMAL: 'blue', BAIXA: 'grey' }[p] || 'grey'
}
function formatDate(d) {
  if (!d) return ''
  const [y, m, day] = d.split('-')
  return `${day}/${m}/${y}`
}
function truncate(text, max) {
  if (!text) return ''
  return text.length > max ? text.slice(0, max) + '…' : text
}
</script>

<style scoped>
.observation-text { cursor: help; }
.observation-cell { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
