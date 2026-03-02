<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { mdiAccountMultiple, mdiMonitorCellphone, mdiCog, mdiReload, mdiDownload, mdiPlus, mdiCheckCircle, mdiAlertCircle, mdiInformation } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import NotificationBar from '@/components/NotificationBar.vue'
import CardBox from '@/components/CardBox.vue'
import TableSampleClients from '@/components/TableSampleClients.vue'
import TableUserCommands from '@/components/TableUserCommands.vue'
import BaseButton from '@/components/BaseButton.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import { useMainStore } from '@/stores/main'
import { useActionToast } from '@/composables/useActionToast'

const mainStore = useMainStore()
const isClientsLoading = ref(false)
const isCreateUserModalOpen = ref(false)
const isSubmittingUser = ref(false)
const createUserForm = ref({
  name: '',
  email: '',
  telegram_user_id: '',
  telegram_username: '',
  password: '',
  password_confirmation: '',
})
const isCommandFilterModalOpen = ref(false)
const commandFilterStartDate = ref('')
const commandFilterEndDate = ref('')
const commandsRefreshKey = ref(0)
const commandSearchQuery = ref('')
const { toast: usersToast, error: notifyUsersError, runAction } = useActionToast(2600)

const usersToastClass = computed(() => {
  if (usersToast.value.type === 'success') {
    return 'bg-emerald-500'
  }

  if (usersToast.value.type === 'info') {
    return 'bg-blue-500'
  }

  return 'bg-red-500'
})

const usersToastIcon = computed(() => {
  if (usersToast.value.type === 'success') {
    return mdiCheckCircle
  }

  if (usersToast.value.type === 'info') {
    return mdiInformation
  }

  return mdiAlertCircle
})

const resetCreateUserForm = () => {
  createUserForm.value = {
    name: '',
    email: '',
    telegram_user_id: '',
    telegram_username: '',
    password: '',
    password_confirmation: '',
  }
}

const openCreateUserModal = () => {
  resetCreateUserForm()
  isCreateUserModalOpen.value = true
}

const submitCreateUser = async () => {
  if (!createUserForm.value.name?.trim()) {
    notifyUsersError('Name is required.')
    return
  }

  if (!createUserForm.value.email?.trim()) {
    notifyUsersError('Email is required.')
    return
  }

  if (!createUserForm.value.telegram_username?.trim()) {
    notifyUsersError('Telegram username is required.')
    return
  }

  const telegramNumericId = Number(createUserForm.value.telegram_user_id)
  if (!Number.isInteger(telegramNumericId) || telegramNumericId <= 0) {
    notifyUsersError('Telegram ID must be a valid positive number.')
    return
  }

  if (!createUserForm.value.password || createUserForm.value.password.length < 6) {
    notifyUsersError('Password must be at least 6 characters.')
    return
  }

  if (createUserForm.value.password !== createUserForm.value.password_confirmation) {
    notifyUsersError('Password confirmation does not match.')
    return
  }

  isSubmittingUser.value = true

  try {
    const payload = {
      name: createUserForm.value.name.trim(),
      email: createUserForm.value.email.trim().toLowerCase(),
      telegram_user_id: telegramNumericId,
      telegram_username: createUserForm.value.telegram_username.trim().replace(/^@/, ''),
      password: createUserForm.value.password,
      password_confirmation: createUserForm.value.password_confirmation,
    }

    const { ok } = await runAction(
      () => axios.post('/api/users', payload),
      {
        successMessage: 'User created successfully. Ask user to /start the bot.',
        errorPrefix: 'Failed to create user',
      }
    )

    if (!ok) {
      return
    }

    isCreateUserModalOpen.value = false
    resetCreateUserForm()

    isClientsLoading.value = true
    await mainStore.fetchSampleClients()
  } finally {
    isSubmittingUser.value = false
    isClientsLoading.value = false
  }
}

const openCommandFilterModal = () => {
  isCommandFilterModalOpen.value = true
}

const applyCommandDateFilter = () => {
  if (commandFilterStartDate.value && commandFilterEndDate.value) {
    if (commandFilterStartDate.value > commandFilterEndDate.value) {
      notifyUsersError('Start date must be before end date.')
      return
    }
  }

  isCommandFilterModalOpen.value = false
  commandsRefreshKey.value += 1
}

const clearCommandFilter = () => {
  commandFilterStartDate.value = ''
  commandFilterEndDate.value = ''
  commandsRefreshKey.value += 1
}

const refreshCommands = () => {
  commandsRefreshKey.value += 1
}

const exportUserCommands = async () => {
  const params = {}

  if (commandFilterStartDate.value) {
    params.start_date = commandFilterStartDate.value
  }
  if (commandFilterEndDate.value) {
    params.end_date = commandFilterEndDate.value
  }
  if (commandSearchQuery.value?.trim()) {
    params.search = commandSearchQuery.value.trim()
  }

  await runAction(
    async () => {
      const response = await axios.get('/api/users/commands/export', {
        params,
        responseType: 'blob',
      })

      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `user-commands-${new Date().toISOString().split('T')[0]}.xlsx`)
      document.body.appendChild(link)
      link.click()
      link.parentNode.removeChild(link)
      window.URL.revokeObjectURL(url)
    },
    {
      successMessage: 'User commands exported successfully!',
      errorPrefix: 'Failed to export user commands',
      onError: (error) => {
        console.error('Error exporting user commands:', error)
      },
    }
  )
}

onMounted(async () => {
  isClientsLoading.value = true
  await mainStore.fetchSampleClients()
  isClientsLoading.value = false
})
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccountMultiple" title="Users" main>
        <BaseButton :icon="mdiPlus" color="success" @click="openCreateUserModal" />
      </SectionTitleLineWithButton>

      <NotificationBar color="info" :icon="mdiMonitorCellphone" class="mb-4">
        <b>Users Telegram Only</b>
      </NotificationBar>

      <CardBox has-table>
        <TableSampleClients :is-loading="isClientsLoading" />
      </CardBox>

      <SectionTitleLineWithButton :icon="mdiMonitorCellphone" title="Command List (All Users)" main class="mt-8">
        <div class="flex gap-2">
          <BaseButton :icon="mdiDownload" color="whiteDark" @click="exportUserCommands" />
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openCommandFilterModal" />
          <BaseButton :icon="mdiReload" color="whiteDark" @click="refreshCommands" />
        </div>
      </SectionTitleLineWithButton>

      <CardBox has-table>
        <TableUserCommands
          :date-start="commandFilterStartDate"
          :date-end="commandFilterEndDate"
          :refresh-key="commandsRefreshKey"
          @search-change="commandSearchQuery = $event"
        />
      </CardBox>

      <CardBoxModal
        v-model="isCreateUserModalOpen"
        title="Add New User"
        button="success"
        :button-label="isSubmittingUser ? 'Submitting...' : 'Submit'"
        :has-cancel="true"
        @confirm="submitCreateUser"
        @cancel="isCreateUserModalOpen = false"
      >
        <div class="max-h-[60vh] overflow-y-auto pr-1">
        <FormField label="Full Name" label-for="new-user-name">
          <FormControl
            id="new-user-name"
            v-model="createUserForm.name"
            type="text"
            placeholder="e.g. John Doe"
          />
        </FormField>

        <FormField label="Email" label-for="new-user-email">
          <FormControl
            id="new-user-email"
            v-model="createUserForm.email"
            type="email"
            placeholder="example@email.com"
          />
        </FormField>

        <FormField label="Telegram ID" label-for="new-telegram-id">
          <FormControl
            id="new-telegram-id"
            v-model="createUserForm.telegram_user_id"
            type="number"
            min="1"
            placeholder="e.g. 123456789"
          />
        </FormField>

        <FormField label="Telegram Username" label-for="new-telegram-username">
          <FormControl
            id="new-telegram-username"
            v-model="createUserForm.telegram_username"
            type="text"
            placeholder="@username"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">After account creation, ask the user to send /start to the bot.</p>
        </FormField>

        <FormField label="Password" label-for="new-user-password">
          <FormControl
            id="new-user-password"
            v-model="createUserForm.password"
            type="password"
            placeholder="At least 6 characters"
          />
        </FormField>

        <FormField label="Confirm Password" label-for="new-user-password-confirmation">
          <FormControl
            id="new-user-password-confirmation"
            v-model="createUserForm.password_confirmation"
            type="password"
            placeholder="Repeat password"
          />
        </FormField>
        </div>
      </CardBoxModal>

      <CardBoxModal
        v-model="isCommandFilterModalOpen"
        title="Filter Command List by Date"
        button-label="Apply"
        :has-cancel="true"
        @confirm="applyCommandDateFilter"
        @cancel="isCommandFilterModalOpen = false"
      >
        <FormField label="Start date" label-for="command-filter-start-date">
          <FormControl id="command-filter-start-date" v-model="commandFilterStartDate" type="date" />
        </FormField>
        <FormField label="End date" label-for="command-filter-end-date">
          <FormControl id="command-filter-end-date" v-model="commandFilterEndDate" type="date" />
        </FormField>
        <div class="mt-4">
          <BaseButton label="Clear Filter" color="whiteDark" outline @click="clearCommandFilter" />
        </div>
      </CardBoxModal>

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-if="usersToast.visible"
          class="fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 text-white"
          :class="usersToastClass"
        >
          <BaseIcon :path="usersToastIcon" size="18" />
          <span class="text-sm font-medium">{{ usersToast.message }}</span>
        </div>
      </transition>
    </SectionMain>
  </LayoutAuthenticated>
</template>
