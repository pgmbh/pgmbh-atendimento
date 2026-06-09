<template>
  <v-card class="mb-4">
    <v-card-text>
      <!-- Filtro principal: Projeto -->
      <v-row>
        <v-col cols="12">
          <v-autocomplete
            v-model="selectedProject"
            :items="projectStore.activeProjects"
            :item-title="p => `[${p.acronym}] ${p.name}`"
            item-value="id"
            label="Projeto *"
            placeholder="Selecione um projeto para carregar o cronograma"
            prepend-inner-icon="mdi-folder-outline"
            clearable
            return-object
            @update:model-value="onProjectChange"
          />
        </v-col>
      </v-row>

      <!-- Filtros avançados (apenas com projeto selecionado) -->
      <v-expansion-panels v-if="selectedProject" class="mt-2">
        <v-expansion-panel title="Filtros Avançados">
          <v-expansion-panel-text>
            <v-row>
              <v-col cols="12" sm="3">
                <v-select v-model="filters.status" :items="statusOptions" item-title="title" item-value="value" label="Status" clearable />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field v-model="filters.date_from" label="Data Início" type="date" />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field v-model="filters.date_to" label="Data Fim" type="date" />
              </v-col>
              <v-col cols="12" sm="3" class="d-flex align-center gap-2">
                <v-btn color="primary" @click="applyFilters">Aplicar</v-btn>
                <v-btn variant="outlined" class="ml-2" @click="clearFilters">Limpar</v-btn>
              </v-col>
            </v-row>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useProjectStore } from '@/stores/projectStore'

const emit = defineEmits(['project-selected', 'filters-changed'])
const projectStore = useProjectStore()

const selectedProject = ref(null)
const filters = ref({ status: null, date_from: null, date_to: null })
const statusOptions = [
  { title: 'Novo', value: 'NOVO' },
  { title: 'Aberto', value: 'OPEN' },
  { title: 'Em Andamento', value: 'IN_PROGRESS' },
  { title: 'Resolvido', value: 'RESOLVED' },
  { title: 'Retorno', value: 'RETORNO' },
  { title: 'Concluído', value: 'CONCLUDED' },
  { title: 'Cancelado', value: 'CANCELADO' },
]

function onProjectChange(project) {
  emit('project-selected', project || null)
}

function applyFilters() {
  emit('filters-changed', { ...filters.value })
}

function clearFilters() {
  filters.value = { status: null, date_from: null, date_to: null }
  emit('filters-changed', { ...filters.value })
}
</script>
