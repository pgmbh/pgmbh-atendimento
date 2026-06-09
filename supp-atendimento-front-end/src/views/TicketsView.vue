<template>
  <div class="dashboard">
    <AppHeader />
    <div class="dashboard-layout">
      <AppSidebar />
      <div class="dashboard-content" :style="{ marginLeft: sidebarCollapsed ? '60px' : '250px' }">
        <div class="tickets-page">
          <div class="d-flex justify-space-between align-center mb-4">
            <h2 class="text-h5 font-weight-medium">Atendimentos</h2>
            <v-btn color="primary" @click="createTicket" class="create-button">
              <span class="icon-text me-2">➕</span>
              Novo Atendimento
            </v-btn>
          </div>
          <!-- Filtros de Pesquisa -->
          <v-card class="mb-4">
            <v-card-text>
              <v-row>
                <!-- Campo de filtro por status -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchStatus" :items="statusOptions" item-title="title" item-value="value"
                    label="Status" outlined dense @update:model-value="handleFilter"></v-select>
                </v-col>

                <!-- Intervalo de datas compacto -->
                <v-col cols="12" sm="3">
                  <div class="date-compact">
                    <div class="date-label mb-1">Período</div>
                    <div class="date-inputs">
                      <v-text-field v-model="startDate" type="date" density="compact" hide-details
                        variant="outlined" class="date-input"></v-text-field>

                      <v-text-field v-model="endDate" type="date" density="compact" hide-details
                        variant="outlined" class="date-input"></v-text-field>
                    </div>
                  </div>
                </v-col>

                <!-- Filtro de Prioridade -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchPriority" :items="priorityOptions" item-title="title" item-value="value"
                    label="Prioridade" outlined dense @update:model-value="handleFilter"></v-select>
                </v-col>

                <!-- Filtro de Sistema -->
                <v-col cols="12" sm="3">
                  <v-select v-model="searchProject" :items="projectOptions" item-title="title" item-value="value"
                    label="Sistema" outlined dense @update:model-value="handleFilter"></v-select>
                </v-col>

                <!-- Nova linha para botões (mantida como estava) -->
                <v-col cols="12" class="d-flex align-center mt-2">
                  <v-btn color="primary" @click="handleFilter" :loading="loading" class="me-2 btn-centered">
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
                    class="btn-centered"
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
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
          <v-card class="tickets-table">
            <v-table hover>
              <thead>
                <tr>
                  
                  <th class="px-4 py-3">Número</th>
                  <th class="text-left">Prioridade</th> <!-- Nova coluna -->
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">Setor</th>
                  <th class="px-4 py-3">Sistema</th>
                  <th class="px-4 py-3">Data de Criação</th>
                  <th class="px-4 py-3">Prazo</th>
                  <th class="px-4 py-3">Data de Conclusão</th>
                  <th class="px-4 py-3">Acompanhar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ticket in tickets" :key="ticket.id">
                  
                  <td class="px-4 py-3">{{ ticket.title }}</td>
                  <td>
                    <v-chip :color="getPriorityColor(ticket.priority)"
                      :text-color="getPriorityTextColor(ticket.priority)" size="small" class="priority-chip">
                      {{ ticket.priority }}
                    </v-chip>
                  </td>
                  <td class="px-4 py-3">
                    <v-chip :color="getStatusColor(ticket.status)" :class="['status-chip', ticket.status.toLowerCase()]"
                      size="small">
                      {{ translateStatus(ticket.status) }}
                    </v-chip>
                  </td>
                  <td class="px-4 py-3">{{ ticket.sector.name }}</td>
                  <td class="px-4 py-3">{{ ticket.project ? (ticket.project.acronym || ticket.project.name) : '-' }}</td>

                  <td class="px-4 py-3">{{ formatDate(ticket.dates.created) }}</td>
                  <td class="px-4 py-3" :class="getDeadlineClass(ticket.dates.deadline)">{{ formatDate(ticket.dates.deadline) }}</td>
                  <td class="px-4 py-3">{{ ticket.dates.concluded ? formatDate(ticket.dates.concluded) : '-' }}</td>
                  <td class="px-4 py-3">
                    <div class="d-flex gap-2">
                      <!-- Visualizar histórico -->
                      <v-btn 
                        size="small" 
                        color="info" 
                        class="btn-centered-text action-btn"
                        @click="openTicketDetails(ticket)"
                      >
                        📋 Ver Histórico
                      </v-btn>
                      
                      <!-- Registrar comentário - apenas para status não concluídos -->
                      <v-btn 
                        v-if="ticket.status !== 'CONCLUDED'"
                        size="small" 
                        color="success" 
                        class="btn-centered-text action-btn"
                        @click="openCommentDialog(ticket)"
                      >
                        💬 Registrar comentário
                      </v-btn>
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
                <!-- Botão Anterior -->
                <v-btn :disabled="currentPage === 1" @click="handlePageChange(currentPage - 1)" size="small"
                  variant="text" class="pagination-button">
                  Anterior
                </v-btn>

                <!-- Números das páginas -->
                <v-btn v-for="page in meta.last_page" :key="page" :color="currentPage === page ? 'primary' : ''"
                  :variant="currentPage === page ? 'flat' : 'text'" size="small" @click="handlePageChange(page)"
                  class="pagination-button">
                  {{ page }}
                </v-btn>

                <!-- Botão Próximo -->
                <v-btn :disabled="currentPage === meta.last_page" @click="handlePageChange(currentPage + 1)"
                  size="small" variant="text" class="pagination-button">
                  Próximo
                </v-btn>
              </div>
            </div>

          </v-card>
        </div>
      </div>
    </div>
  </div>

  <!-- Modais -->
  <TicketDetailsModal v-model="showDetailsModal" :ticket="selectedTicket" @ticket-reopened="handleTicketReopened" />
  
  <!-- Modal de Comentário -->
  <v-dialog v-model="commentDialog.show" max-width="600px">
    <v-card>
      <v-card-title class="text-h5">
        Registrar Comentário
        <v-chip v-if="commentDialog.ticket" color="primary" variant="outlined" size="small" class="ml-2">
          #{{ commentDialog.ticket.id }}
        </v-chip>
      </v-card-title>
      
      <v-card-text>
        <div v-if="commentDialog.ticket" class="mb-4 pa-3 bg-grey-lighten-4 rounded">
          <strong>{{ commentDialog.ticket.title }}</strong>
          <br>
          <small class="text-grey-darken-1">Status: {{ translateStatus(commentDialog.ticket.status) }}</small>
        </div>
        
        <v-textarea
          v-model="commentDialog.comment"
          label="Digite seu comentário"
          placeholder="Descreva suas observações, dúvidas ou informações adicionais sobre este ticket..."
          rows="4"
          variant="outlined"
          :rules="[v => !!v || 'Comentário é obrigatório']"
        ></v-textarea>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn 
          color="grey-darken-1" 
          variant="text" 
          @click="closeCommentDialog"
          :disabled="commentDialog.loading"
        >
          Cancelar
        </v-btn>
        <v-btn 
          color="primary" 
          variant="flat"
          @click="submitComment"
          :loading="commentDialog.loading"
          :disabled="!commentDialog.comment?.trim()"
        >
          Enviar Comentário
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>

import TicketDetailsModal from '@/components/tickets/TicketDetailsModal.vue'
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { format, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import api from '@/services/api';
import AppHeader from '@/components/common/AppHeader.vue';
import AppSidebar from '@/components/common/AppSidebar.vue';
import { useSidebar } from '@/composables/useSidebar';
import { authService } from '@/services/auth.service';
import DateRangeSelector from '@/components/common/DateRangeSelector.vue';


// Valores das datas (input type="date" → string "YYYY-MM-DD")
const startDate = ref(null);
const endDate = ref(null);

// Watch para mudanças nas datas
watch([startDate, endDate], () => {
  if (startDate.value || endDate.value) {
    console.log('Datas selecionadas:', { startDate: startDate.value, endDate: endDate.value });
    handleFilter();
  }
});


const selectedTicket = ref(null)
const showDetailsModal = ref(false)

// Estado para o modal de comentário
const commentDialog = ref({
  show: false,
  ticket: null,
  comment: '',
  loading: false
})

// Função para carregar os dados do usuário
const carregarDadosUsuario = () => {
  const userData = authService.getUser();
  if (userData) {
    nomeUsuario.value = userData.name; // Assume que o usuário tem uma propriedade 'name'
    console.log('Dados do usuário carregados:', userData);
  } else {
    console.error('Dados do usuário não encontrados');
    router.push('/login');
  }
};

const getPriorityColor = (priority) => {
  const colors = {
    'URGENTE': 'red-darken-1',
    'ALTA': 'orange-darken-1',
    'NORMAL': 'blue',
    'BAIXA': 'green'
  }
  return colors[priority] || 'grey'
}

const getPriorityTextColor = (priority) => {
  return ['URGENTE', 'ALTA'].includes(priority) ? 'white' : 'white'
}


const getStatusColor = (status) => {
  const colors = {
    'NEW': 'grey',
    'OPEN': 'blue',
    'IN_PROGRESS': 'orange',
    'RESOLVED': 'green',
    'CANCELADO': 'red',
    'RETORNO': 'cyan',
    'CONCLUDED': 'purple',
    'backlog': 'blue-grey',
    'product backlog': 'blue-grey',
  }
  return colors[status] || 'grey'
}
// Adicione a função que abre o modal
const openTicketDetails = async (ticket) => {
  try {
    console.log('Abrindo detalhes do ticket aq:', ticket) // Log para debug
    selectedTicket.value = ticket
    showDetailsModal.value = true

    // Carrega o histórico do ticket
    const response = await api.get(`/service/${ticket.id}/history`)
    if (response.data.success) {
      selectedTicket.value = {
        ...ticket,
        histories: response.data.data
      }
    }
  } catch (error) {
    console.error('Erro ao carregar detalhes do ticket:', error)
  }
}

const { sidebarCollapsed } = useSidebar();
const router = useRouter();
const tickets = ref([]);
const loading = ref(false);
const nomeUsuario = ref(''); // Adicionando a referência ao nome do usuário

const currentPage = ref(1);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 5,
  total: 0
});


// Filtros de pesquisa
const searchName = ref('');
const searchStatus = ref('');
/*const searchDateRange = ref([]);*/
const dateRange = ref({ startDate: null, endDate: null });

const searchPriority = ref('');
const searchProject = ref('');
const dateMenu = ref(false);
const showCompleted = ref(true);

// Sistemas (projetos) disponíveis para o filtro
const projects = ref([]);
const projectOptions = computed(() => [
  { title: 'Todos os sistemas', value: '' },
  ...projects.value.map(p => ({
    title: p.acronym ? `${p.acronym} - ${p.name}` : p.name,
    value: p.id
  }))
]);

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

const statusOptions = [
  { title: 'Todos os status', value: '' },
  { title: 'Novo', value: 'NEW' },
  { title: 'Aberto', value: 'OPEN' },
  { title: 'Em Andamento', value: 'IN_PROGRESS' },
  { title: 'Resolvido', value: 'RESOLVED' },
  { title: 'Cancelado', value: 'CANCELADO' },
  { title: 'Retorno', value: 'RETORNO' },
  { title: 'Concluído', value: 'CONCLUDED' },
  { title: 'Backlog', value: 'backlog' },
  { title: 'Product Backlog', value: 'product backlog' },
];
const priorityOptions = [
  { title: 'Todas as prioridades', value: '' },
  { title: 'Baixa', value: 'BAIXA' },
  { title: 'Normal', value: 'NORMAL' },
  { title: 'Alta', value: 'ALTA' },
  { title: 'Urgente', value: 'URGENTE' }
];

// Função removida - filtros agora são aplicados no backend via loadTickets()



const resetFilters = () => {
  // Limpa todos os campos de filtro
  searchName.value = '';
  searchStatus.value = '';
  searchPriority.value = '';
  searchProject.value = '';
  startDate.value = null;
  endDate.value = null;

  // Recarrega os dados
  currentPage.value = 1;
  loadTickets(1);
};

const toggleCompleted = () => {
  showCompleted.value = !showCompleted.value;
  // Recarrega os dados com o novo filtro
  currentPage.value = 1;
  loadTickets(1);
};



const handlePageChange = async (page) => {
  await loadTickets(page);
};

const handleDateRangeChange = () => {
  // Quando o intervalo de datas mudar, acionar a pesquisa
  handleFilter();
};

const translateStatus = (status) => {
  const translations = {
    'new': 'NOVO',
    'NEW': 'NOVO',
    'OPEN': 'ABERTO',
    'IN_PROGRESS': 'EM ANDAMENTO',
    'RESOLVED': 'RESOLVIDO',
    'CANCELADO': 'CANCELADO',
    'RETORNO': 'RETORNO',
    'CONCLUDED': 'CONCLUÍDO',
    'backlog': 'BACKLOG',
    'product backlog': 'PRODUCT BACKLOG',
  };
  return translations[status] || status;
};



const formatDate = (dateString) => {
  if (!dateString) return '';
  return format(new Date(dateString), "dd/MM/yyyy 'às' HH:mm", {
    locale: ptBR
  });
};

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
};

const loadTickets = async (page = 1) => {
  loading.value = true;
  try {
    if (!authService.isAuthenticated()) {
      console.log('Usuário não autenticado');
      router.push('/login');
      return;
    }

    // Construir os parâmetros de filtro
    const params = new URLSearchParams({
      page: page.toString(),
      sort: 'created_at',
      order: 'desc'
    });
    // Adicionar filtros apenas se tiverem valor
    if (searchName.value) {
      params.append('title', searchName.value);
    }
    if (searchStatus.value) {
      params.append('status', searchStatus.value);
    }
    if (searchPriority.value) {
      console.log('Filtro de prioridade aplicado:', searchPriority.value);
      params.append('priority', searchPriority.value);
    }
    if (searchProject.value) {
      params.append('project_id', searchProject.value);
    }


    if (startDate.value) {
      // Certifique-se de que a data está no formato ISO
      let formattedStartDate = startDate.value;
      if (startDate.value instanceof Date) {
        formattedStartDate = startDate.value.toISOString().split('T')[0];
      }
      params.append('start_date', formattedStartDate);
    }
    
    if (endDate.value) {
      // Certifique-se de que a data está no formato ISO
      let formattedEndDate = endDate.value;
      if (endDate.value instanceof Date) {
        formattedEndDate = endDate.value.toISOString().split('T')[0];
      }
      params.append('end_date', formattedEndDate);
    }

    // Adicionar filtro de tickets concluídos
    if (!showCompleted.value) {
      params.append('exclude_status', 'CONCLUDED');
    }

    const url = `/service/my-tickets?${params.toString()}`;
    console.log('URL da requisição:', url);
    console.log('Parâmetros enviados:', Object.fromEntries(params));
    
    const response = await api.get(url);

    if (response.data.success) {
      tickets.value = response.data.data;
      meta.value = response.data.meta;
      console.log('Tickets carregados:', tickets.value.length);
    } else {
      tickets.value = [];
    }
  } catch (error) {
    console.error('Erro ao carregar tickets:', error);
    tickets.value = [];

    if (error.response?.status === 401) {
      authService.logout();
      router.push('/login');
    }
  } finally {
    loading.value = false;
  }
};

// Função para acionar a pesquisa com debounce
let filterTimeout;
const handleFilter = async () => {
  try {
    // Limpa timeout anterior
    clearTimeout(filterTimeout);
    
    // Aguarda um pouco para o valor ser definido
    filterTimeout = setTimeout(async () => {
      console.log('Executando filtro com valores:', {
        status: searchStatus.value,
        priority: searchPriority.value,
        startDate: startDate.value,
        endDate: endDate.value
      });
      
      // Reseta para a primeira página
      currentPage.value = 1;

      // Inicia o carregamento
      loading.value = true;

      try {
        // Carrega os tickets com os filtros
        await loadTickets(1);
      } finally {
        loading.value = false;
      }
    }, 300); // 300ms de delay

  } catch (error) {
    console.error('Erro ao filtrar tickets:', error);
    loading.value = false;
  }
};

// Adicione um debounce para o campo de pesquisa por nome
let searchTimeout;
const handleSearchInput = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    handleFilter();
  }, 500); // Aguarda 500ms após o usuário parar de digitar
};

const createTicket = () => router.push('/tickets/create');
const viewTicket = (id) => router.push(`/tickets/${id}`);
const editTicket = (id) => router.push(`/tickets/${id}/edit`);

const handleTicketReopened = () => {
  // Recarrega a lista de tickets para refletir a mudança
  loadTickets(currentPage.value);
};

// Funções para o modal de comentário
const openCommentDialog = (ticket) => {
  commentDialog.value = {
    show: true,
    ticket: ticket,
    comment: '',
    loading: false
  };
};

const closeCommentDialog = () => {
  commentDialog.value = {
    show: false,
    ticket: null,
    comment: '',
    loading: false
  };
};

const submitComment = async () => {
  try {
    commentDialog.value.loading = true;
    
    // Chamada para a API para registrar o comentário
    const response = await api.post(`/service/${commentDialog.value.ticket.id}/comment`, {
      comment: commentDialog.value.comment.trim()
    });
    
    if (response.data.success) {
      // Feedback de sucesso
      alert('Comentário registrado com sucesso!');
      
      // Fecha o modal
      closeCommentDialog();
      
      // Recarrega a lista para refletir mudanças
      loadTickets(currentPage.value);
    }
  } catch (error) {
    console.error('Erro ao registrar comentário:', error);
    alert('Erro ao registrar comentário. Tente novamente.');
  } finally {
    commentDialog.value.loading = false;
  }
};

onMounted(() => {
  carregarDadosUsuario(); // Carrega os dados do usuário quando o componente é montado
  loadProjects();
  loadTickets();
});
</script>

<style scoped>
.intervalo-datas {
  width: 100%;
}

.date-inputs {
  display: flex;
  align-items: center;
  gap: 8px;
}

.date-field {
  flex: 1;
}

.date-separator {
  margin: 0 4px;
  color: rgba(0, 0, 0, 0.6);
}

.create-button {
  display: flex !important;
  align-items: center !important;
  gap: 4px !important;
}

.create-button .icon-text {
  font-size: 1rem;
  line-height: 1;
  display: flex;
  align-items: center;
}

/* Adicione estes novos estilos */
.v-btn {
  text-transform: uppercase !important;
  font-weight: 500 !important;
}

.v-btn:hover {
  opacity: 0.9;
}

.d-flex {
  gap: 8px;
}

.dashboard {
  min-height: 100vh;
  background-color: #f3f4f6;
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

.id-prefix {
  color: #666;
  margin-right: 2px;
}

.status-chip {
  font-size: 0.875rem;
  font-weight: 500;
}

.status-chip.new {
  background-color: #E3F2FD !important;
}

.status-chip.open {
  background-color: #FFF3E0 !important;
}

.status-chip.closed {
  background-color: #EEEEEE !important;
}

.status-chip.resolved {
  background-color: #E8F5E9 !important;
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

.action-button {
  min-width: 36px !important;
  padding: 0 8px !important;
  cursor: pointer !important;
  /* Adiciona o cursor de mãozinha */
}

.icon-text {
  font-size: 1.2rem;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  /* Adiciona também ao texto do ícone para garantir */
}

.action-button:hover {
  background-color: rgba(0, 0, 0, 0.04) !important;
}

/* Ajuste de alinhamento vertical */
.d-flex {
  align-items: center;
}


.pagination-container {
  border-top: 1px solid #e0e0e0;
}

:deep(.v-pagination__item) {
  color: #1a237e;
}

:deep(.v-pagination__item--active) {
  background-color: #1a237e !important;
}


.pagination-wrapper {
  padding: 16px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(0, 0, 0, 0.12);
  background-color: #ffffff;
}

.pagination-info {
  color: rgba(0, 0, 0, 0.6);
  font-size: 0.875rem;
  font-weight: 400;
  letter-spacing: 0.15px;
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
  font-weight: 500 !important;
  letter-spacing: 0.0892857143em !important;
  text-transform: uppercase !important;
  transition: background-color 0.2s ease-in-out !important;
  cursor: pointer !important;
  /* Adiciona a maozinha em todos os botões */
}

/* Estilo para o botão da página atual */
.pagination-button.v-btn--variant-flat {
  background-color: #1a237e !important;
  color: #ffffff !important;
  box-shadow: 0 3px 1px -2px rgba(0, 0, 0, .2), 0 2px 2px 0 rgba(0, 0, 0, .14), 0 1px 5px 0 rgba(0, 0, 0, .12) !important;
  cursor: default !important;
  /* Remove a maozinha do botão ativo */

}

.pagination-button:hover {
  background-color: rgba(26, 35, 126, 0.04) !important;
}

.pagination-button:disabled {
  color: rgba(0, 0, 0, 0.38) !important;
  background-color: rgba(0, 0, 0, 0.12) !important;
  cursor: default !important;
}

.pagination-button:hover:not(:disabled):not(.v-btn--variant-flat) {
  background-color: rgba(26, 35, 126, 0.04) !important;
  transform: translateY(-1px);
  /* Leve efeito de elevação ao passar o mouse */
}

/* Solução revisada para o componente de período */
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

/* Corrige o alinhamento do container do período */

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

/* Estilo para prazos vencidos */
.deadline-overdue {
  color: #d32f2f !important;
  font-weight: 600 !important;
}

</style>