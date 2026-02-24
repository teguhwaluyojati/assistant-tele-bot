import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useMainStore } from '@/stores/main.js'
import { useDarkModeStore } from './stores/darkMode'
import axios from 'axios'

import './css/main.css'

const pinia = createPinia()

axios.defaults.withCredentials = true
localStorage.removeItem('auth_token')

const ERROR_REPORT_TTL = 60000
let lastErrorAt = 0
let lastErrorSignature = ''

const reportClientError = async (payload) => {
  const signature = `${payload.message}|${payload.url}`
  const now = Date.now()

  if (signature === lastErrorSignature && now - lastErrorAt < ERROR_REPORT_TTL) {
    return
  }

  lastErrorSignature = signature
  lastErrorAt = now

  try {
    await axios.post('/api/client-error', payload)
  } catch (error) {
    // Ignore reporting failures.
  }
}

window.addEventListener('error', (event) => {
  reportClientError({
    message: event.message || 'Unknown error',
    url: window.location.href,
    component: 'window.error',
    userAgent: navigator.userAgent,
  })
})

window.addEventListener('unhandledrejection', (event) => {
  const message = event.reason?.message || String(event.reason || 'Unhandled rejection')
  reportClientError({
    message,
    url: window.location.href,
    component: 'window.unhandledrejection',
    userAgent: navigator.userAgent,
  })
})

createApp(App).use(router).use(pinia).mount('#app')
const darkStore = useDarkModeStore(pinia)
if (
  (!localStorage['darkMode'] && window.matchMedia('(prefers-color-scheme: dark)').matches) ||
  localStorage['darkMode'] === '1'
) {
  darkStore.set(true)
}

const defaultDocumentTitle = 'My Asisstant'

router.afterEach((to) => {
  document.title = to.meta?.title
    ? `${to.meta.title} — ${defaultDocumentTitle}`
    : defaultDocumentTitle
})
