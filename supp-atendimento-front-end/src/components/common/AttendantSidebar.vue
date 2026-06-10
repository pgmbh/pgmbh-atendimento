<template>
  <aside :class="['sidebar', { 'collapsed': isCollapsed }]">

    <!-- Logo + toggle -->
    <div class="sidebar-header">
      <div class="sidebar-logo" v-show="!isCollapsed">
        <SuppLogo :width="24" :height="24" />
        <span class="logo-text">PGM</span>
      </div>
      <button class="toggle-btn" @click="toggleSidebar" :title="isCollapsed ? 'Expandir' : 'Recolher'">
        <i class="mdi" :class="isCollapsed ? 'mdi-menu' : 'mdi-chevron-left'"></i>
      </button>
    </div>

    <!-- User info -->
    <div class="sidebar-user">
      <div class="user-avatar">{{ userInitial }}</div>
      <div class="user-details" v-show="!isCollapsed">
        <div class="user-name">{{ nomeAtendente }}</div>
        <div class="user-role">{{ sector || funcaoAtendente || 'Atendente' }}</div>
      </div>
    </div>

    <div class="sidebar-divider"></div>

    <!-- Navigation -->
    <nav class="sidebar-nav">

      <p class="nav-section-label" v-show="!isCollapsed">APLICAÇÕES</p>
      <ul>
        <li>
          <router-link to="/attendant/dashboard" active-class="active">
            <i class="mdi mdi-view-dashboard-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Dashboard</span>
          </router-link>
        </li>
        <li>
          <router-link to="/attendant/tickets" active-class="active">
            <i class="mdi mdi-ticket-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Atendimentos</span>
          </router-link>
        </li>
        <li>
          <router-link to="/projects" active-class="active">
            <i class="mdi mdi-folder-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Projetos</span>
          </router-link>
        </li>
        <li>
          <router-link to="/schedule" active-class="active">
            <i class="mdi mdi-calendar-month-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Cronograma</span>
          </router-link>
        </li>
      </ul>

      <p class="nav-section-label" v-show="!isCollapsed">MÓDULOS</p>
      <ul>
        <li>
          <router-link to="/reports/productivity" active-class="active">
            <i class="mdi mdi-chart-bar nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Produtividade</span>
          </router-link>
        </li>
        <li>
          <router-link to="/reports/activity" active-class="active">
            <i class="mdi mdi-clipboard-text-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Rel. Atividades</span>
          </router-link>
        </li>
      </ul>

      <template v-if="isAdmin">
        <p class="nav-section-label" v-show="!isCollapsed">CONFIGURAÇÕES</p>
        <ul>
          <li>
            <router-link to="/attendant/admin/users" active-class="active">
              <i class="mdi mdi-account-group-outline nav-icon"></i>
              <span class="nav-text" v-show="!isCollapsed">Gerenciar Usuários</span>
            </router-link>
          </li>
          <li>
            <router-link to="/attendant/admin/service-types" active-class="active">
              <i class="mdi mdi-tag-text-outline nav-icon"></i>
              <span class="nav-text" v-show="!isCollapsed">Tipos de Demanda</span>
            </router-link>
          </li>
          <li>
            <router-link to="/attendant/admin/priorities" active-class="active">
              <i class="mdi mdi-flag-outline nav-icon"></i>
              <span class="nav-text" v-show="!isCollapsed">Prioridades</span>
            </router-link>
          </li>
          <li>
            <router-link to="/attendant/admin/sectors" active-class="active">
              <i class="mdi mdi-domain nav-icon"></i>
              <span class="nav-text" v-show="!isCollapsed">Setores</span>
            </router-link>
          </li>
          <li>
            <router-link to="/attendant/admin/tags" active-class="active">
              <i class="mdi mdi-label-outline nav-icon"></i>
              <span class="nav-text" v-show="!isCollapsed">Etiquetas</span>
            </router-link>
          </li>
        </ul>
      </template>

      <p class="nav-section-label" v-show="!isCollapsed">CONTA</p>
      <ul>
        <li>
          <router-link to="/attendant/change-password" active-class="active">
            <i class="mdi mdi-lock-outline nav-icon"></i>
            <span class="nav-text" v-show="!isCollapsed">Alterar Senha</span>
          </router-link>
        </li>
      </ul>

    </nav>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSidebar } from '@/composables/useSidebar'
import { authService } from '@/services/auth.service'
import SuppLogo from '@/components/common/SuppLogo.vue'

const { sidebarCollapsed, toggleSidebar } = useSidebar()
const isCollapsed = sidebarCollapsed

const nomeAtendente = ref('')
const funcaoAtendente = ref('')
const sector = ref('')

const userInitial = computed(() =>
  nomeAtendente.value ? nomeAtendente.value.charAt(0).toUpperCase() : 'A'
)

const isAdmin = computed(() =>
  funcaoAtendente.value === 'Admin'
)

onMounted(() => {
  const attendant = authService.getAttendantData()
  if (attendant) {
    nomeAtendente.value = attendant.name
    funcaoAtendente.value = attendant.function
    sector.value = attendant.sector?.name || ''
  }
})
</script>

<style scoped>
.sidebar {
  position: fixed;
  left: 0;
  top: 60px;
  bottom: 0;
  width: 250px;
  background: #ffffff;
  border-right: 1px solid #e8eaf6;
  display: flex;
  flex-direction: column;
  transition: width 0.25s ease;
  z-index: 100;
  overflow: hidden;
}

.sidebar.collapsed {
  width: 60px;
}

/* Header */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 14px 10px;
  flex-shrink: 0;
}

.sidebar.collapsed .sidebar-header {
  justify-content: center;
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 8px;
}

.logo-text {
  font-size: 1rem;
  font-weight: 700;
  color: #1a237e;
  letter-spacing: 1px;
}

.toggle-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  color: #5c6bc0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
  flex-shrink: 0;
}

.toggle-btn:hover {
  background: #e8eaf6;
  color: #1a237e;
}

.toggle-btn .mdi {
  font-size: 1.25rem;
}

/* User area */
.sidebar-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px 16px;
  flex-shrink: 0;
}

.sidebar.collapsed .sidebar-user {
  justify-content: center;
  padding: 10px 0 16px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #1a237e;
  color: #fff;
  font-size: 1rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.user-details {
  overflow: hidden;
}

.user-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-role {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 1px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sidebar-divider {
  height: 1px;
  background: #e8eaf6;
  margin: 0 14px 8px;
  flex-shrink: 0;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0 8px 16px;
}

.sidebar-nav ul {
  list-style: none;
  padding: 0;
  margin: 0 0 8px;
}

.sidebar-nav li {
  margin-bottom: 2px;
}

.nav-section-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: #9ca3af;
  letter-spacing: 0.8px;
  padding: 12px 8px 4px;
  margin: 0;
  text-transform: uppercase;
}

.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 10px;
  color: #374151;
  text-decoration: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
  white-space: nowrap;
}

.sidebar-nav a:hover {
  background: #f0f4ff;
  color: #1a237e;
}

.sidebar-nav a.active {
  background: #1a237e;
  color: #ffffff;
}

.sidebar.collapsed .sidebar-nav a {
  justify-content: center;
  padding: 10px;
}

.nav-icon {
  font-size: 1.125rem;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}

.nav-text {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
