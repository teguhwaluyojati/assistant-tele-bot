<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import { mdiAccountMultiple, mdiMonitorCellphone, mdiCog, mdiReload, mdiDownload } from '@mdi/js'
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
import { useMainStore } from '@/stores/main'

const mainStore = useMainStore()
const isClientsLoading = ref(false)
const isCommandFilterModalOpen = ref(false)
const commandFilterStartDate = ref('')
const commandFilterEndDate = ref('')
const commandsRefreshKey = ref(0)
const commandSearchQuery = ref('')

const openCommandFilterModal = () => {
  isCommandFilterModalOpen.value = true
}

const applyCommandDateFilter = () => {
  if (commandFilterStartDate.value && commandFilterEndDate.value) {
    if (commandFilterStartDate.value > commandFilterEndDate.value) {
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
  try {
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
  } catch (error) {
    console.error('Error exporting user commands:', error)
  }
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
      <SectionTitleLineWithButton :icon="mdiAccountMultiple" title="Users" main />

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
    </SectionMain>
  </LayoutAuthenticated>
</template>
