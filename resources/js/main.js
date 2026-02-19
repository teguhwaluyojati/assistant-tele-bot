import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { useMainStore } from '@/stores/main.js'
import { useDarkModeStore } from './stores/darkMode'

import './css/main.css'

const pinia = createPinia()

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
