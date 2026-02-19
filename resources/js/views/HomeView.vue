<script setup>
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import { useMainStore } from '@/stores/main'
import {
  mdiAccountMultiple,
  mdiCartOutline,
  mdiChartTimelineVariant,
  mdiCog,
  mdiMonitorCellphone,
  mdiReload,
  mdiChartPie,
  mdiWallet,
  mdiDownload,
} from '@mdi/js'
import LineChart from '@/components/Charts/LineChart.vue'
import SectionMain from '@/components/SectionMain.vue'
import CardBoxWidget from '@/components/CardBoxWidget.vue'
import CardBox from '@/components/CardBox.vue'
import TableSampleClients from '@/components/TableSampleClients.vue'
import NotificationBar from '@/components/NotificationBar.vue'
import BaseButton from '@/components/BaseButton.vue'
import CardBoxTransaction from '@/components/CardBoxTransaction.vue'
import CardBoxClient from '@/components/CardBoxClient.vue'
import TableTransactions from '@/components/TableTransactions.vue'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'

const chartData = ref(null)
const summary = ref({
  total_income: 0,
  total_expense: 0,
  balance: 0,
  total_transactions: 0,
  period: '',
})
const isFilterModalOpen = ref(false)
const filterStartDate = ref('')
const filterEndDate = ref('')
const activeDateFilter = ref({
  start_date: '',
  end_date: '',
})
const isTransactionFilterModalOpen = ref(false)
const transactionFilterStartDate = ref('')
const transactionFilterEndDate = ref('')
const isUserReady = ref(false)
const isClientsLoading = ref(false)
const isTransactionsLoading = ref(true)

const buildDateParams = () => {
  const params = {}
  if (activeDateFilter.value.start_date) {
    params.start_date = activeDateFilter.value.start_date
  }
  if (activeDateFilter.value.end_date) {
    params.end_date = activeDateFilter.value.end_date
  }
  return params
}

const toNumber = (value) => {
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : 0
}

const fetchSummary = async (params = {}) => {
  try {
    const response = await axios.get('/api/transactions/summary', { params })
    summary.value = response.data?.data || summary.value
  } catch (error) {
    console.error('Failed to load summary:', error)
  }
}

const fetchChartData = async (params = {}) => {
  try {
    const response = await axios.get('/api/transactions/daily-chart', { params })
    chartData.value = response.data?.data || null
  } catch (error) {
    console.error('Failed to load chart data:', error)
  }
}

const openFilterModal = () => {
  filterStartDate.value = activeDateFilter.value.start_date
  filterEndDate.value = activeDateFilter.value.end_date
  isFilterModalOpen.value = true
}

const applyDateFilter = () => {
  if (filterStartDate.value && filterEndDate.value) {
    if (filterStartDate.value > filterEndDate.value) {
      console.error('Start date must be before end date')
      return
    }
  }

  activeDateFilter.value = {
    start_date: filterStartDate.value || '',
    end_date: filterEndDate.value || '',
  }

  const params = buildDateParams()
  fetchSummary(params)
  fetchChartData(params)
  isFilterModalOpen.value = false
}

const openTransactionFilterModal = () => {
  isTransactionFilterModalOpen.value = true
}

const applyTransactionDateFilter = () => {
  if (transactionFilterStartDate.value && transactionFilterEndDate.value) {
    if (transactionFilterStartDate.value > transactionFilterEndDate.value) {
      console.error('Start date must be before end date')
      return
    }
  }
  isTransactionFilterModalOpen.value = false
}

const clearTransactionFilter = () => {
  transactionFilterStartDate.value = ''
  transactionFilterEndDate.value = ''
}

const exportTransactions = async () => {
  try {
    const params = {}
    if (transactionFilterStartDate.value) {
      params.start_date = transactionFilterStartDate.value
    }
    if (transactionFilterEndDate.value) {
      params.end_date = transactionFilterEndDate.value
    }

    const response = await axios.get('/api/transactions/export', {
      params,
      responseType: 'blob'
    })

    // Create blob link to download
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `transactions-${new Date().toISOString().split('T')[0]}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.parentNode.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting transactions:', error)
  }
}

const mainStore = useMainStore()

onMounted(async () => {
  const params = buildDateParams()
  fetchSummary(params)
  fetchChartData(params)
  
  // Fetch current user first to determine admin status
  await mainStore.fetchCurrentUser()
  isUserReady.value = true
  
  isTransactionsLoading.value = true
  await mainStore.fetchTransactionsFromApi()
  isTransactionsLoading.value = false
  
  // Fetch clients only if user is admin
  if (mainStore.currentUser?.telegram_user?.level === 1) {
    isClientsLoading.value = true
    await mainStore.fetchSampleClients()
    isClientsLoading.value = false
  }
})

const displayClientName = (client) => {
  const fullName = [client.first_name, client.last_name].filter(Boolean).join(' ').trim()
  return fullName || client.username || (client.user_id ? `User ${client.user_id}` : 'Unknown')
}

const formatShortDate = (value) => {
  if (!value) {
    return '-'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const displayPeriod = computed(() => summary.value.period || 'N/A')

const fallbackClients = Array.from({ length: 4 }, (_, index) => ({
  id: `placeholder-${index}`,
  name: 'N/A',
  login: 'N/A',
  date: 'N/A',
  type: 'info',
  text: 'N/A',
}))

const clientBarItems = computed(() => {
  if (!isAdminUser.value) {
    return []
  }

  if (!mainStore.clients.length) {
    return fallbackClients
  }

  return mainStore.clients.slice(0, 4).map((client) => {
    const levelLabel = client.level === 1 ? 'Admin' : 'Member'
    const levelType = client.level === 1 ? 'success' : 'info'
    return {
      id: client.id,
      name: displayClientName(client),
      login: client.username || '-',
      date: formatShortDate(client.last_interaction_at),
      type: levelType,
      text: levelLabel,
    }
  })
})

const transactionBarItems = computed(() => {
  return mainStore.history.slice(0, 4)
})

const isAdminUser = computed(() => {
  return mainStore.currentUser?.telegram_user?.level === 1
})
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiChartTimelineVariant" title="Overview" main>
        <div class="flex items-center gap-4">
          <span class="text-base text-gray-500 font-medium">
            Period: {{ displayPeriod }}
          </span>
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openFilterModal" />
        </div>
      </SectionTitleLineWithButton>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3 mb-8">
        <CardBoxWidget
          color="text-emerald-500"
          :icon="mdiChartTimelineVariant"
          :number="toNumber(summary.total_income)"
          prefix="Rp"
          label="Income"
        />
        <CardBoxWidget
          color="text-red-500"
          :icon="mdiCartOutline"
          :number="toNumber(summary.total_expense)"
          prefix="Rp"
          label="Expense"
        />
        <CardBoxWidget
          color="text-blue-500"
          :icon="mdiWallet"
          :number="toNumber(summary.balance)"
          prefix="Rp"
          label="Balance"
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="flex flex-col justify-between gap-4">
          <div v-if="isTransactionsLoading" class="space-y-4 animate-pulse">
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 w-3/4 rounded bg-gray-200 dark:bg-slate-700"></div>
                <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-slate-700"></div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-slate-700"></div>
                <div class="h-3 w-1/3 rounded bg-gray-200 dark:bg-slate-700"></div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 w-4/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                <div class="h-3 w-2/5 rounded bg-gray-200 dark:bg-slate-700"></div>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 w-3/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                <div class="h-3 w-2/6 rounded bg-gray-200 dark:bg-slate-700"></div>
              </div>
            </div>
          </div>
          <template v-else>
            <CardBoxTransaction
              v-for="(transaction, index) in transactionBarItems"
              :key="index"
              :amount="transaction.amount"
              :date="transaction.date"
              :business="transaction.business"
              :type="transaction.type"
              :name="transaction.name"
              :account="transaction.account"
            />
            <CardBox v-if="!transactionBarItems.length" class="flex-1">
              <div class="space-y-2">
                <h3 class="text-lg font-semibold">No transactions yet</h3>
                <p class="text-sm text-gray-500 dark:text-slate-400">
                  New activity will appear here once transactions are recorded.
                </p>
              </div>
            </CardBox>
          </template>
        </div>
        <div class="flex flex-col justify-between gap-4">
          <template v-if="isUserReady && isAdminUser">
            <div v-if="isClientsLoading" class="space-y-4 animate-pulse">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-3/4 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-1/3 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-4/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-2/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-3/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-2/6 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
            </div>
            <CardBoxClient
              v-else
              v-for="client in clientBarItems"
              :key="client.id"
              :name="client.name"
              :login="client.login"
              :date="client.date"
              :type="client.type"
              :text="client.text"
            />
          </template>
          <CardBox v-else-if="isUserReady" class="flex-1">
            <div class="space-y-2">
              <h3 class="text-lg font-semibold">Need help?</h3>
              <p class="text-sm text-gray-500 dark:text-slate-400">
                This area is for admin insights. You can still track your transactions on the left
                panel and the table below.
              </p>
            </div>
          </CardBox>
          <CardBox v-else class="flex-1">
            <div class="space-y-4 animate-pulse">
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-3/4 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-1/2 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-1/3 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-4/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-2/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-gray-200 dark:bg-slate-700"></div>
                <div class="flex-1 space-y-2">
                  <div class="h-4 w-3/5 rounded bg-gray-200 dark:bg-slate-700"></div>
                  <div class="h-3 w-2/6 rounded bg-gray-200 dark:bg-slate-700"></div>
                </div>
              </div>
            </div>
          </CardBox>
        </div>
      </div>


      <SectionTitleLineWithButton :icon="mdiChartPie" title="Trends overview">
        <BaseButton
          :icon="mdiReload"
          color="whiteDark"
          @click="fetchChartData(buildDateParams())"
        />
      </SectionTitleLineWithButton>

      <CardBox class="mb-8">
        <div v-if="chartData">
          <line-chart :data="chartData" class="h-[500px]" />
        </div>
        <div
          v-else
          class="h-[500px] flex items-center justify-center text-gray-500 dark:text-slate-400"
        >
          N/A
        </div>
      </CardBox>

      <SectionTitleLineWithButton v-if="isAdminUser" :icon="mdiAccountMultiple" title="Clients" />

      <NotificationBar v-if="isAdminUser" color="info" :icon="mdiMonitorCellphone" class="mb-4">
        <b>Users Telegram Only</b>
      </NotificationBar>

      <CardBox v-if="isAdminUser" has-table class="mb-8">
        <TableSampleClients />
      </CardBox>

      <SectionTitleLineWithButton :icon="mdiCartOutline" title="All Transactions">
        <div class="flex gap-2">
          <BaseButton :icon="mdiDownload" color="whiteDark" @click="exportTransactions" />
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openTransactionFilterModal" />
        </div>
      </SectionTitleLineWithButton>

      <CardBox has-table>
        <TableTransactions 
          :date-start="transactionFilterStartDate" 
          :date-end="transactionFilterEndDate"
        />
      </CardBox>
      <CardBoxModal
        v-model="isFilterModalOpen"
        title="Filter Period"
        button-label="Apply"
        :has-cancel="true"
        @confirm="applyDateFilter"
        @cancel="isFilterModalOpen = false"
      >
        <FormField label="Start date" label-for="filter-start-date">
          <FormControl
            id="filter-start-date"
            v-model="filterStartDate"
            type="date"
          />
        </FormField>
        <FormField label="End date" label-for="filter-end-date">
          <FormControl
            id="filter-end-date"
            v-model="filterEndDate"
            type="date"
          />
        </FormField>
      </CardBoxModal>

      <CardBoxModal
        v-model="isTransactionFilterModalOpen"
        title="Filter Transactions by Date"
        button-label="Apply"
        :has-cancel="true"
        @confirm="applyTransactionDateFilter"
        @cancel="isTransactionFilterModalOpen = false"
      >
        <FormField label="Start date" label-for="transaction-filter-start-date">
          <FormControl
            id="transaction-filter-start-date"
            v-model="transactionFilterStartDate"
            type="date"
          />
        </FormField>
        <FormField label="End date" label-for="transaction-filter-end-date">
          <FormControl
            id="transaction-filter-end-date"
            v-model="transactionFilterEndDate"
            type="date"
          />
        </FormField>
        <div class="mt-4">
          <BaseButton
            label="Clear Filter"
            color="whiteDark"
            outline
            @click="clearTransactionFilter"
          />
        </div>
      </CardBoxModal>
    </SectionMain>
  </LayoutAuthenticated>
</template>
