<template>
  <div class="dashboard">
    <AttendantHeader />
    <div class="dashboard-layout">
      <AttendantSidebar />
      <div class="dashboard-content" :style="{ marginLeft: sidebarCollapsed ? '60px' : '250px' }">
        <div class="config-page">
          <div class="d-flex justify-space-between align-center mb-4">
            <h2 class="text-h5 font-weight-medium">Prioridades</h2>
            <v-btn color="primary" @click="openNew" prepend-icon="mdi-plus">Nova Prioridade</v-btn>
          </div>

          <v-card class="mb-4">
            <v-card-text>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="search" label="Pesquisar por nome" prepend-inner-icon="mdi-magnify" clearable hide-details />
                </v-col>
                <v-col cols="12" sm="3">
                  <v-select v-model="filterActive" :items="activeOptions" item-title="text" item-value="value" label="Status" hide-details @update:model-value="load" />
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card>
            <v-data-table :headers="headers" :items="filteredItems" :loading="loading" :items-per-page="10" item-key="id">
              <template #item.label="{ item }">
                <v-chip size="small" :style="{ backgroundColor: item.color, color: textColor(item.color) }">{{ item.label }}</v-chip>
              </template>
              <template #item.active="{ item }">
                <v-chip :color="item.active ? 'success' : 'error'" size="small">
                  {{ item.active ? 'Ativa' : 'Inativa' }}
                </v-chip>
              </template>
              <template #item.actions="{ item }">
                <div class="action-buttons">
                  <v-tooltip text="Editar">
                    <template #activator="{ props }">
                      <v-btn icon size="small" color="primary" v-bind="props" @click="openEdit(item)" class="action-btn">
                        <v-icon>mdi-pencil-outline</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>
                  <v-tooltip :text="item.active ? 'Inativar' : 'Ativar'">
                    <template #activator="{ props }">
                      <v-btn icon size="small" :color="item.active ? 'warning' : 'success'" v-bind="props" @click="toggle(item)" class="action-btn">
                        <v-icon>{{ item.active ? 'mdi-toggle-switch' : 'mdi-toggle-switch-off-outline' }}</v-icon>
                      </v-btn>
                    </template>
                  </v-tooltip>
                </div>
              </template>
            </v-data-table>
          </v-card>
        </div>
      </div>
    </div>

    <v-dialog v-model="dialog.show" max-width="480px">
      <v-card>
        <v-card-title class="headline primary text-white">
          {{ dialog.isEdit ? 'Editar Prioridade' : 'Nova Prioridade' }}
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="form">
            <v-row>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.name"
                  label="Nome interno * (ex: ALTA)"
                  :rules="[v => !!v || 'Nome é obrigatório']"
                  required
                  hint="Identificador em maiúsculas, sem espaços"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.label"
                  label="Label de exibição * (ex: Alta)"
                  :rules="[v => !!v || 'Label é obrigatória']"
                  required
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="formData.weight"
                  label="Ordem (peso) *"
                  type="number"
                  min="0"
                  :rules="[v => v !== '' && v !== null || 'Peso é obrigatório']"
                  hint="0 = mais urgente"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <p class="text-body-2 text-medium-emphasis mb-1">Cor</p>
                <div class="d-flex align-center gap-3">
                  <input type="color" v-model="formData.color" class="color-picker" />
                  <v-chip size="small" :style="{ backgroundColor: formData.color, color: textColor(formData.color) }">{{ formData.label || 'Prévia' }}</v-chip>
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-divider />
        <v-card-actions>
          <v-spacer />
          <v-btn color="grey-darken-1" variant="text" @click="dialog.show = false">Cancelar</v-btn>
          <v-btn color="primary" @click="save" :loading="dialog.loading">
            {{ dialog.isEdit ? 'Atualizar' : 'Cadastrar' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="feedback.show" :color="feedback.color" :timeout="4000">
      {{ feedback.message }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSidebar } from '@/composables/useSidebar'
import AttendantHeader from '@/components/common/AttendantHeader.vue'
import AttendantSidebar from '@/components/common/AttendantSidebar.vue'
import { authService } from '@/services/auth.service'
import { configService } from '@/services/config.service'

const router = useRouter()
const { sidebarCollapsed } = useSidebar()

const loading = ref(false)
const items = ref([])
const search = ref('')
const filterActive = ref('true')
const form = ref(null)

const activeOptions = [
  { text: 'Ativas', value: 'true' },
  { text: 'Inativas', value: 'false' },
  { text: 'Todas', value: 'all' },
]

const headers = [
  { title: 'Nome interno', key: 'name' },
  { title: 'Label', key: 'label' },
  { title: 'Peso', key: 'weight' },
  { title: 'Status', key: 'active', sortable: false },
  { title: 'Ações', key: 'actions', sortable: false },
]

const dialog = ref({ show: false, isEdit: false, loading: false })
const formData = ref({ id: null, name: '', label: '', color: '#607d8b', weight: 99 })
const feedback = ref({ show: false, message: '', color: 'success' })

const filteredItems = computed(() =>
  items.value.filter(i =>
    !search.value ||
    i.name.toLowerCase().includes(search.value.toLowerCase()) ||
    i.label.toLowerCase().includes(search.value.toLowerCase())
  )
)

function checkAdminPermission() {
  const attendant = authService.getAttendantData()
  if (!attendant || attendant.function !== 'Admin') {
    router.push('/attendant/dashboard')
  }
}

async function load() {
  loading.value = true
  try {
    const res = await configService.list('priorities', { active: filterActive.value })
    items.value = res.data.data
  } catch {
    showFeedback('Erro ao carregar prioridades.', 'error')
  } finally {
    loading.value = false
  }
}

function openNew() {
  formData.value = { id: null, name: '', label: '', color: '#607d8b', weight: 99 }
  dialog.value = { show: true, isEdit: false, loading: false }
}

function openEdit(item) {
  formData.value = { id: item.id, name: item.name, label: item.label, color: item.color, weight: item.weight }
  dialog.value = { show: true, isEdit: true, loading: false }
}

async function save() {
  const valid = await form.value?.validate()
  if (!valid?.valid) return
  dialog.value.loading = true
  const payload = { name: formData.value.name, label: formData.value.label, color: formData.value.color, weight: Number(formData.value.weight) }
  try {
    if (dialog.value.isEdit) {
      await configService.update('priorities', formData.value.id, payload)
      showFeedback('Prioridade atualizada com sucesso.')
    } else {
      await configService.create('priorities', payload)
      showFeedback('Prioridade criada com sucesso.')
    }
    dialog.value.show = false
    await load()
  } catch (e) {
    showFeedback(e.response?.data?.message || 'Erro ao salvar.', 'error')
  } finally {
    dialog.value.loading = false
  }
}

async function toggle(item) {
  try {
    const res = await configService.toggleActive('priorities', item.id)
    showFeedback(res.data.message)
    await load()
  } catch {
    showFeedback('Erro ao alterar status.', 'error')
  }
}

function textColor(hex) {
  if (!hex || !hex.startsWith('#')) return '#000000'
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  return luminance > 0.5 ? '#000000' : '#ffffff'
}

function showFeedback(message, color = 'success') {
  feedback.value = { show: true, message, color }
}

onMounted(() => {
  checkAdminPermission()
  load()
})
</script>

<style scoped>
.dashboard { min-height: 100vh; background-color: #f3f4f6; }
.dashboard-layout { padding-top: 60px; min-height: calc(100vh - 60px); }
.dashboard-content { transition: margin-left 0.3s ease; padding: 24px; }
.config-page { padding: 24px; background-color: #f8f9fa; min-height: calc(100vh - 108px); }
.action-buttons { display: flex; gap: 8px; justify-content: center; align-items: center; }
.action-btn { transition: all 0.2s ease-in-out !important; }
.action-btn:hover { transform: translateY(-2px) scale(1.1); }
.color-picker { width: 48px; height: 36px; border: 1px solid #ccc; border-radius: 6px; cursor: pointer; padding: 2px; }
</style>
