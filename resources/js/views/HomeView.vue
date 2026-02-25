<script setup>
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import { useMainStore } from '@/stores/main'
import {
  mdiCartOutline,
  mdiChartTimelineVariant,
  mdiCog,
  mdiReload,
  mdiWallet,
} from '@mdi/js'
import LineChart from '@/components/Charts/LineChart.vue'
import SectionMain from '@/components/SectionMain.vue'
import CardBoxWidget from '@/components/CardBoxWidget.vue'
import CardBox from '@/components/CardBox.vue'
import BaseButton from '@/components/BaseButton.vue'
import CardBoxTransaction from '@/components/CardBoxTransaction.vue'
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
const isUserReady = ref(false)
const isTransactionsLoading = ref(true)
const isInsightLoading = ref(true)
const recentCommands = ref([])
const recentLogins = ref([])
const activeInsightSlide = ref(0)
const isInsightSwitching = ref(false)
const dashboardSwipeRef = ref(null)
const activeDashboardSlide = ref(0)
const activeOverviewMetric = ref('')
const isOverviewDetailModalOpen = ref(false)

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

const fetchRecentCommands = async () => {
  try {
    const response = await axios.get('/api/dashboard/recent-commands')
    recentCommands.value = response.data?.data || []
  } catch (error) {
    console.error('Failed to load recent commands:', error)
    recentCommands.value = []
  }
}

const fetchRecentLogins = async () => {
  try {
    const response = await axios.get('/api/dashboard/recent-logins')
    recentLogins.value = response.data?.data || []
  } catch (error) {
    console.error('Failed to load recent logins:', error)
    recentLogins.value = []
  }
}

const currentInsightSlide = computed(() => {
  if (isAdminUser.value) {
    return activeInsightSlide.value === 0 ? 'commands' : 'logins'
  }

  return 'logins'
})

const changeInsightSlide = (targetIndex) => {
  if (!isAdminUser.value || activeInsightSlide.value === targetIndex || isInsightSwitching.value) {
    return
  }

  isInsightSwitching.value = true
  setTimeout(() => {
    activeInsightSlide.value = targetIndex
    isInsightSwitching.value = false
  }, 260)
}

const fetchRightPanelInsights = async () => {
  try {
    isInsightLoading.value = true

    if (isAdminUser.value) {
      await Promise.all([fetchRecentCommands(), fetchRecentLogins()])
    } else {
      activeInsightSlide.value = 0
      recentCommands.value = []
      await fetchRecentLogins()
    }
  } finally {
    isInsightLoading.value = false
  }
}

const displayCommandOwner = (item) => {
  const fullName = [item.first_name, item.last_name].filter(Boolean).join(' ').trim()
  if (fullName) {
    return fullName
  }

  if (item.username) {
    return `@${item.username}`
  }

  return item.user_id ? `User ${item.user_id}` : 'Unknown'
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

  await fetchRightPanelInsights()
})

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

const transactionBarItems = computed(() => {
  return mainStore.history.slice(0, 4)
})

const rightInsightItems = computed(() => {
  const items = currentInsightSlide.value === 'commands'
    ? recentCommands.value.slice(0, 4).map((command) => ({
      id: `cmd-${command.id}`,
      amount: command.command || 'Command',
      date: formatShortDate(command.created_at),
      business: displayCommandOwner(command),
      type: 'info',
      name: 'Telegram Command',
      account: 'Command',
    }))
    : recentLogins.value.slice(0, 4).map((item, index) => ({
      id: `login-${index}-${item.email}`,
      amount: 'Login',
      date: formatShortDate(item.created_at),
      business: item.email,
      type: 'info',
      name: `IP: ${item.ip_address || '-'}`,
      account: 'Login',
    }))

  while (items.length < 4) {
    items.push({
      id: `placeholder-${currentInsightSlide.value}-${items.length}`,
      amount: 'N/A',
      date: 'N/A',
      business: 'N/A',
      type: 'info',
      name: 'N/A',
      account: currentInsightSlide.value === 'commands' ? 'Command' : 'Login',
    })
  }

  return items
})

const isAdminUser = computed(() => {
  return mainStore.currentUser?.telegram_user?.level === 1
})

const totalFlowAmount = computed(() => {
  return toNumber(summary.value.total_income) + toNumber(summary.value.total_expense)
})

const selectedOverviewInfo = computed(() => {
  const income = toNumber(summary.value.total_income)
  const expense = toNumber(summary.value.total_expense)
  const balance = toNumber(summary.value.balance)
  const totalFlow = totalFlowAmount.value

  if (activeOverviewMetric.value === 'income') {
    const percentage = totalFlow > 0 ? Math.round((income / totalFlow) * 100) : 0
    return {
      title: 'Income details',
      value: income,
      description: `Income contributes ${percentage}% of total transaction flow in this period.`,
    }
  }

  if (activeOverviewMetric.value === 'expense') {
    const percentage = totalFlow > 0 ? Math.round((expense / totalFlow) * 100) : 0
    return {
      title: 'Expense details',
      value: expense,
      description: `Expense contributes ${percentage}% of total transaction flow in this period.`,
    }
  }

  return {
    title: 'Balance details',
    value: balance,
    description:
      balance >= 0
        ? 'Current balance is positive for the selected period.'
        : 'Current balance is negative for the selected period.',
  }
})

const selectedOverviewRows = computed(() => {
  const income = toNumber(summary.value.total_income)
  const expense = toNumber(summary.value.total_expense)
  const balance = toNumber(summary.value.balance)
  const totalTransactions = toNumber(summary.value.total_transactions)
  const totalFlow = totalFlowAmount.value

  if (activeOverviewMetric.value === 'income') {
    return [
      { label: 'Income', value: `Rp ${income.toLocaleString('en-US')}` },
      { label: 'Expense', value: `Rp ${expense.toLocaleString('en-US')}` },
      {
        label: 'Contribution',
        value: `${totalFlow > 0 ? Math.round((income / totalFlow) * 100) : 0}% of total flow`,
      },
      { label: 'Total transactions', value: totalTransactions.toLocaleString('en-US') },
      { label: 'Period', value: displayPeriod.value },
    ]
  }

  if (activeOverviewMetric.value === 'expense') {
    return [
      { label: 'Expense', value: `Rp ${expense.toLocaleString('en-US')}` },
      { label: 'Income', value: `Rp ${income.toLocaleString('en-US')}` },
      {
        label: 'Contribution',
        value: `${totalFlow > 0 ? Math.round((expense / totalFlow) * 100) : 0}% of total flow`,
      },
      { label: 'Total transactions', value: totalTransactions.toLocaleString('en-US') },
      { label: 'Period', value: displayPeriod.value },
    ]
  }

  return [
    { label: 'Balance', value: `Rp ${balance.toLocaleString('en-US')}` },
    { label: 'Income', value: `Rp ${income.toLocaleString('en-US')}` },
    { label: 'Expense', value: `Rp ${expense.toLocaleString('en-US')}` },
    { label: 'Total transactions', value: totalTransactions.toLocaleString('en-US') },
    { label: 'Period', value: displayPeriod.value },
  ]
})

const openOverviewDetail = (metric) => {
  activeOverviewMetric.value = metric
  isOverviewDetailModalOpen.value = true
}

const goToDashboardSlide = (index) => {
  const container = dashboardSwipeRef.value
  if (!container) {
    return
  }

  const width = container.clientWidth
  container.scrollTo({
    left: width * index,
    behavior: 'smooth',
  })
}

const handleDashboardScroll = () => {
  const container = dashboardSwipeRef.value
  if (!container || container.clientWidth === 0) {
    return
  }

  activeDashboardSlide.value = Math.round(container.scrollLeft / container.clientWidth)
}
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
        <button
          type="button"
          class="w-full p-0 bg-transparent border-0 appearance-none text-left rounded-sm transition ring-offset-2 focus:outline-none"
          :class="activeOverviewMetric === 'income' ? 'ring-2 ring-emerald-500' : ''"
          @click="openOverviewDetail('income')"
        >
          <CardBoxWidget
            color="text-emerald-500"
            :icon="mdiChartTimelineVariant"
            :number="toNumber(summary.total_income)"
            prefix="Rp"
            label="Income"
          />
        </button>
        <button
          type="button"
          class="w-full p-0 bg-transparent border-0 appearance-none text-left rounded-sm transition ring-offset-2 focus:outline-none"
          :class="activeOverviewMetric === 'expense' ? 'ring-2 ring-red-500' : ''"
          @click="openOverviewDetail('expense')"
        >
          <CardBoxWidget
            color="text-red-500"
            :icon="mdiCartOutline"
            :number="toNumber(summary.total_expense)"
            prefix="Rp"
            label="Expense"
          />
        </button>
        <button
          type="button"
          class="w-full p-0 bg-transparent border-0 appearance-none text-left rounded-sm transition ring-offset-2 focus:outline-none"
          :class="activeOverviewMetric === 'balance' ? 'ring-2 ring-blue-500' : ''"
          @click="openOverviewDetail('balance')"
        >
          <CardBoxWidget
            color="text-blue-500"
            :icon="mdiWallet"
            :number="toNumber(summary.balance)"
            prefix="Rp"
            label="Balance"
          />
        </button>
      </div>

      <div class="mb-8">
        <div
          ref="dashboardSwipeRef"
          class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-smooth"
          @scroll="handleDashboardScroll"
        >
          <div class="min-w-full snap-start">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <div class="flex flex-col gap-4">
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
              <div class="relative flex flex-col gap-4 pb-6">
                <div v-if="isInsightLoading || !isUserReady" class="space-y-4 animate-pulse">
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
                    v-for="item in rightInsightItems"
                    :key="item.id"
                    :amount="item.amount"
                    :date="item.date"
                    :business="item.business"
                    :type="item.type"
                    :name="item.name"
                    :account="item.account"
                    class="transition-opacity duration-300"
                    :class="isInsightSwitching ? 'opacity-30' : 'opacity-100'"
                  />

                  <div v-if="isAdminUser" class="absolute bottom-0 left-1/2 -translate-x-1/2">
                    <div class="flex items-center gap-2">
                      <button
                        type="button"
                        class="h-2.5 w-2.5 rounded-full transition"
                        :class="activeInsightSlide === 0 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-slate-600'"
                        @click="changeInsightSlide(0)"
                      ></button>
                      <button
                        type="button"
                        class="h-2.5 w-2.5 rounded-full transition"
                        :class="activeInsightSlide === 1 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-slate-600'"
                        @click="changeInsightSlide(1)"
                      ></button>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <div class="min-w-full snap-start">
            <CardBox>
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Trends overview</h3>
                <BaseButton
                  :icon="mdiReload"
                  color="whiteDark"
                  @click="fetchChartData(buildDateParams())"
                />
              </div>

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
          </div>
        </div>

        <div class="flex items-center justify-center gap-2 mt-4">
          <button
            type="button"
            class="h-2.5 w-2.5 rounded-full transition"
            :class="activeDashboardSlide === 0 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-slate-600'"
            @click="goToDashboardSlide(0)"
          ></button>
          <button
            type="button"
            class="h-2.5 w-2.5 rounded-full transition"
            :class="activeDashboardSlide === 1 ? 'bg-blue-600' : 'bg-gray-300 dark:bg-slate-600'"
            @click="goToDashboardSlide(1)"
          ></button>
        </div>
      </div>

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
        v-model="isOverviewDetailModalOpen"
        :title="selectedOverviewInfo.title"
        button-label="Close"
        @confirm="isOverviewDetailModalOpen = false"
      >
        <div class="space-y-4">
          <p class="text-xl font-bold">
            Rp {{ toNumber(selectedOverviewInfo.value).toLocaleString('en-US') }}
          </p>
          <p class="text-sm text-gray-500 dark:text-slate-400">
            {{ selectedOverviewInfo.description }}
          </p>
          <div class="space-y-2 border-t border-gray-200 dark:border-slate-700 pt-3">
            <div
              v-for="row in selectedOverviewRows"
              :key="row.label"
              class="flex items-center justify-between text-sm"
            >
              <span class="text-gray-500 dark:text-slate-400">{{ row.label }}</span>
              <span class="font-medium">{{ row.value }}</span>
            </div>
          </div>
        </div>
      </CardBoxModal>

    </SectionMain>
  </LayoutAuthenticated>
</template>
