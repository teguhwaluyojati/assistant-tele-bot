import { createRouter, createWebHistory } from 'vue-router';
import Home from '@/views/HomeView.vue';
import Style from '@/views/StyleView.vue';
import axios from 'axios';

const routes = [
  // Public routes (no auth)
  {
    path: '/',
    name: 'login',
    meta: { title: 'Login' },
    component: () => import('@/views/Login.vue'),
  },
  {
    path: '/register',
    name: 'register',
    meta: { title: 'Register' },
    component: () => import('@/views/Register.vue'),
  },
  {
    path: '/error',
    name: 'error',
    meta: { title: 'Error' },
    component: () => import('@/views/ErrorView.vue'),
  },

  // Protected routes (require auth)
  {
    path: '/',
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'dashboard',
        component: Home,
        meta: { title: 'Dashboard' },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/ProfileView.vue'),
        meta: { title: 'Profile' },
      },
      {
        path: 'tables',
        name: 'tables',
        component: () => import('@/views/TablesView.vue'),
        meta: { title: 'Tables', requiresAdmin: true },
      },
      {
        path: 'forms',
        name: 'forms',
        component: () => import('@/views/FormsView.vue'),
        meta: { title: 'Forms' },
      },
      {
        path: 'ui',
        name: 'ui',
        component: () => import('@/views/UiView.vue'),
      },
      {
        path: 'responsive',
        name: 'responsive',
        component: () => import('@/views/ResponsiveView.vue'),
      },
      {
        path: 'style',
        name: 'style-alt',
        component: Style,
        meta: { title: 'Select style' },
      },
    ],
  },

  // Fallback route (404)
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/ErrorView.vue'),
    meta: { title: 'Page Not Found' },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 };
  },
});

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('auth_token');
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

  if (requiresAuth && !token) {
    console.log('No token found, redirecting to login.');
    next({ name: 'login' });
  } else if (requiresAuth && token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    try {
      console.log('Verifying token in router...');
      const response = await axios.get('/api/user');
      const currentUser = response?.data || null;
      if (currentUser) {
        localStorage.setItem('user', JSON.stringify(currentUser));
      }
      const isAdmin = currentUser?.telegram_user?.level === 1;
      const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin);
      if (requiresAdmin && !isAdmin) {
        console.log('Admin role required, redirecting to dashboard.');
        next({ name: 'dashboard' });
        return;
      }
      console.log('Token is valid, proceeding to route.');
      next();
    } catch (error) {
      if (error.response && error.response.status === 401) {
        console.log('Token is invalid, redirecting to login.');
        localStorage.clear();
        delete axios.defaults.headers.common['Authorization'];
        next({ name: 'login' });
      } else {
        console.error('Error verifying token:', error);
        next(false);
      }
    }
  } else {
    if (to.name === 'login' && token) {
      console.log('Already logged in, redirecting to dashboard.');
      next({ name: 'dashboard' });
    } else {
      console.log('No auth required, proceeding to route.');
      next();
    }
  }
});

export default router;