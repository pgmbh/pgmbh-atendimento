<template>
  <div class="dashboard">
    <AttendantHeader />
    <div class="dashboard-layout">
      <AttendantSidebar />
      <div class="dashboard-content" :style="{ marginLeft: sidebarCollapsed ? '60px' : '250px' }">
        <div class="tickets-page">
          <div class="d-flex justify-space-between align-center mb-4">
            <h2 class="text-h5 font-weight-medium">Meus Atendimentos</h2>
            <v-btn color="primary" @click="openCreateDialog" class="btn-centered btn-centered-text">
              Criar Chamado para Usuário
            </v-btn>


          </div>

          <v-card class="mb-4">
            <v-card-text>
              <v-row>
                <!-- Campo de pesquisa por solicitante -->
                <v-col cols="12" sm="3">
                  <v-text-field v-model="searchRequester" label="Pesquisar por solicitante" outlined dense
                    @input="handleSearchInput"></v-text-field>
                </v-col>

                <!-- Filtro de Status -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchStatus" :items="statusOptions" label="Status" outlined dense
                    @change="handleSearch"></v-select>
                </v-col>

                <!-- Filtro de Prioridade -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchPriority" :items="priorityOptions" label="Prioridade" outlined dense
                    @change="handleSearch"></v-select>
                </v-col>
                <v-col cols="12" sm="3">
                  <v-select v-model="searchCategory" :items="categoryOptions" item-title="title" item-value="value"
                    label="Categoria" outlined dense @change="handleSearch"></v-select>
                </v-col>

                <v-col cols="12" sm="3">
                  <v-select v-model="searchServiceType" :items="serviceTypeOptions" item-title="title"
                    item-value="value" label="Tipo de Serviço" outlined dense @change="handleSearch"></v-select>
                </v-col>

                <!-- Filtro de Sistema -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchProject" :items="projectOptions" item-title="title"
                    item-value="value" label="Sistema" outlined dense @update:model-value="handleSearch"></v-select>
                </v-col>

                <!-- Filtro de Período -->
                <v-col cols="12" sm="3">
                  <div class="date-compact">
                    <div class="date-label mb-1">Período</div>
                    <div class="date-inputs">
                      <v-text-field v-model="startDate" type="date" density="compact" hide-details
                        variant="outlined" class="date-input" @update:model-value="handleSearch()"></v-text-field>

                      <v-text-field v-model="endDate" type="date" density="compact" hide-details
                        variant="outlined" class="date-input" @update:model-value="handleSearch()"></v-text-field>
                    </div>
                  </div>
                </v-col>
                <!-- Nova linha para botões -->
                <v-col cols="12" class="d-flex align-center mt-2">
                  <v-btn color="primary" @click="handleSearch" :loading="loading" class="me-2 btn-centered">
                    Pesquisar
                  </v-btn>

                  <v-btn variant="outlined" @click="resetFilters" :disabled="loading" class="me-2 btn-centered">
                    Limpar
                  </v-btn>

                  <v-btn
                    color="black"
                    :variant="showCompleted ? 'flat' : 'outlined'"
                    @click="toggleCompleted"
                    :disabled="loading"
                    class="btn-centered me-2"
                    :title="showCompleted ? 'Ocultar Concluídos' : 'Exibir Concluídos'"
                  >
                    <svg v-if="showCompleted" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="me-2">
                      <path d="M12 4.5C5.5 4.5 2 12 2 12s3.5 7.5 10 7.5S22 12 22 12s-3.5-7.5-10-7.5z" fill="currentColor"/>
                      <circle cx="12" cy="12" r="3" fill="white"/>
                      <line x1="2" y1="2" x2="22" y2="22" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="me-2">
                      <path d="M12 4.5C5.5 4.5 2 12 2 12s3.5 7.5 10 7.5S22 12 22 12s-3.5-7.5-10-7.5z" fill="currentColor"/>
                      <circle cx="12" cy="12" r="3" fill="white"/>
                    </svg>
                    {{ showCompleted ? 'Ocultar Concluídos' : 'Exibir Concluídos' }}
                  </v-btn>

                  <v-btn
                    color="red-darken-2"
                    :variant="showCancelled ? 'flat' : 'outlined'"
                    @click="toggleCancelled"
                    :disabled="loading"
                    class="btn-centered"
                    :title="showCancelled ? 'Ocultar Cancelados' : 'Exibir Cancelados'"
                  >
                    <v-icon size="16" class="me-2">
                      {{ showCancelled ? 'mdi-cancel' : 'mdi-cancel' }}
                    </v-icon>
                    {{ showCancelled ? 'Ocultar Cancelados' : 'Exibir Cancelados' }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
          <v-card class="tickets-table">
            <!-- Barra de carregamento -->
            <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4"></v-progress-linear>


            <!-- Tabela de tickets -->
            <v-table hover>
              <thead>
                <tr>
                  <th class="text-left sortable-th" @click="changeSort('title')">
                    Número
                    <v-icon size="x-small" :class="sortField === 'title' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'title' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-left">Solicitante</th>
                  <th class="text-left sortable-th" @click="changeSort('priority')">
                    Prioridade
                    <v-icon size="x-small" :class="sortField === 'priority' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'priority' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-left sortable-th" @click="changeSort('status')">
                    Status
                    <v-icon size="x-small" :class="sortField === 'status' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'status' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-left">Setor</th>
                  <th class="text-left">Sistema</th>
                  <th class="text-left sortable-th" @click="changeSort('created_at')">
                    Data Criação
                    <v-icon size="x-small" :class="sortField === 'created_at' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'created_at' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-left sortable-th" @click="changeSort('deadline')">
                    Prazo
                    <v-icon size="x-small" :class="sortField === 'deadline' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'deadline' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-left sortable-th" @click="changeSort('conclusion_date')">
                    Data Conclusão
                    <v-icon size="x-small" :class="sortField === 'conclusion_date' ? 'sort-icon-active' : 'sort-icon-idle'">
                      {{ sortField === 'conclusion_date' ? (sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down') : 'mdi-unfold-more-horizontal' }}
                    </v-icon>
                  </th>
                  <th class="text-center">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ticket in tickets" :key="ticket.id">
                  <td>#{{ ticket.id }}</td>
                  <td>{{ ticket.requester?.name }}</td> <!-- Nova célula -->
                  <td>
                    <v-chip :color="getPriorityColor(ticket.priority)"
                      :text-color="getPriorityTextColor(ticket.priority)" size="small" class="priority-chip">
                      {{ ticket.priority }}
                    </v-chip>
                  </td>
                  <td>
                    <v-chip :color="getStatusColor(ticket.status)" size="small">
                      {{ translateStatus(ticket.status) }}
                    </v-chip>
                  </td>
                  <td>{{ ticket.sector?.name }}</td>
                  <td>{{ ticket.project ? (ticket.project.acronym || ticket.project.name) : '-' }}</td>
                  <td>{{ formatDate(ticket.dates.created) }}</td>
                  <td :class="getDeadlineClass(ticket.dates.deadline)">{{ formatDate(ticket.dates.deadline) }}</td>
                  <td>{{ ticket.dates.concluded ? formatDate(ticket.dates.concluded) : '-' }}</td>
                  <td class="text-center">
                    <div class="action-buttons">
                      <v-tooltip text="Evoluir atendimento" location="top">
                        <template #activator="{ props }">
                          <v-btn v-bind="props" size="small" color="primary" icon="mdi-pencil"
                            @click="openEvolveDialog(ticket)"
                            :disabled="ticket.status === 'CONCLUDED' || ticket.status === 'RESOLVED'"></v-btn>
                        </template>
                      </v-tooltip>

                      <v-tooltip text="Transferir atendimento" location="top">
                        <template #activator="{ props }">
                          <v-btn v-bind="props" size="small" color="primary" icon="mdi-account-arrow-right"
                            @click="openTransferDialog(ticket)"
                            :disabled="ticket.status === 'CONCLUDED' || ticket.status === 'RESOLVED'"></v-btn>
                        </template>
                      </v-tooltip>

                      <v-tooltip text="Ver histórico" location="top">
                        <template #activator="{ props }">
                          <v-btn v-bind="props" size="small" color="info" icon="mdi-history"
                            @click="openHistoryDialog(ticket)"></v-btn>
                        </template>
                      </v-tooltip>
                    </div>
                  </td>
                </tr>
              </tbody>
            </v-table>

            <div class="pagination-wrapper">
              <div class="pagination-info">
                Mostrando {{ tickets.length }} de {{ meta.total }} registros
              </div>
              <div class="pagination-controls">
                <v-btn :disabled="currentPage === 1" @click="handlePageChange(currentPage - 1)" size="small"
                  variant="text" class="pagination-button">
                  Anterior
                </v-btn>

                <template v-for="page in visiblePages" :key="page">
                  <span v-if="page === '...'" class="pagination-ellipsis">…</span>
                  <v-btn v-else :color="currentPage === page ? 'primary' : ''"
                    :variant="currentPage === page ? 'flat' : 'text'" size="small"
                    @click="handlePageChange(page)" class="pagination-button">
                    {{ page }}
                  </v-btn>
                </template>

                <v-btn :disabled="currentPage === meta.last_page" @click="handlePageChange(currentPage + 1)"
                  size="small" variant="text" class="pagination-button">
                  Próximo
                </v-btn>
              </div>
            </div>



          </v-card>
        </div>

        <!-- Modal para Evolução do Ticket -->
        <v-dialog v-model="evolveDialog.show" max-width="800px">
          <v-card>
            <v-card-title class="headline">
              Evoluir Atendimento #{{ evolveDialog.ticket?.id }}
            </v-card-title>

            <v-card-text>




              <div class="ticket-details mb-6">
                <div class="ticket-header">
                  <h3 class="ticket-title">{{ evolveDialog.ticket?.title }}</h3>
                  <v-chip :color="getPriorityColor(evolveDialog.ticket?.priority)" size="small" class="priority-chip">
                    {{ evolveDialog.ticket?.priority }}
                  </v-chip>
                </div>

              </div>
              <div class="description-container">

                <div class="description-label">
                  <v-icon icon="mdi-text-box-outline" class="mr-2"></v-icon>
                  Descrição do Atendimento
                </div>
                <div class="description-content">
                  {{ evolveDialog.ticket?.description }}
                </div>
                <div class="ticket-metadata">

                  <div class="metadata-item">
                    <v-icon size="small" class="mr-1">mdi-calendar</v-icon>
                    <span class="metadata-label">Aberto em:</span>
                    {{ formatDate(evolveDialog.ticket?.dates?.created) }}
                  </div>
                  <div class="metadata-item">
                    <v-icon size="small" class="mr-1">mdi-account</v-icon>
                    <span class="metadata-label">Solicitante:</span>
                    {{ evolveDialog.ticket?.requester?.name }}
                  </div>
                </div>

              </div>


      
<!-- Dentro do v-card-text do evolveDialog -->
<div class="admin-fields mt-4 mb-4">
  <div class="section-title d-flex align-center mb-2">
    <v-icon icon="mdi-tune" class="mr-2"></v-icon>
    <span>Campos administrativos</span>
  </div>

  <v-row>
    <!-- BLOCO PARA ADMINISTRADORES -->
    <template v-if="isAdmin">
      <v-col cols="12" md="6">
        <v-select v-model="evolveDialog.category_id" :items="categories" item-title="name" item-value="id"
          label="Categoria" :disabled="evolveDialog.loading"></v-select>
      </v-col>

      <v-col cols="12" md="6">
        <v-select v-model="evolveDialog.service_type_id" :items="serviceTypes" item-title="name"
          item-value="id" label="Tipo de Atendimento" :disabled="evolveDialog.loading"></v-select>
      </v-col>


      <!-- CORREÇÃO: Mova o campo Prazo para DENTRO do v-if, em sua própria coluna -->
      <v-col cols="12" md="6">
        <v-menu v-model="evolveDialog.deadlineMenu" :close-on-content-click="false" min-width="auto">
          <template v-slot:activator="{ props }">
            <v-text-field v-model="evolveDialog.formattedDeadline" label="Prazo" prepend-inner-icon="mdi-calendar"
              readonly v-bind="props" variant="outlined" density="comfortable"
              hint="Se não informado, será definido automaticamente 5 dias a partir da criação"
              class="prazo-field-left-align"></v-text-field>
          </template>
          <v-date-picker v-model="evolveDialog.deadline" @update:model-value="evolveDialog.deadlineMenu = false" locale="pt-BR"></v-date-picker>
        </v-menu>
      </v-col>
    </template>

    <!-- BLOCO PARA ATENDENTES COMUNS (AGORA ESTÁ CORRETAMENTE LIGADO AO V-IF) -->
    <template v-else>
      <v-col cols="12" md="6">
        <div class="field-label">Categoria:</div>
        <div class="field-value">
          {{ getCategoryName(evolveDialog.ticket?.category?.id) || 'Não definida' }}
        </div>
      </v-col>

      <v-col cols="12" md="6">
        <div class="field-label">Tipo de Atendimento:</div>
        <div class="field-value">
          {{ getServiceTypeName(evolveDialog.ticket?.serviceType?.id) || 'Não definido' }}
        </div>
      </v-col>
    </template>
  </v-row>
</div>

    
              <!-- Formulário de nova evolução -->
              <div class="new-update-form mb-6">
                <v-select v-model="evolveDialog.newStatus" :items="availableStatuses" item-title="text"
                  item-value="value" label="Novo Status" required />

                <v-textarea v-model="evolveDialog.comment" label="Comentário" required rows="3"
                  class="mb-4"></v-textarea>


              </div>


              <!-- Exibição de anexos -->
              <div v-if="evolveDialog.ticket?.attachments && evolveDialog.ticket.attachments.length > 0"
                class="attachments-section mb-4 mt-4">
                <div class="section-title d-flex align-center mb-2">
                  <v-icon icon="mdi-paperclip" class="mr-2"></v-icon>
                  <span>Anexos ({{ evolveDialog.ticket.attachments.length }})</span>
                </div>
                <v-list class="attachment-list">
                  <v-list-item v-for="attachment in evolveDialog.ticket.attachments" :key="attachment.id"
                    class="attachment-item">
                    <template v-slot:prepend>
                      <v-icon icon="mdi-file-document-outline"></v-icon>
                    </template>
                    <v-list-item-title>{{ attachment.originalFilename }}</v-list-item-title>
                    <template v-slot:append>
                      <v-btn color="primary" variant="text" size="small" @click="downloadAttachment(attachment)"
                        :loading="downloading === attachment.id">
                        <v-icon start>mdi-download</v-icon>
                        Download
                      </v-btn>
                    </template>
                  </v-list-item>
                </v-list>
              </div>



              <!-- Linha do tempo -->
              <div class="timeline">
                <div v-for="(history, index) in evolveDialog.ticket?.histories" :key="index" class="timeline-item">
                  <div class="timeline-header d-flex align-center">
                    <v-avatar size="32" class="mr-3">
                      <v-img src="/assets/user-avatar.png"></v-img>
                    </v-avatar>
                    <div>
                      <span class="font-weight-medium">{{ history.responsible?.name }}</span>
                      <span class="text-caption ml-2 grey--text">
                        {{ formatDate(history.date) }}
                      </span>
                    </div>
                  </div>

                  <div class="timeline-content ml-11">
                    <div class="d-flex align-center gap-2 mb-2">
                      <v-chip size="small" :color="getStatusColor(history.status_prev)">
                        {{ translateStatus(history.status_prev) }}
                      </v-chip>
                      <span class="mx-2">→</span>
                      <v-chip size="small" :color="getStatusColor(history.status_post)">
                        {{ translateStatus(history.status_post) }}
                      </v-chip>
                    </div>
                    <p class="text-body-2 mb-0">{{ history.comment }}</p>
                  </div>
                </div>
              </div>
            </v-card-text>

            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="grey" text @click="evolveDialog.show = false">
                Cancelar
              </v-btn>
              <v-btn color="primary" @click="evolveTicket" :loading="evolveDialog.loading">
                Confirmar
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Modal para Transferência do Ticket -->
        <v-dialog v-model="transferDialog.show" max-width="500px">
          <v-card>
            <v-card-title>Transferir Atendimento</v-card-title>
            <v-card-text>
              <v-select v-model="transferDialog.selectedSectorId" :items="sectors" item-title="name"
                item-value="id" label="Setor de Destino" clearable></v-select>
              <v-autocomplete v-model="transferDialog.selectedAttendantIds" :items="attendantOptions"
                item-title="label" item-value="id" label="Atendente(s) de Destino" multiple chips closable-chips
                :loading="transferDialog.loadingAttendants"
                hint="Selecione um ou vários atendentes (de qualquer setor). Deixe em branco para transferir somente o setor."
                persistent-hint></v-autocomplete>
              <v-textarea v-model="transferDialog.comment" label="Motivo da Transferência" required
                rows="3" class="mt-3"></v-textarea>
            </v-card-text>
            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn color="grey" text @click="transferDialog.show = false">
                Cancelar
              </v-btn>
              <v-btn color="primary" @click="transferTicket" :loading="transferDialog.loading">
                Confirmar
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Modal de Detalhes do Ticket (igual ao do usuário) -->
        <TicketDetailsModal v-model="showHistoryModal" :ticket="selectedTicket" @ticket-reopened="handleTicketReopened" />

        <AdminCreateTicket v-model="createDialog" @created="handleTicketCreated" />




      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { useSidebar } from '@/composables/useSidebar'
import AttendantHeader from '@/components/common/AttendantHeader.vue'
import AttendantSidebar from '@/components/common/AttendantSidebar.vue'
import AdminCreateTicket from '@/components/common/AdminCreateTicket.vue' // Importe o novo componente
import TicketDetailsModal from '@/components/tickets/TicketDetailsModal.vue'
import api from '@/services/api'
import { authService } from '@/services/auth.service'
import { mdiPencilBoxOutline } from "@mdi/js";

const { sidebarCollapsed } = useSidebar()
const loading = ref(false)
const tickets = ref([])

// Dentro do script setup
const loadCategoriesview = async () => {
  try {
    const response = await api.get('/categories');
    if (response.data.success) {
      console.log('Categorias carregadas =========>>>>>:', categories.value);
      categories.value = response.data.data;
    }
  } catch (error) {
    console.error('Erro ao carregar categorias:', error);
  }
};


const loadServiceTypes = async () => {
  try {
    const response = await api.get('/service-types');
    if (response.data.success) {
      serviceTypes.value = response.data.data;
    }
  } catch (error) {
    console.error('Erro ao carregar tipos de serviço:', error);
  }
};

const loadProjects = async () => {
  try {
    const response = await api.get('/project');
    if (response.data.success) {
      projects.value = response.data.data;
    }
  } catch (error) {
    console.error('Erro ao carregar sistemas:', error);
  }
};


// Funções auxiliares para obter nomes baseados nos IDs
const getCategoryName = (categoryId) => {
  if (!categoryId) return null;
  const category = categories.value.find(c => c.id === categoryId);
  return category ? category.name : null;
};

const getServiceTypeName = (serviceTypeId) => {
  if (!serviceTypeId) return null;
  const serviceType = serviceTypes.value.find(st => st.id === serviceTypeId);
  return serviceType ? serviceType.name : null;
};



// Função principal de pesquisa
// Adicione um debounce para o campo de pesquisa por solicitante
let searchTimeout;
const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    handleSearch();
  }, 500); // Aguarda 500ms após o usuário parar de digitar
};

const handleSearch = async () => {
  try {
    // Reseta para a primeira página antes de pesquisar
    currentPage.value = 1;

    // Inicia o carregamento
    loading.value = true;

    // Carrega os tickets com os filtros atuais
    await loadTickets(1);

  } catch (error) {
    console.error('Erro ao pesquisar tickets:', error);
  } finally {
    loading.value = false;
  }
};

const createDialog = ref(false)
const attendantData = ref(null)
const categories = ref([]);
const serviceTypes = ref([]);

const isAdmin = computed(() => {
  return authService.isAdmin()
})

const resetFilters = () => {
  // Limpa todos os campos de filtro
  searchTitle.value = '';
  searchRequester.value = '';
  searchStatus.value = '';
  searchPriority.value = '';
  searchCategory.value = '';
  searchServiceType.value = '';
  searchProject.value = '';
  startDate.value = null;
  endDate.value = null;
  showCompleted.value = false;
  showCancelled.value = false;

  // Recarrega os dados sem filtros
  currentPage.value = 1;
  loadTickets(1);
};

const toggleCompleted = () => {
  showCompleted.value = !showCompleted.value;
  currentPage.value = 1;
  loadTickets(1);
};

const toggleCancelled = () => {
  showCancelled.value = !showCancelled.value;
  currentPage.value = 1;
  loadTickets(1);
};

const searchTitle = ref('');
const searchRequester = ref('');
const searchStatus = ref('');
const searchPriority = ref('');
const searchCategory = ref('');
const searchServiceType = ref('');
const searchProject = ref('');
const projects = ref([]);
const showCompleted = ref(false);
const showCancelled = ref(false);

// Valores das datas
const startDate = ref(null);
const endDate = ref(null);

const handleTicketCreated = (newTicket) => {
  // Adicionar o novo ticket à lista ou recarregar os dados
  loadTickets()
  // Exibir mensagem de sucesso
  alert('Atendimento criado com sucesso!')
}

// Estado para os diálogos
const evolveDialog = ref({
  show: false,
  ticket: {
    attachments: [] // Inicialização explícita
  }, 
  newStatus: '',
  comment: '',
  category_id: null,
  service_type_id: null,
  deadline: null,
  deadlineMenu: false,
  formattedDeadline: '',
  loading: false
})

const openCreateDialog = () => {
  createDialog.value = true
}


const transferDialog = ref({
  show: false,
  ticket: null,
  selectedSectorId: null,
  selectedAttendantIds: [],
  comment: '',
  loading: false,
  loadingAttendants: false
})

const allAttendants = ref([])

// Lista de atendentes para o select de transferência, com o setor no rótulo
const attendantOptions = computed(() =>
  allAttendants.value.map((a) => ({
    id: a.id,
    label: a.sector?.name ? `${a.name} (${a.sector.name})` : a.name
  }))
)

const showHistoryModal = ref(false)
const selectedTicket = ref(null)

// Depois (em português com mapeamento)
const availableStatuses = [
  { text: 'ABERTO', value: 'OPEN' },
  { text: 'EM ANDAMENTO', value: 'IN_PROGRESS' },
  { text: 'RESOLVIDO', value: 'RESOLVED' },
  { text: 'CANCELADO', value: 'CANCELADO' },
  { text: 'RETORNO', value: 'RETORNO' },
  { text: 'CONCLUÍDO', value: 'CONCLUDED' }
]

// Adicione estes computed properties para formatar as opções dos selects
const categoryOptions = computed(() => {
  // Adicionar opção vazia no início
  return [
    { title: 'Todas as categorias', value: '' },
    ...categories.value.map(category => ({
      title: category.name,
      value: category.id
    }))
  ];
});


const serviceTypeOptions = computed(() => {
  // Adicionar opção vazia no início
  return [
    { title: 'Todos os tipos de serviço', value: '' },
    ...serviceTypes.value.map(type => ({
      title: type.name,
      value: type.id
    }))
  ];
});

const projectOptions = computed(() => {
  return [
    { title: 'Todos os sistemas', value: '' },
    ...projects.value.map(p => ({
      title: p.acronym ? `${p.acronym} - ${p.name}` : p.name,
      value: p.id
    }))
  ];
});

const currentPage = ref(1);
const sortField = ref('created_at');
const sortOrder = ref('desc');

const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 5,
  total: 0
});

const visiblePages = computed(() => {
  const total = meta.value.last_page;
  const current = currentPage.value;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
  const pages = [1];
  if (current > 3) pages.push('...');
  for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
    pages.push(i);
  }
  if (current < total - 2) pages.push('...');
  pages.push(total);
  return pages;
});

const changeSort = (field) => {
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortField.value = field;
    sortOrder.value = 'asc';
  }
  currentPage.value = 1;
  loadTickets(1);
};


const sectors = ref([])

// Ordena tickets por prioridade
const priorityOrder = {
  'URGENTE': 0,
  'ALTA': 1,
  'NORMAL': 2,
  'BAIXA': 3
}

const sortedTickets = computed(() => {
  return [...tickets.value].sort((a, b) => {
    return priorityOrder[a.priority] - priorityOrder[b.priority]
  })
})

// Funções auxiliares
const getPriorityColor = (priority) => {
  if (!priority) return 'grey'
  const colors = {
    'URGENTE': 'red',
    'ALTA': 'orange',
    'NORMAL': 'blue',
    'BAIXA': 'green'
  }
  return colors[priority] || 'grey'
}

const getPriorityTextColor = (priority) => {
  return ['URGENTE', 'ALTA'].includes(priority) ? 'white' : 'white'
}

const getStatusColor = (status) => {
  if (!status) return 'grey'
  const colors = {
    'NEW': 'grey',
    'OPEN': 'blue',
    'IN_PROGRESS': 'orange',
    'RESOLVED': 'green',
    'CANCELADO': 'red',
    'RETORNO': 'cyan',
    'CONCLUDED': 'purple'
  }
  return colors[status] || 'grey'
}

const translateStatus = (status) => {
  if (!status) return '-'
  const translations = {
    'new': 'NOVO',
    'NEW': 'NOVO',
    'NOVO': 'NOVO',
    'OPEN': 'ABERTO',
    'IN_PROGRESS': 'EM ANDAMENTO',
    'RESOLVED': 'RESOLVIDO',
    'CANCELADO': 'CANCELADO',
    'RETORNO': 'RETORNO',
    'CONCLUDED': 'CONCLUÍDO'
  }
  return translations[status] || status
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return format(new Date(dateString), "dd/MM/yyyy 'às' HH:mm", {
    locale: ptBR
  })
}

const formatDeadlineForDisplay = (deadline) => {
  if (!deadline) return '';
  try {
    if (deadline instanceof Date) {
      return format(deadline, 'dd/MM/yyyy', { locale: ptBR });
    }
    if (typeof deadline === 'string') {
      return format(new Date(deadline), 'dd/MM/yyyy', { locale: ptBR });
    }
    return '';
  } catch (error) {
    console.error('Erro ao formatar data do prazo:', error);
    return '';
  }
}

const getDeadlineClass = (deadline) => {
  if (!deadline) return '';
  
  try {
    const deadlineDate = new Date(deadline);
    const today = new Date();
    
    // Remove o tempo para comparar apenas as datas
    deadlineDate.setHours(23, 59, 59, 999);
    today.setHours(0, 0, 0, 0);
    
    if (deadlineDate < today) {
      return 'deadline-overdue';
    }
    
    return '';
  } catch (error) {
    console.error('Erro ao verificar prazo:', error);
    return '';
  }
}


const loadServiceHistory = async (serviceId) => {
  try {
    console.log('Carregando histórico para o ticket:', serviceId);

    // Carrega o histórico
    const historyResponse = await api.get(`/service/${serviceId}/history`);

    if (historyResponse.data.success) {
      evolveDialog.value.ticket.histories = historyResponse.data.data;
      console.log('Histórico carregado com sucesso:', historyResponse.data.data);
    } else {
      console.warn('Resposta de histórico sem sucesso:', historyResponse.data);
    }

    // Carrega os detalhes completos do ticket para ter acesso aos anexos
    console.log('Carregando detalhes para o ticket:', serviceId);
    const detailsResponse = await api.get(`/service/${serviceId}`);


    if (detailsResponse.data.success) {
      // Mantém o histórico já carregado, mas atualiza o resto das informações
      evolveDialog.value.ticket = {
        ...detailsResponse.data.data,
        histories: evolveDialog.value.ticket.histories || []
      };
      console.log('Detalhes carregados com sucesso:', detailsResponse.data.data);
    } else {
      console.warn('Resposta de detalhes sem sucesso:', detailsResponse.data);
    }
  } catch (error) {
    console.error('Erro ao carregar dados do ticket:', error);
    // Não deixe o erro parar a execução - mantenha o que já temos
  }
};




const openEvolveDialog = async (ticket) => {
  try {
    // Inicializa o diálogo com os dados básicos do ticket
    evolveDialog.value = {
      show: true,
      ticket: {
        ...ticket,
        attachments: ticket.attachments || [],
        histories: []
      },
      newStatus: ticket.status === 'NOVO' ? 'OPEN' : ticket.status,
      comment: '',
      // Inicializar com os valores existentes do ticket
      category_id: ticket.category?.id || null,
      service_type_id: ticket.serviceType?.id || null,
      loading: true
    };

    // Carrega detalhes completos do ticket
    await loadServiceHistory(ticket.id);

    const response = await api.get(`/service/${ticket.id}`);

    if (response.data.success) {
      // Importante: preservar os valores de categoria e tipo de atendimento
      const categoryId = response.data.data.category?.id || ticket.category?.id;
      const serviceTypeId = response.data.data.serviceType?.id || ticket.serviceType?.id;

      evolveDialog.value.ticket = {
        ...response.data.data,
        histories: evolveDialog.value.ticket.histories || [],
        attachments: response.data.data.attachments || []
      };

      // Atualizar os valores nos campos do formulário
      evolveDialog.value.category_id = categoryId;
      evolveDialog.value.service_type_id = serviceTypeId;
      
      // Configurar deadline
      if (response.data.data.dates?.deadline) {
        evolveDialog.value.deadline = new Date(response.data.data.dates.deadline);
        evolveDialog.value.formattedDeadline = formatDeadlineForDisplay(evolveDialog.value.deadline);
      } else {
        evolveDialog.value.deadline = null;
        evolveDialog.value.formattedDeadline = '';
      }
    }
  } catch (error) {
    console.error('Erro ao carregar detalhes do ticket:', error);
  } finally {
    evolveDialog.value.loading = false;
  }
};




const openTransferDialog = (ticket) => {
  transferDialog.value = {
    show: true,
    ticket,
    selectedSectorId: null,
    selectedAttendantIds: [],
    comment: '',
    loading: false,
    loadingAttendants: false
  }
  if (allAttendants.value.length === 0) {
    loadAttendants()
  }
}

const loadAttendants = async () => {
  transferDialog.value.loadingAttendants = true
  try {
    const response = await api.get('/attendants')
    if (response.data.success) {
      allAttendants.value = response.data.data
    }
  } catch (error) {
    console.error('Erro ao carregar atendentes:', error)
  } finally {
    transferDialog.value.loadingAttendants = false
  }
}

const openHistoryDialog = async (ticket) => {
  try {
    selectedTicket.value = ticket
    showHistoryModal.value = true
    
    // Carrega o histórico do ticket
    const response = await api.get(`/service/${ticket.id}/history`);
    
    if (response.data.success) {
      selectedTicket.value = {
        ...ticket,
        histories: response.data.data
      };
    }
  } catch (error) {
    console.error('Erro ao carregar histórico do ticket:', error);
  }
}

const evolveTicket = async () => {
  evolveDialog.value.loading = true;
  try {
    const ticketId = evolveDialog.value.ticket.id;

    // 1. Prepara os dados para a atualização principal (status, comentário, etc.)
    const mainUpdateData = {
      status: evolveDialog.value.newStatus,
      comment: evolveDialog.value.comment,
    };

    if (isAdmin.value) {
      if (evolveDialog.value.category_id) {
        mainUpdateData.category_id = evolveDialog.value.category_id;
      }
      if (evolveDialog.value.service_type_id) {
        mainUpdateData.service_type_id = evolveDialog.value.service_type_id;
      }
    }

    // 2. Faz a chamada para atualizar o status e outros campos
    console.log(`[PASSO 1] Atualizando status... PUT /api/service/${ticketId}/status`, mainUpdateData);
    await api.put(`/service/${ticketId}/status`, mainUpdateData);
    console.log('[PASSO 1] Status atualizado com sucesso!');


    // 3. Verifica se o prazo (deadline) foi alterado
    if (evolveDialog.value.deadline) {
      let formattedDeadline = evolveDialog.value.deadline;
      if (evolveDialog.value.deadline instanceof Date) {
        formattedDeadline = evolveDialog.value.deadline.toISOString().split('T')[0];
      }
      
      const deadlineData = { deadline: formattedDeadline };

      // 4. Faz a chamada SEPARADA para o endpoint específico de deadline
      console.log(`[PASSO 2] Atualizando o prazo... PUT /api/service/${ticketId}/deadline`, deadlineData);
      await api.put(`/service/${ticketId}/deadline`, deadlineData);
      console.log('[PASSO 2] Prazo atualizado com sucesso!');
    }

    await loadTickets();
    evolveDialog.value.show = false;

  } catch (error) {
    console.error('Erro ao evoluir ticket:', error);
    let errorMessage = 'Falha ao atualizar o atendimento.';
    if (error.response) {
      // Tenta pegar a mensagem de erro específica da API
      errorMessage = `Erro ${error.response.status}: ${error.response.data?.message || 'Ocorreu um erro no servidor.'}`;
      console.error('Detalhes do erro da API:', error.response.data);
    }
    alert(errorMessage);
  } finally {
    evolveDialog.value.loading = false;
  }
};

const transferTicket = async () => {
  const { selectedSectorId, selectedAttendantIds, comment } = transferDialog.value

  if (!selectedSectorId && (!selectedAttendantIds || selectedAttendantIds.length === 0)) {
    alert('Selecione um setor de destino e/ou um ou mais atendentes.')
    return
  }
  if (!comment) {
    alert('Informe o motivo da transferência.')
    return
  }

  transferDialog.value.loading = true
  try {
    const ticketId = transferDialog.value.ticket.id

    // Transfere o setor, se algum tiver sido escolhido
    if (selectedSectorId) {
      await api.put(`/service/${ticketId}/transfer`, {
        sector_id: selectedSectorId,
        comment
      })
    }

    // Atribui o atendimento a um ou vários atendentes (de qualquer setor)
    if (selectedAttendantIds && selectedAttendantIds.length > 0) {
      await api.put(`/service/${ticketId}/assign`, {
        attendant_ids: selectedAttendantIds
      })
    }

    await loadTickets()
    transferDialog.value.show = false
  } catch (error) {
    console.error('Erro ao transferir ticket:', error)
    alert('Falha ao transferir o atendimento.')
  } finally {
    transferDialog.value.loading = false
  }
}


const handlePageChange = async (page) => {
  currentPage.value = page;
  await loadTickets(page);
};


const loadTickets = async (page = 1) => {
  loading.value = true;
  try {
    // Verificação de autenticação
    if (!authService.isAttendant()) {
      console.log('Usuário não autenticado');
      router.push('/login');
      return;
    }

    // Obter dados do atendente
    const attendant = authService.getAttendantData();
    if (!attendant || !attendant.id) {
      console.error('Dados do atendente inválidos');
      return;
    }

    // Criar objeto URLSearchParams para construir a query string
    const params = new URLSearchParams();
    params.append('page', page.toString());
    params.append('per_page', '10');
    params.append('sort', sortField.value);
    params.append('order', sortOrder.value);

    // Adicionar filtros somente se existirem valores
    if (searchTitle.value) {
      params.append('title', searchTitle.value);
    }
    if (searchRequester.value) {
      params.append('requester', searchRequester.value);
    }
    if (searchStatus.value) {
      params.append('status', searchStatus.value);
    }
    if (searchPriority.value) {
      params.append('priority', searchPriority.value);
    }
    if (searchCategory.value) {
      params.append('category_id', searchCategory.value);
    }
    if (searchServiceType.value) {
      params.append('service_type_id', searchServiceType.value);
    }
    if (searchProject.value) {
      params.append('project_id', searchProject.value);
    }

    // Adicionar filtros de data
    if (startDate.value) {
      let formattedStartDate = startDate.value;
      if (startDate.value instanceof Date) {
        formattedStartDate = startDate.value.toISOString().split('T')[0];
      }
      params.append('start_date', formattedStartDate);
    }
    
    if (endDate.value) {
      let formattedEndDate = endDate.value;
      if (endDate.value instanceof Date) {
        formattedEndDate = endDate.value.toISOString().split('T')[0];
      }
      params.append('end_date', formattedEndDate);
    }

    // Montar lista de status excluídos
    const excludedStatuses = [];
    if (!showCompleted.value) excludedStatuses.push('CONCLUDED');
    if (!showCancelled.value) excludedStatuses.push('CANCELADO');
    if (excludedStatuses.length > 0) {
      params.append('exclude_status', excludedStatuses.join(','));
    }

    // Log para debug da URL construída
    console.log('URL da requisição:', `/service/attendant/${attendant.id}?${params.toString()}`);

    // Fazer a requisição
    const response = await api.get(`/service/attendant/${attendant.id}?${params.toString()}`);

    // Log da resposta para debug
    console.log('Dados recebidos:', response.data);

    // Processar a resposta
    if (response.data.success) {
      tickets.value = response.data.data;
      meta.value = response.data.meta;
    } else {
      console.error('Resposta sem sucesso:', response.data);
      tickets.value = [];
    }

  } catch (error) {
    // Tratamento de erros mais detalhado
    console.error('Erro ao carregar tickets:', error);
    if (error.response) {
      console.error('Detalhes do erro:', error.response.data);
    }
    tickets.value = [];

    // Tratamento de erro de autenticação
    if (error.response?.status === 401) {
      authService.logout();
      router.push('/login');
    }
  } finally {
    loading.value = false;
  }
};







const loadSectors = async () => {
  try {
    const response = await api.get('/sectors')
    if (response.data.success) {
      sectors.value = response.data.data
    }
  } catch (error) {
    console.error('Erro ao carregar setores:', error)
  }
}

const handleTicketReopened = () => {
  // Recarrega a lista de tickets para refletir a mudança
  loadTickets(currentPage.value);
}


// Replace the current statusOptions with:
const statusOptions = [
  { title: 'Todos os status', value: '' },
  { title: 'Novo', value: 'NEW' },
  { title: 'Aberto', value: 'OPEN' },
  { title: 'Em Andamento', value: 'IN_PROGRESS' },
  { title: 'Resolvido', value: 'RESOLVED' },
  { title: 'Cancelado', value: 'CANCELADO' },
  { title: 'Retorno', value: 'RETORNO' },
  { title: 'Concluído', value: 'CONCLUDED' }
]

// Replace the current priorityOptions with:
const priorityOptions = [
  { title: 'Todas as prioridades', value: '' },
  { title: 'Baixa', value: 'BAIXA' },
  { title: 'Normal', value: 'NORMAL' },
  { title: 'Alta', value: 'ALTA' },
  { title: 'Urgente', value: 'URGENTE' }
]



// Variável de controle para download
// No script setup do componente
// Adicione um novo ref para controlar o download
const downloading = ref(null);

// Adicione a função para download
const downloadAttachment = async (attachment) => {
  try {
    downloading.value = attachment.id;

    // Fazer requisição para baixar o anexo
    const response = await api.get(`/service/attachment/${attachment.id}`, {
      responseType: 'blob'
    });

    console.log('DOnwload====>>>');

    // Criar URL de objeto para o blob
    const url = window.URL.createObjectURL(response.data);

    // Criar elemento de link para download
    const link = document.createElement('a');
    link.href = url;
    link.download = attachment.originalFilename;

    // Adicionar à página, clicar e remover
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    // Liberar o objeto URL
    window.URL.revokeObjectURL(url);

  } catch (error) {
    console.error('Erro ao baixar anexo:', error);
    // Exibir mensagem de erro se necessário
  } finally {
    downloading.value = null;
  }
};

const loadCategoriesAndServiceTypes = async () => {
  try {
    // Carrega categorias
    const categoryResponse = await api.get('/categories');
    if (categoryResponse.data.success) {
      categories.value = categoryResponse.data.data;
      console.log('Categorias carregadas com sucesso:', categories.value);
    } else {
      console.warn('Falha ao carregar categorias:', categoryResponse.data);
    }

    // Carrega tipos de serviço
    const serviceTypeResponse = await api.get('/service-types');
    if (serviceTypeResponse.data.success) {
      serviceTypes.value = serviceTypeResponse.data.data;
      console.log('Tipos de serviço carregados com sucesso:', serviceTypes.value);
    } else {
      console.warn('Falha ao carregar tipos de serviço:', serviceTypeResponse.data);
    }
  } catch (error) {
    console.error('Erro ao carregar dados de filtro:', error);
  }
};

// Watcher para atualizar formatação do deadline
watch(() => evolveDialog.value.deadline, (newDeadline) => {
  evolveDialog.value.formattedDeadline = formatDeadlineForDisplay(newDeadline);
});

// Carrega dados iniciais
onMounted(() => {
  attendantData.value = authService.getAttendantData()
  loadTickets();
  loadSectors();
  loadCategoriesview();
  loadServiceTypes();
  loadProjects();
  loadCategoriesAndServiceTypes(); // Nova função combinada
})
</script>

<style scoped>
.dashboard {
  min-height: 100vh;
  background-color: #f3f4f6;
}

.ticket-details {
  background-color: #f8f9fa;
  border-radius: 8px;
  overflow: hidden;
}


.ticket-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background-color: #fff;
  border-bottom: 1px solid #e9ecef;
}


.ticket-title {
  font-size: 1.1rem;
  font-weight: 500;
  color: #1a237e;
  margin: 0;
}

.priority-chip {
  font-weight: 500;
}


.admin-fields {
  background-color: #f5f5f5;
  border-radius: 8px;
  padding: 16px;
  margin-top: 20px;
  border: 1px dashed #1a237e;
}

.section-title {
  font-weight: 500;
  color: #1a237e;
  margin-bottom: 12px;
}

.description-container {
  padding: 20px;
  background-color: #ffffff;
  border-radius: 0 0 8px 8px;
}

.description-label {
  display: flex;
  align-items: center;
  font-size: 0.95rem;
  font-weight: 500;
  color: #495057;
  margin-bottom: 12px;
}

.description-content {
  font-size: 0.95rem;
  line-height: 1.6;
  color: #212529;
  padding: 16px;
  background-color: #f8f9fa;
  border-radius: 6px;
  border: 1px solid #e9ecef;
  white-space: pre-wrap;
  margin-bottom: 16px;
}


.field-label {
  font-weight: 500;
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.875rem;
}

.field-value {
  background-color: #f5f5f5;
  padding: 12px;
  border-radius: 4px;
  margin-top: 4px;
  font-size: 0.9rem;
  color: rgba(0, 0, 0, 0.87);
  border: 1px solid rgba(0, 0, 0, 0.12);
  min-height: 48px;
  display: flex;
  align-items: center;
}

.ticket-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding-top: 12px;
  border-top: 1px solid #e9ecef;
}

.metadata-item {
  display: flex;
  align-items: center;
  font-size: 0.85rem;
  color: #6c757d;
}

.metadata-label {
  font-weight: 500;
  margin-right: 4px;
  margin-left: 4px;
}

/* Efeito hover suave */
.description-container:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  transition: box-shadow 0.3s ease;
}

/* Estilo para texto selecionado */
.description-content::selection {
  background-color: #e3f2fd;
  color: #1a237e;
}

.dashboard-layout {
  padding-top: 60px;
  min-height: calc(100vh - 60px);
}

.dashboard-content {
  transition: margin-left 0.3s ease;
  padding: 24px;
}

.tickets-page {
  padding: 24px;
  background-color: #f8f9fa;
  min-height: calc(100vh - 60px);
}

.tickets-table {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

:deep(.v-table) {
  background: transparent;
}

:deep(.v-table th) {
  font-size: 0.875rem;
  color: #666;
  font-weight: 500;
  text-transform: uppercase;
  background-color: #f5f5f5;
}

:deep(.v-table td) {
  font-size: 0.875rem;
  color: #333;
}


.pagination-wrapper {
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(0, 0, 0, 0.12);
}

.pagination-info {
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.875rem;
}

.pagination-controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.pagination-button {
  min-width: 40px !important;
  height: 40px !important;
  border-radius: 20px !important;
}

.pagination-button.v-btn--variant-flat {
  background-color: #1a237e !important;
  color: #ffffff !important;
}

.timeline {
  position: relative;
  padding-top: 16px;
  border-top: 1px solid #e0e0e0;
}

.timeline-item {
  position: relative;
  padding-bottom: 24px;
}

.timeline-item::before {
  content: '';
  position: absolute;
  left: 16px;
  top: 40px;
  bottom: 0;
  width: 2px;
  background: #e0e0e0;
}

.timeline-item:last-child::before {
  display: none;
}

.timeline-content {
  background: #f8f9fa;
  padding: 12px;
  border-radius: 16px;
  margin-top: 8px;
}

/* Estilo para o hover nos itens da timeline */
.timeline-item:hover {
  background: rgba(0, 0, 0, 0.02);
}

/* Estilo para o conteúdo quando houver hover */
.timeline-item:hover .timeline-content {
  background: #f0f2f5;
}


.attachments-section {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 16px;
  margin-top: 20px;
}

.section-title {
  color: #1a237e;
  border-bottom: 1px solid #e0e0e0;
  padding-bottom: 8px;
}

.attachment-list {
  background-color: transparent !important;
  padding: 0;
}

.attachment-item {
  border-radius: 4px;
  transition: background-color 0.2s ease;
}

.attachment-item:hover {
  background-color: rgba(0, 0, 0, 0.03);
}

/* Adicionar padding à direita para evitar que o botão fique muito próximo do texto */
:deep(.attachment-item .v-list-item__content) {
  padding-right: 16px;
}

.attachments-container {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #e0e0e0;
}

.attachments-header {
  font-weight: 500;
  color: #1a237e;
  display: flex;
  align-items: center;
}

.attachments-list {
  background-color: white;
  border-radius: 4px;
  overflow: hidden;
}

:deep(.v-list-item) {
  border-bottom: 1px solid #f0f0f0;
}

:deep(.v-list-item:last-child) {
  border-bottom: none;
}

/* Centralização do texto dos botões */
.btn-centered {
  text-align: center !important;
}

.btn-centered :deep(.v-btn__content) {
  justify-content: center !important;
  text-align: center !important;
  width: 100% !important;
  display: flex !important;
}

/* Centralização específica para botões com texto - SOLUÇÃO DE ESPAÇAMENTO */
.btn-centered-text {
  text-align: center !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}

.btn-centered-text :deep(.v-btn__content) {
  justify-content: center !important;
  align-items: center !important;
  text-align: center !important;
  width: 100% !important;
  display: flex !important;
  padding: 0 !important;
  margin: 0 !important;
}

/* Remove margens e paddings que desalinham o texto */
.btn-centered-text :deep(.v-btn__prepend) {
  margin-inline-start: 0 !important;
  margin-inline-end: 6px !important;
  margin-left: 0 !important;
  margin-right: 6px !important;
}

/* Força o espaçamento igual dos dois lados */
.btn-centered-text :deep(.v-btn__content) {
  padding-left: 8px !important;
  padding-right: 8px !important;
}

/* Ajuste específico para botões pequenos */
.btn-centered-text.v-btn--size-small {
  min-width: auto !important;
  padding: 0 8px !important;
}

.btn-centered-text.v-btn--size-small :deep(.v-btn__content) {
  padding: 0 4px !important;
}

/* Centralização forçada para qualquer botão com essa classe */
.btn-centered-text :deep(*) {
  text-align: center !important;
}

/* Container dos botões de ação — quebra de linha com espaçamento uniforme */
.action-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: center;
  align-items: center;
}

/* Estilo específico para botões de ação */
.action-btn {
  min-width: 85px !important;
  text-align: center !important;
  justify-content: center !important;
  margin: 0 !important;
}

.action-btn :deep(.v-btn__content) {
  justify-content: center !important;
  text-align: center !important;
  width: 100% !important;
  padding: 0 !important;
  margin: 0 !important;
}

/* Força centralização absoluta */
.action-btn :deep(.v-btn__content) {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 4px !important;
}

.sortable-th {
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

.sortable-th:hover {
  background-color: #ebebeb !important;
}

.sort-icon-active {
  color: #1a237e;
  margin-left: 2px;
}

.sort-icon-idle {
  color: #bbb;
  margin-left: 2px;
}

.pagination-ellipsis {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 40px;
  color: rgba(0, 0, 0, 0.4);
  font-size: 1rem;
}

/* Estilo para prazos vencidos */
.deadline-overdue {
  color: #d32f2f !important;
  font-weight: 600 !important;
}

/* Estilos para filtros de data */
.date-compact {
  display: flex;
  flex-direction: column;
  height: 56px; /* Altura fixa para corresponder aos outros campos */
  padding-top: 0; /* Remove o padding superior */
}

.date-label {
  font-size: 12px; /* Tamanho de fonte reduzido para corresponder aos labels do Vuetify */
  color: rgba(0, 0, 0, 0.6);
  padding-top: 0;
  margin-bottom: 3px; /* Espaçamento menor entre o label e os campos */
  line-height: 12px; /* Altura da linha reduzida */
  transform: translateY(-4px); /* Move o label 4px para cima */
}

.date-inputs {
  display: flex;
  gap: 8px;
  height: 40px; /* Altura fixa para os inputs */
}

.date-input {
  flex: 1;
  margin-top: 0 !important; /* Remove margens automáticas */
  margin-bottom: 0 !important;
}

/* Trava a altura do campo para corresponder aos selects (density compact = 40px) */
:deep(.date-input .v-field) {
  --v-field-padding-top: 0;
  --v-field-padding-bottom: 0;
}

:deep(.date-input .v-field__field),
:deep(.date-input .v-field__input) {
  min-height: 40px;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

/* Input de data nativo ocupa toda a largura/altura sem esticar a caixa */
:deep(.date-input input[type="date"]) {
  height: 40px;
  font-size: 14px;
}

/* Garantir que os campos de data tenham o mesmo estilo que os outros */
:deep(.date-input .v-field__outline) {
  --v-field-border-width: 1px !important;
  border-width: var(--v-field-border-width) !important;
}

/* Aplicar estilo consistente aos inputs */
:deep(.v-col) {
  padding-top: 6px;
  padding-bottom: 6px;
}
</style>