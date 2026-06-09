import { defineStore } from 'pinia'
import { scheduleService } from '@/services/schedule.service'

export const useScheduleStore = defineStore('schedule', {
  state: () => ({
    items: [],
    selectedProject: null,
    filters: {
      project_id: null,
      sector_id: null,
      status: null,
      date_from: null,
      date_to: null,
      attendant_id: null,
    },
    loading: false,
    error: null,
  }),

  actions: {
    async selectProject(project) {
      this.selectedProject = project
      this.filters.project_id = project?.id ?? null
      if (project) {
        await this.fetchSchedule()
      } else {
        this.items = []
      }
    },

    async fetchSchedule() {
      if (!this.filters.project_id) {
        this.items = []
        return
      }
      this.loading = true
      this.error = null
      try {
        const params = Object.fromEntries(
          Object.entries(this.filters).filter(([, v]) => v !== null && v !== '')
        )
        const data = await scheduleService.getSchedule(params)
        this.items = data.data || []
      } catch (e) {
        this.error = e.response?.data?.message || e.message
        this.items = []
      } finally {
        this.loading = false
      }
    },

    async setFilter(key, value) {
      this.filters[key] = value
      await this.fetchSchedule()
    },

    async setFilters(updates) {
      Object.entries(updates).forEach(([k, v]) => {
        if (k !== 'project_id' && k in this.filters) this.filters[k] = v
      })
      await this.fetchSchedule()
    },

    clearFilters() {
      const projectId = this.filters.project_id
      this.filters = {
        project_id: projectId,
        sector_id: null,
        status: null,
        date_from: null,
        date_to: null,
        attendant_id: null,
      }
      this.fetchSchedule()
    },

    clearAll() {
      this.selectedProject = null
      this.filters = {
        project_id: null,
        sector_id: null,
        status: null,
        date_from: null,
        date_to: null,
        attendant_id: null,
      }
      this.items = []
    },
  },
})
