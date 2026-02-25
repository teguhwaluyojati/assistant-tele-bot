<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { mdiCartOutline, mdiCog, mdiDownload, mdiPlus } from '@mdi/js'
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
const transactionsTableKey = ref(0)

const isCreateTransactionModalOpen = ref(false)
const createTransactionForm = ref({
  type: 'expense',
  amount: '',
  description: '',
})
const isSubmittingTransaction = ref(false)

const isMessageModalOpen = ref(false)
const messageModalTitle = ref('')
const messageModalContent = ref('')
const messageModalType = ref('success')

const formattedAmount = computed({
  get: () => {
    const raw = createTransactionForm.value.amount
    if (!raw) {
      return ''
    }

    const numeric = Number(raw)
    if (!Number.isFinite(numeric)) {
      return ''
    }

    return `Rp ${numeric.toLocaleString('en-US')}`
  },
  set: (value) => {
    const digitsOnly = String(value ?? '').replace(/\D/g, '')
    createTransactionForm.value.amount = digitsOnly
  },
})

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

const openCreateTransactionModal = () => {
  isCreateTransactionModalOpen.value = true
}

const resetCreateTransactionForm = () => {
  createTransactionForm.value = {
    type: 'expense',
    amount: '',
    description: '',
  }
}

const submitTransaction = async () => {
  if (!createTransactionForm.value.amount || Number(createTransactionForm.value.amount) <= 0) {
    messageModalTitle.value = 'Error'
    messageModalContent.value = 'Amount must be greater than 0.'
    messageModalType.value = 'danger'
    isMessageModalOpen.value = true
    return
  }

  if (!createTransactionForm.value.description?.trim()) {
    messageModalTitle.value = 'Error'
    messageModalContent.value = 'Description is required.'
    messageModalType.value = 'danger'
    isMessageModalOpen.value = true
    return
  }

  isSubmittingTransaction.value = true
  try {
    await axios.post('/api/transactions', {
      type: createTransactionForm.value.type,
      amount: Number(createTransactionForm.value.amount),
      description: createTransactionForm.value.description.trim(),
    })

    isCreateTransactionModalOpen.value = false
    resetCreateTransactionForm()
    transactionsTableKey.value += 1

    messageModalTitle.value = 'Success'
    messageModalContent.value = 'Transaction created successfully!'
    messageModalType.value = 'success'
    isMessageModalOpen.value = true
  } catch (error) {
    const errorMsg = error.response?.data?.message || error.response?.statusText || error.message
    messageModalTitle.value = 'Error'
    messageModalContent.value = `Failed to create transaction: ${errorMsg}`
    messageModalType.value = 'danger'
    isMessageModalOpen.value = true
  } finally {
    isSubmittingTransaction.value = false
  }
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
          <BaseButton :icon="mdiPlus" color="success" @click="openCreateTransactionModal" />
          <BaseButton :icon="mdiDownload" color="whiteDark" @click="exportTransactions" />
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openTransactionFilterModal" />
        </div>
      </SectionTitleLineWithButton>

      <CardBox has-table>
        <TableTransactions
          :key="transactionsTableKey"
          :date-start="transactionFilterStartDate"
          :date-end="transactionFilterEndDate"
        />
      </CardBox>

      <CardBoxModal
        v-model="isCreateTransactionModalOpen"
        title="Create Transaction"
        button="success"
        :button-label="isSubmittingTransaction ? 'Submitting...' : 'Submit'"
        :has-cancel="true"
        @confirm="submitTransaction"
        @cancel="isCreateTransactionModalOpen = false"
      >
        <FormField label="Type">
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="createTransactionForm.type"
                type="radio"
                value="income"
                class="w-4 h-4 text-green-600 focus:ring-green-500"
              />
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Income</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="createTransactionForm.type"
                type="radio"
                value="expense"
                class="w-4 h-4 text-red-600 focus:ring-red-500"
              />
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Expense</span>
            </label>
          </div>
        </FormField>

        <FormField label="Amount">
          <FormControl
            v-model="formattedAmount"
            type="text"
            inputmode="numeric"
            placeholder="e.g. Rp 1,000,000"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Nominal dalam IDR</p>
        </FormField>

        <FormField label="Description">
          <FormControl
            v-model="createTransactionForm.description"
            type="textarea"
            placeholder="Enter description"
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

      <CardBoxModal
        v-model="isMessageModalOpen"
        :title="messageModalTitle"
        :button="messageModalType"
        button-label="OK"
        @confirm="isMessageModalOpen = false"
      >
        <p>{{ messageModalContent }}</p>
      </CardBoxModal>
    </SectionMain>
  </LayoutAuthenticated>
</template>
