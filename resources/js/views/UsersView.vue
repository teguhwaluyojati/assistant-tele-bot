<script setup>
import { onMounted, ref } from 'vue'
import { mdiAccountMultiple, mdiMonitorCellphone } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import NotificationBar from '@/components/NotificationBar.vue'
import CardBox from '@/components/CardBox.vue'
import TableSampleClients from '@/components/TableSampleClients.vue'
import { useMainStore } from '@/stores/main'

const mainStore = useMainStore()
const isClientsLoading = ref(false)

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
    </SectionMain>
  </LayoutAuthenticated>
</template>
