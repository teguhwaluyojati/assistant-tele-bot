<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { mdiCartOutline, mdiCog, mdiDownload, mdiPlus, mdiReload, mdiCheckCircle, mdiAlertCircle, mdiInformation } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import CardBox from '@/components/CardBox.vue'
import TableTransactions from '@/components/TableTransactions.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'
import { useActionToast } from '@/composables/useActionToast'

const isTransactionFilterModalOpen = ref(false)
const transactionFilterStartDate = ref('')
const transactionFilterEndDate = ref('')
const transactionsTableKey = ref(0)

const isCreateTransactionModalOpen = ref(false)

const getCurrentLocalDateTime = () => {
  const now = new Date()
  const timezoneOffset = now.getTimezoneOffset() * 60000
  const localISO = new Date(now.getTime() - timezoneOffset).toISOString()
  return localISO.slice(0, 16)
}

const createTransactionForm = ref({
  type: 'expense',
  amount: '',
  transaction_date: getCurrentLocalDateTime(),
  description: '',
  category: '',
})
const isSubmittingTransaction = ref(false)

const categoryOptionsByType = {
  expense: [
    'Food & Drink',
    'Transport',
    'Bills & Utilities',
    'Shopping',
    'Health',
    'Education',
    'Entertainment',
  ],
  income: [
    'Salary',
    'Bonus',
    'Business',
    'Investment',
    'Gift',
  ],
}

const createCategoryOptions = computed(() => {
  const options = categoryOptionsByType[createTransactionForm.value.type] ?? []

  return [
    { value: '', label: 'Auto (based on description)' },
    ...options.map((option) => ({ value: option, label: option })),
  ]
})

const { toast: transactionToast, success: notifyTransactionSuccess, error: notifyTransactionError, runAction } = useActionToast(2600)

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

const refreshTransactionsTable = () => {
  transactionsTableKey.value += 1
}

const openCreateTransactionModal = () => {
  isCreateTransactionModalOpen.value = true
}

const resetCreateTransactionForm = () => {
  createTransactionForm.value = {
    type: 'expense',
    amount: '',
    transaction_date: getCurrentLocalDateTime(),
    description: '',
    category: '',
  }
}

const submitTransaction = async () => {
  if (!createTransactionForm.value.amount || Number(createTransactionForm.value.amount) <= 0) {
    notifyTransactionError('Amount must be greater than 0.')
    return
  }

  isSubmittingTransaction.value = true
  try {
    const normalizedDescription = createTransactionForm.value.description?.trim() || ''
    const normalizedCategory = createTransactionForm.value.category?.trim() || null

    const { ok } = await runAction(
      () => axios.post('/api/transactions', {
        type: createTransactionForm.value.type,
        amount: Number(createTransactionForm.value.amount),
        transaction_date: createTransactionForm.value.transaction_date,
        description: normalizedDescription,
        category: normalizedCategory,
      }),
      {
        successMessage: 'Transaction created successfully!',
        errorPrefix: 'Failed to create transaction',
      }
    )

    if (!ok) {
      return
    }

    isCreateTransactionModalOpen.value = false
    resetCreateTransactionForm()
    transactionsTableKey.value += 1
  } finally {
    isSubmittingTransaction.value = false
  }
}

const showTransactionToastClass = computed(() => {
  if (transactionToast.value.type === 'success') {
    return 'bg-emerald-500'
  }

  if (transactionToast.value.type === 'info') {
    return 'bg-blue-500'
  }

  return 'bg-red-500'
})

const showTransactionToastIcon = computed(() => {
  if (transactionToast.value.type === 'success') {
    return mdiCheckCircle
  }

  if (transactionToast.value.type === 'info') {
    return mdiInformation
  }

  return mdiAlertCircle
})

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
    notifyTransactionSuccess('Transactions exported successfully!')
  } catch (error) {
    console.error('Error exporting transactions:', error)
    notifyTransactionError('Failed to export transactions.')
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
          <BaseButton :icon="mdiReload" color="whiteDark" @click="refreshTransactionsTable" />
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

        <FormField label="Category (optional)">
          <FormControl
            v-model="createTransactionForm.category"
            :options="createCategoryOptions"
          />
        </FormField>

        <FormField label="Description">
          <FormControl
            v-model="createTransactionForm.description"
            type="textarea"
            placeholder="Enter description (optional)"
          />
        </FormField>

        <FormField label="Date & Time">
          <FormControl
            v-model="createTransactionForm.transaction_date"
            type="datetime-local"
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

      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-if="transactionToast.visible"
          class="fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 text-white"
          :class="showTransactionToastClass"
        >
          <BaseIcon :path="showTransactionToastIcon" size="18" />
          <span class="text-sm font-medium">{{ transactionToast.message }}</span>
        </div>
      </transition>
    </SectionMain>
  </LayoutAuthenticated>
</template>
