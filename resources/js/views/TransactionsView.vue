<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { mdiCartOutline, mdiCog, mdiDownload } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import CardBox from '@/components/CardBox.vue'
import TableTransactions from '@/components/TableTransactions.vue'
import BaseButton from '@/components/BaseButton.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'

const isTransactionFilterModalOpen = ref(false)
const transactionFilterStartDate = ref('')
const transactionFilterEndDate = ref('')

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
      responseType: 'blob',
    })

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
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiCartOutline" title="Transactions" main>
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
