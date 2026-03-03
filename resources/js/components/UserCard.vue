<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useMainStore } from '@/stores/main'
import { mdiCheckDecagram, mdiClose } from '@mdi/js'
import BaseLevel from '@/components/BaseLevel.vue'
import UserAvatarCurrentUser from '@/components/UserAvatarCurrentUser.vue'
import CardBox from '@/components/CardBox.vue'
import FormCheckRadio from '@/components/FormCheckRadio.vue'
import PillTag from '@/components/PillTag.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import axios from 'axios'

const mainStore = useMainStore()

const userName = computed(() => mainStore.userName)
const userAvatar = computed(() => mainStore.userAvatar)
const lastLogin = ref(null)
const currentUserRoleLabel = computed(() => {
  const level = Number(mainStore.currentUser?.telegram_user?.level)

  if (level === 0) {
    return 'Superadmin'
  }

  if (level === 1) {
    return 'Admin'
  }

  if (level === 2) {
    return 'Member'
  }

  return 'Unknown'
})

const currentUserRoleClass = computed(() => {
  const level = Number(mainStore.currentUser?.telegram_user?.level)

  if (level === 0) {
    return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-400/40'
  }

  if (level === 1) {
    return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:border-blue-400/40'
  }

  if (level === 2) {
    return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-400/40'
  }

  return 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-slate-700/40 dark:text-slate-200 dark:border-slate-500/40'
})

const currentUserRoleDotClass = computed(() => {
  const level = Number(mainStore.currentUser?.telegram_user?.level)

  if (level === 0) {
    return 'bg-amber-500 dark:bg-amber-300'
  }

  if (level === 1) {
    return 'bg-blue-500 dark:bg-blue-300'
  }

  if (level === 2) {
    return 'bg-emerald-500 dark:bg-emerald-300'
  }

  return 'bg-gray-400 dark:bg-slate-300'
})

const formatRelativeLastLogin = (value) => {
  if (!value) {
    return 'just now'
  }

  const loginDate = new Date(value)
  if (Number.isNaN(loginDate.getTime())) {
    return 'recently'
  }

  const diffMs = Date.now() - loginDate.getTime()
  const diffMinutes = Math.max(1, Math.floor(diffMs / 60000))

  if (diffMinutes < 60) {
    return `${diffMinutes} min${diffMinutes === 1 ? '' : 's'} ago`
  }

  const diffHours = Math.floor(diffMinutes / 60)
  if (diffHours < 24) {
    return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`
  }

  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`
}

const lastLoginLabel = computed(() => {
  if (!lastLogin.value) {
    return 'No login history yet'
  }

  const relative = formatRelativeLastLogin(lastLogin.value.created_at)
  const ipAddress = lastLogin.value.ip_address || '-'
  return `Last login ${relative} from ${ipAddress}`
})

const isModalActive = ref(false)

const openModal = () => {
  isModalActive.value = true
}

const closeModal = () => {  
  isModalActive.value = false
}

const userSwitchVal = ref(false)

const handleKeydown = (e) => {
  if (e.key === 'Escape' && isModalActive.value) {
    closeModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)

  axios.get('/api/history-login')
    .then((response) => {
      if (response?.data?.success && response?.data?.data) {
        lastLogin.value = response.data.data
      }
    })
    .catch((error) => {
      console.error('Failed to fetch last login:', error)
    })
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <CardBox v-bind="$attrs">
    <BaseLevel type="justify-around lg:justify-center">
      <div @click="openModal" class="cursor-pointer transition-transform hover:scale-105" title="Klik untuk memperbesar">
      <UserAvatarCurrentUser class="lg:mx-12" />
      </div>
      <div class="space-y-3 text-center md:text-left lg:mx-12">
        <div class="flex justify-center md:block">
          <FormCheckRadio
            v-model="userSwitchVal"
            name="notifications-switch"
            type="switch"
            label="Notifications"
            :input-value="true"
          />
        </div>
        <h1 class="text-2xl">
          Howdy, <b>{{ userName }}</b
          >!
        </h1>
        <div class="flex justify-center md:justify-start">
          <div
            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors"
            :class="currentUserRoleClass"
          >
            <span class="h-2 w-2 rounded-full" :class="currentUserRoleDotClass" />
            <span>{{ currentUserRoleLabel }}</span>
          </div>
        </div>
        <p class="text-sm text-gray-500 dark:text-slate-400">{{ lastLoginLabel }}</p>
        <div class="flex justify-center md:block">
          <PillTag label="Verified" color="info" :icon="mdiCheckDecagram" />
        </div>
      </div>
    </BaseLevel>
  </CardBox>
  <div v-if="isModalActive" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="closeModal">
    
    <button @click="closeModal" class="absolute top-5 right-5 text-white hover:text-gray-300 transition">
      <BaseIcon :path="mdiClose" size="36" />
    </button>

    <div class="relative max-w-3xl w-full">
      <img 
        :src="userAvatar" 
        alt="Full Avatar" 
        class="w-full h-auto rounded-lg shadow-2xl border-4 border-white/20"
      >
    </div>
  </div>
</template>
