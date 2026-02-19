<script setup>
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import { mdiEye, mdiTrashCan, mdiArrowUp, mdiArrowDown } from '@mdi/js'
import CardBoxModal from '@/components/CardBoxModal.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const props = defineProps({
  dateStart: {
    type: String,
    default: '',
  },
  dateEnd: {
    type: String,
    default: '',
  },
})

const transactions = ref([])
const searchQuery = ref('')
const typeFilter = ref('all') // 'all', 'income', 'expense'
const sortField = ref('created_at')
const sortDirection = ref('desc')
const isLoading = ref(true)

const perPage = ref(10)
const currentPage = ref(0)

const isDetailModalActive = ref(false)
const selectedTransaction = ref(null)

const isDeleteConfirmActive = ref(false)
const transactionToDelete = ref(null)

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value)
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

const getTransactionTypeLabel = (type) => {
  return type === 'income' ? 'Income' : 'Expense'
}

const getTransactionTypeColor = (type) => {
  return type === 'income' ? 'success' : 'warning'
}

const getFullName = (user) => {
  if (!user) return 'Unknown'
  const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ').trim()
  return fullName || user.username || `User ${user.user_id || 'N/A'}`
}

const fetchTransactions = async () => {
  isLoading.value = true
  try {
    const token = localStorage.getItem('auth_token')
    if (token && !axios.defaults.headers.common['Authorization']) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }

    const response = await axios.get('/api/transactions?per_page=100')
    if (response.data.success || response.data.data) {
      const data = response.data.data.data || response.data.data || []
      transactions.value = Array.isArray(data) ? data : []
    }
  } catch (error) {
    console.error('Error fetching transactions:', error)
    transactions.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchTransactions()
})

const filteredAndSortedItems = computed(() => {
  let filtered = transactions.value

  // Type filter
  if (typeFilter.value !== 'all') {
    filtered = filtered.filter((transaction) => transaction.type === typeFilter.value)
  }

  // Date range filter
  if (props.dateStart || props.dateEnd) {
    filtered = filtered.filter((transaction) => {
      const transactionDate = new Date(transaction.created_at)
      if (Number.isNaN(transactionDate.getTime())) return false

      if (props.dateStart) {
        const startDate = new Date(props.dateStart)
        startDate.setHours(0, 0, 0, 0)
        if (transactionDate < startDate) return false
      }

      if (props.dateEnd) {
        const endDate = new Date(props.dateEnd)
        endDate.setHours(23, 59, 59, 999)
        if (transactionDate > endDate) return false
      }

      return true
    })
  }

  // Search filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim()
    filtered = filtered.filter((transaction) => {
      const description = (transaction.description || '').toLowerCase()
      const fullName = getFullName(transaction.user).toLowerCase()
      const amount = String(transaction.amount || '').toLowerCase()
      
      return (
        description.includes(query) ||
        fullName.includes(query) ||
        amount.includes(query)
      )
    })
  }

  // Sort
  const sorted = [...filtered].sort((a, b) => {
    let aVal, bVal

    switch (sortField.value) {
      case 'created_at':
        aVal = new Date(a.created_at || 0).getTime()
        bVal = new Date(b.created_at || 0).getTime()
        break
      case 'amount':
        aVal = a.amount || 0
        bVal = b.amount || 0
        break
      case 'type':
        aVal = a.type || ''
        bVal = b.type || ''
        break
      case 'user':
        aVal = getFullName(a.user).toLowerCase()
        bVal = getFullName(b.user).toLowerCase()
        break
      default:
        return 0
    }

    if (aVal < bVal) return sortDirection.value === 'asc' ? -1 : 1
    if (aVal > bVal) return sortDirection.value === 'asc' ? 1 : -1
    return 0
  })

  return sorted
})

const handleSort = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const itemsPaginated = computed(() =>
  filteredAndSortedItems.value.slice(
    perPage.value * currentPage.value,
    perPage.value * (currentPage.value + 1)
  ),
)

const numPages = computed(() => Math.ceil(filteredAndSortedItems.value.length / perPage.value))

const currentPageHuman = computed(() => currentPage.value + 1)

const pagesList = computed(() => {
  const pagesList = []
  for (let i = 0; i < numPages.value; i++) {
    pagesList.push(i)
  }
  return pagesList
})

const viewTransactionDetail = (transaction) => {
  selectedTransaction.value = transaction
  isDetailModalActive.value = true
}

const canDeleteTransaction = (transaction) => {
  // Always show delete button - backend will validate authorization
  return true
}

const openDeleteConfirm = (transaction) => {
  transactionToDelete.value = transaction
  isDeleteConfirmActive.value = true
}

const deleteTransaction = async () => {
  if (!transactionToDelete.value) return

  try {
    const token = localStorage.getItem('auth_token')
    if (token && !axios.defaults.headers.common['Authorization']) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }

    const response = await axios.delete(`/api/transactions/${transactionToDelete.value.id}`)

    // Remove from list
    transactions.value = transactions.value.filter(
      (t) => t.id !== transactionToDelete.value.id
    )

    isDeleteConfirmActive.value = false
    transactionToDelete.value = null
    alert('Transaction deleted successfully!')
  } catch (error) {
    console.error('Error deleting transaction:', error)
    const errorMsg = error.response?.data?.message || error.response?.statusText || error.message
    alert('Failed to delete transaction: ' + errorMsg)
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  typeFilter.value = 'all'
}
</script>

<template>
  <div class="border border-gray-100 dark:border-slate-800 rounded-lg overflow-hidden">
    <!-- Filters -->
    <div class="p-3 lg:px-6 border-b border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
      <!-- Type Filter Buttons -->
      <div class="flex gap-2">
        <button
          v-for="type in ['all', 'income', 'expense']"
          :key="type"
          @click="typeFilter = type"
          :class="{
            'px-4 py-2 rounded text-sm font-semibold transition': true,
            'bg-blue-500 text-white': typeFilter === type,
            'bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-gray-300 hover:opacity-70': typeFilter !== type,
          }"
        >
          {{ type === 'all' ? 'All' : type === 'income' ? 'Income' : 'Expense' }}
        </button>
      </div>

      <div class="flex items-center gap-2">
        <!-- Search Input -->
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search..."
          class="w-full lg:w-40 px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <button
          v-if="searchQuery || typeFilter !== 'all'"
          @click="clearFilters"
          class="px-3 py-1.5 text-sm bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-gray-300 rounded hover:opacity-70 transition whitespace-nowrap"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-6 text-center text-gray-500">
      Loading transactions...
    </div>

    <!-- Table -->
    <table v-else class="min-w-full text-gray-800 dark:text-gray-100 bg-white dark:bg-slate-900">
      <thead>
        <tr>
          <th>
            <button
              class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
              @click="handleSort('created_at')"
            >
              Date
              <BaseIcon
                v-if="sortField === 'created_at'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>
            <button
              class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
              @click="handleSort('user')"
            >
              User
              <BaseIcon
                v-if="sortField === 'user'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>
            <button
              class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
              @click="handleSort('description')"
            >
              Description
              <BaseIcon
                v-if="sortField === 'description'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>
            <button
              class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
              @click="handleSort('type')"
            >
              Type
              <BaseIcon
                v-if="sortField === 'type'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>
            <button
              class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
              @click="handleSort('amount')"
            >
              Amount
              <BaseIcon
                v-if="sortField === 'amount'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th class="flex justify-center items-center p-2 lg:px-4" />
        </tr>
      </thead>
      <tbody>
        <tr v-for="transaction in itemsPaginated" :key="transaction.id">
          <td data-label="Date" class="lg:w-1 whitespace-nowrap">
            <small class="text-gray-500 dark:text-slate-400">
              {{ formatShortDate(transaction.created_at) }}
            </small>
          </td>
          <td data-label="User">
            {{ getFullName(transaction.user) }}
          </td>
          <td data-label="Description">
            {{ transaction.description || '-' }}
          </td>
          <td data-label="Type">
            <span
              :class="{
                'px-2 py-1 rounded text-sm font-semibold': true,
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200':
                  transaction.type === 'income',
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200':
                  transaction.type === 'expense',
              }"
            >
              {{ getTransactionTypeLabel(transaction.type) }}
            </span>
          </td>
          <td data-label="Amount" class="font-semibold">
            <span :class="{ 'text-green-600': transaction.type === 'income', 'text-red-600': transaction.type === 'expense' }">
              {{ formatCurrency(transaction.amount) }}
            </span>
          </td>
          <td class="before:hidden lg:w-1 whitespace-nowrap">
            <BaseButtons type="justify-center" no-wrap>
              <BaseButton color="info" :icon="mdiEye" small @click="viewTransactionDetail(transaction)" />
              <BaseButton
                v-if="canDeleteTransaction(transaction)"
                color="danger"
                :icon="mdiTrashCan"
                small
                @click="openDeleteConfirm(transaction)"
              />
            </BaseButtons>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- No Data -->
    <div v-if="!isLoading && filteredAndSortedItems.length === 0" class="p-6 text-center text-gray-500">
      No transactions found
    </div>

    <!-- Pagination -->
    <div v-if="numPages > 1" class="p-3 lg:px-6 border-t border-gray-100 dark:border-slate-800">
      <BaseLevel>
        <BaseButtons>
          <BaseButton
            label="Prev"
            color="whiteDark"
            small
            :disabled="currentPage === 0"
            @click="currentPage = Math.max(currentPage - 1, 0)"
          />
          <BaseButton
            v-for="page in pagesList"
            :key="page"
            :active="page === currentPage"
            :label="page + 1"
            :color="page === currentPage ? 'lightDark' : 'whiteDark'"
            small
            @click="currentPage = page"
          />
          <BaseButton
            label="Next"
            color="whiteDark"
            small
            :disabled="currentPage >= numPages - 1"
            @click="currentPage = Math.min(currentPage + 1, numPages - 1)"
          />
        </BaseButtons>
        <small>Page {{ currentPageHuman }} of {{ numPages }}</small>
      </BaseLevel>
    </div>
  </div>

  <!-- Detail Modal -->
  <CardBoxModal v-model="isDetailModalActive" title="Transaction Detail" large>
    <div v-if="selectedTransaction" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
          <p class="font-semibold">{{ formatShortDate(selectedTransaction.created_at) }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">User</p>
          <p class="font-semibold">{{ getFullName(selectedTransaction.user) }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Username</p>
          <p class="font-semibold">{{ selectedTransaction.user?.username || '-' }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Type</p>
          <p class="font-semibold">{{ getTransactionTypeLabel(selectedTransaction.type) }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Amount</p>
          <p
            class="font-semibold text-lg"
            :class="{
              'text-green-600': selectedTransaction.type === 'income',
              'text-red-600': selectedTransaction.type === 'expense',
            }"
          >
            {{ formatCurrency(selectedTransaction.amount) }}
          </p>
        </div>
      </div>
      <div class="border-t border-gray-200 dark:border-slate-700 pt-4 mt-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">Description</p>
        <p class="font-semibold">{{ selectedTransaction.description || '-' }}</p>
      </div>
    </div>
  </CardBoxModal>

  <!-- Delete Confirmation Modal -->
  <CardBoxModal
    v-model="isDeleteConfirmActive"
    title="Delete Transaction"
    button="danger"
    has-cancel
    @confirm="deleteTransaction"
    @cancel="isDeleteConfirmActive = false"
  >
    <p v-if="transactionToDelete" class="mb-4">
      Are you sure you want to delete this transaction?
    </p>
    <div v-if="transactionToDelete" class="bg-gray-50 dark:bg-slate-800 p-3 rounded">
      <p><strong>Amount:</strong> {{ formatCurrency(transactionToDelete.amount) }}</p>
      <p><strong>Type:</strong> {{ getTransactionTypeLabel(transactionToDelete.type) }}</p>
      <p><strong>Description:</strong> {{ transactionToDelete.description || '-' }}</p>
    </div>
  </CardBoxModal>
</template>
