import { createRouter, createWebHistory  } from 'vue-router'
import Style from '@/views/StyleView.vue'
import Home from '@/views/HomeView.vue'
import axios from 'axios'

const routes = [
  {
    meta: {
      title: 'Login',
    },
    path: '/',
    name: 'login',
    component: () => import('@/views/Login.vue'),
  },
  {
    meta:{
      title: 'Select style',
    },
    path: '/style',
    name: 'style-alt',
    component: Style,
  },
  {
    // Document title tag
    // We combine it with defaultDocumentTitle set in `src/main.js` on router.afterEach hook
    meta: {
      title: 'Dashboard',
      requiresAuth: true,
    },
    path: '/dashboard',
    name: 'dashboard',
    component: Home,
  },
  {
    meta: {
      title: 'Tables',
    },
    path: '/tables',
    name: 'tables',
    component: () => import('@/views/TablesView.vue'),
  },
  {
    meta: {
      title: 'Forms',
    },
    path: '/forms',
    name: 'forms',
    component: () => import('@/views/FormsView.vue'),
  },
  {
    meta: {
      title: 'Profile',
    },
    path: '/profile',
    name: 'profile',
    component: () => import('@/views/ProfileView.vue'),
  },
  {
    meta: {
      title: 'Ui',
    },
    path: '/ui',
    name: 'ui',
    component: () => import('@/views/UiView.vue'),
  },
  {
    meta: {
      title: 'Responsive layout',
    },
    path: '/responsive',
    name: 'responsive',
    component: () => import('@/views/ResponsiveView.vue'),
  },
  // {
  //   meta: {
  //     title: 'Login',
  //   },
  //   path: '/login',
  //   name: 'login',
  //   component: () => import('@/views/Login.vue'),
  // },
  {
    meta: {
      title: 'Error',
    },
    path: '/error',
    name: 'error',
    component: () => import('@/views/ErrorView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 }
  },
});

router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('auth_token');
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

  if( requiresAuth && !token ) {
    console.log("No token found, redirecting to login.");
    next({ name: 'login' });
  } else if (requiresAuth && token ) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    try{  
      console.log("Verifying token in router...");
      await axios.get('/api/users');
      console.log("Token is valid, proceeding to route.");
      next();
    } catch (error) {
      if (error.response && error.response.status === 401) {
        console.log("Token is invalid, redirecting to login.");
        localStorage.clear();
        delete axios.defaults.headers.common['Authorization'];
        next({ name: 'login' });
      } else { 
        console.error("Error verifying token:", error);
        next(false);
      }
    }
  }
  else{
    if(to.name === 'login' && token){
      console.log("Already logged in, redirecting to dashboard.");
      next({ name: 'dashboard' });
  }else{
    console.log("No auth required, proceeding to route.");
    next();
    }
  }
});

export default router
