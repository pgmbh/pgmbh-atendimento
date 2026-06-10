import { createRouter, createWebHistory } from 'vue-router';
import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';
import TicketsView from '../views/TicketsView.vue';
import { authService } from '@/services/auth.service';

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true }
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView
  },
  {
    path: '/attendant/login',
    redirect: '/login'
  },
  {
    path: '/tickets',
    name: 'tickets',
    component: TicketsView,
    meta: { requiresAuth: true }
  },
  {
    path: '/tickets/create',
    name: 'create-ticket',
    component: () => import('@/views/CreateTicket.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/change-password',
    name: 'change-password',
    component: () => import('@/views/ChangePasswordView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/attendant/dashboard',
    name: 'attendant-dashboard',
    component: () => import('../views/AttendantDashboard.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/attendant/tickets',
    name: 'attendant-tickets',
    component: () => import('@/views/AttendantTicketsView.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/tickets/:id',
    name: 'ticket-details',
    component: () => import('@/views/TicketDetailsView.vue'),
    props: true
  },
  {
    path: '/attendant/admin/users',
    name: 'admin-users',
    component: () => import('@/views/AdminUsersView.vue'),
    meta: { requiresAttendantAuth: true, requiresAdmin: true }
  },
  {
    path: '/attendant/admin/service-types',
    name: 'admin-service-types',
    component: () => import('@/views/AdminServiceTypesView.vue'),
    meta: { requiresAttendantAuth: true, requiresAdmin: true }
  },
  {
    path: '/attendant/admin/priorities',
    name: 'admin-priorities',
    component: () => import('@/views/AdminPrioritiesView.vue'),
    meta: { requiresAttendantAuth: true, requiresAdmin: true }
  },
  {
    path: '/attendant/admin/sectors',
    name: 'admin-sectors',
    component: () => import('@/views/AdminSectorsView.vue'),
    meta: { requiresAttendantAuth: true, requiresAdmin: true }
  },
  {
    path: '/attendant/admin/tags',
    name: 'admin-tags',
    component: () => import('@/views/AdminTagsView.vue'),
    meta: { requiresAttendantAuth: true, requiresAdmin: true }
  },
  {
    path: '/attendant/change-password',
    name: 'attendant-change-password',
    component: () => import('@/views/AttendantChangePasswordView.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/projects',
    name: 'projects',
    component: () => import('@/views/projects/ProjectList.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/projects/:id',
    name: 'project-detail',
    component: () => import('@/views/projects/ProjectDetail.vue'),
    meta: { requiresAttendantAuth: true },
    props: true
  },
  {
    path: '/schedule',
    name: 'schedule',
    component: () => import('@/views/schedule/ScheduleView.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/reports/productivity',
    name: 'productivity-report',
    component: () => import('@/views/reports/ProductivityReport.vue'),
    meta: { requiresAttendantAuth: true }
  },
  {
    path: '/reports/activity',
    name: 'activity-report',
    component: () => import('@/views/reports/ActivityReport.vue'),
    meta: { requiresAttendantAuth: true }
  }
];

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
});

router.beforeEach((to, _from, next) => {
  const authenticated = authService.isAuthenticated();

  if (to.matched.some(record => record.meta.requiresAttendantAuth)) {
    if (!authenticated || !authService.isAttendant()) {
      return next('/login');
    }
    if (to.matched.some(record => record.meta.requiresAdmin) && !authService.isAdmin()) {
      return next('/attendant/dashboard');
    }
    return next();
  }

  if (to.matched.some(record => record.meta.requiresAuth)) {
    if (!authenticated) {
      return next('/login');
    }
    return next();
  }

  next();
});

export default router;
