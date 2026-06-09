<template>
  <div class="dashboard">
    <AttendantHeader />
    <div class="dashboard-layout">
      <AttendantSidebar />
      <div class="dashboard-content" :style="{ marginLeft: sidebarCollapsed ? '60px' : '250px' }">
        <div class="page-container">
          <div class="d-flex justify-space-between align-center mb-4">
            <h2 class="text-h5 font-weight-medium">Cronograma de Atividades</h2>
            <SchedulePdfExport
              :disabled="!scheduleStore.selectedProject"
              :items="sortedItemsForPdf"
              :project="scheduleStore.selectedProject"
            />
          </div>

          <ScheduleFilters @project-selected="onProjectSelected" @filters-changed="onFiltersChanged" />

          <div v-if="!scheduleStore.selectedProject" class="empty-state">
            <v-icon size="80" color="grey-lighten-1">mdi-calendar-blank-outline</v-icon>
            <p class="text-h6 text-grey mt-4">Selecione um projeto acima</p>
            <p class="text-body-2 text-grey">para visualizar o cronograma de atividades</p>
          </div>

          <template v-else>
            <v-alert v-if="scheduleStore.error" type="error" class="mb-4">{{ scheduleStore.error }}</v-alert>
            <ScheduleTable
              :items="scheduleStore.items"
              :project-name="`[${scheduleStore.selectedProject.acronym}] ${scheduleStore.selectedProject.name}`"
              :loading="scheduleStore.loading"
              @update:sort-by="tableSortBy = $event"
            />
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useScheduleStore } from '@/stores/scheduleStore'
import { useProjectStore } from '@/stores/projectStore'
import { useSidebar } from '@/composables/useSidebar'
import AttendantHeader from '@/components/common/AttendantHeader.vue'
import AttendantSidebar from '@/components/common/AttendantSidebar.vue'
import ScheduleFilters from '@/components/schedule/ScheduleFilters.vue'
import ScheduleTable from '@/components/schedule/ScheduleTable.vue'
import SchedulePdfExport from '@/components/schedule/SchedulePdfExport.vue'

const route = useRoute()
const scheduleStore = useScheduleStore()
const projectStore = useProjectStore()
const { sidebarCollapsed } = useSidebar()

const tableSortBy = ref([])

const sortedItemsForPdf = computed(() => {
  if (!tableSortBy.value.length) return scheduleStore.items
  return [...scheduleStore.items].sort((a, b) => {
    for (const { key, order } of tableSortBy.value) {
      const aVal = a[key] ?? ''
      const bVal = b[key] ?? ''
      const cmp = String(aVal).localeCompare(String(bVal), 'pt-BR', { numeric: true })
      if (cmp !== 0) return order === 'asc' ? cmp : -cmp
    }
    return 0
  })
})

function onProjectSelected(project) {
  scheduleStore.selectProject(project)
}

function onFiltersChanged(filters) {
  scheduleStore.setFilters(filters)
}

onMounted(async () => {
  await projectStore.fetchProjects()
  const projectId = route.query.project_id
  if (projectId) {
    const project = projectStore.projects.find(p => p.id === Number(projectId))
    if (project) scheduleStore.selectProject(project)
  }
})
</script>

<style scoped>
.page-container { padding: 24px; }
.dashboard { min-height: 100vh; background-color: #f3f4f6; }
.dashboard-layout { padding-top: 60px; }
.dashboard-content { transition: margin-left 0.3s ease; padding: 24px; }
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: white;
  border-radius: 8px;
  border: 1px dashed #ccc;
}
</style>
