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

onMounted(() => {
  const params = buildDateParams()
  fetchSummary(params)
  fetchChartData(params)
})

const mainStore = useMainStore()

const clientBarItems = computed(() => mainStore.clients.slice(0, 4))

const transactionBarItems = computed(() => mainStore.history)
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiChartTimelineVariant" title="Overview" main>
        <div class="flex items-center gap-3">
          <span v-if="summary.period" class="text-sm text-gray-500">
            Period: {{ summary.period }}
          </span>
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openFilterModal" />
        </div>
      </SectionTitleLineWithButton>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
        <CardBoxWidget
          color="text-emerald-500"
          :icon="mdiChartTimelineVariant"
          :number="summary.total_income"
          prefix="Rp"
          label="Income"
        />
        <CardBoxWidget
          color="text-red-500"
          :icon="mdiCartOutline"
          :number="summary.total_expense"
          prefix="Rp"
          label="Expense"
        />
        <CardBoxWidget
          color="text-blue-500"
          :icon="mdiAccountMultiple"
          :number="summary.balance"
          prefix="Rp"
          label="Balance"
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="flex flex-col justify-between">
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
        </div>
        <div class="flex flex-col justify-between">
          <CardBoxClient
            v-for="client in clientBarItems"
            :key="client.id"
            :name="client.name"
            :login="client.login"
            :date="client.created"
            :progress="client.progress"
          />
        </div>
      </div>


      <SectionTitleLineWithButton :icon="mdiChartPie" title="Trends overview">
        <BaseButton
          :icon="mdiReload"
          color="whiteDark"
          @click="fetchChartData(buildDateParams())"
        />
      </SectionTitleLineWithButton>

      <CardBox class="mb-6">
        <div v-if="chartData">
          <line-chart :data="chartData" class="h-96" />
        </div>
      </CardBox>

      <SectionTitleLineWithButton :icon="mdiAccountMultiple" title="Clients" />

      <NotificationBar color="info" :icon="mdiMonitorCellphone">
        <b>Responsive table.</b> Collapses on mobile
      </NotificationBar>

      <CardBox has-table>
        <TableSampleClients />
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
    </SectionMain>
  </LayoutAuthenticated>
</template>
