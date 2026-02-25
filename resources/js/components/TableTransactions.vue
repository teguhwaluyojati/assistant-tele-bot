<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { mdiEye, mdiTrashCan, mdiPencil, mdiArrowUp, mdiArrowDown } from '@mdi/js'
import CardBoxModal from '@/components/CardBoxModal.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'

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
const selectedTransactions = ref(new Set())
const selectAll = ref(false)
const searchQuery = ref('')
const typeFilter = ref('all') // 'all', 'income', 'expense'
const sortField = ref('created_at')
const sortDirection = ref('desc')
const isLoading = ref(true)

// Server-side pagination
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 15 // Backend default

const isDetailModalActive = ref(false)
const selectedTransaction = ref(null)

const isEditModalActive = ref(false)
const transactionToEdit = ref(null)
const editForm = ref({
  amount: '',
  type: 'income',
  description: '',
})

const isDeleteConfirmActive = ref(false)
const transactionToDelete = ref(null)

const isMessageModalActive = ref(false)
const messageModalTitle = ref('')
const messageModalContent = ref('')
const messageModalType = ref('success') // 'success' or 'error'

const isBulkDeleteConfirmActive = ref(false)

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
    timeZone: 'Asia/Jakarta',
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
    // Server-side pagination with default per_page=15 from backend
    const params = new URLSearchParams({
      page: currentPage.value,
      sort: sortField.value,
      direction: sortDirection.value,
      ...(typeFilter.value !== 'all' && { type: typeFilter.value }),
      ...(searchQuery.value && { search: searchQuery.value }),
    })

    const response = await axios.get(`/api/transactions?${params.toString()}`)
    if (response.data.success || response.data.data) {
      const paginatedData = response.data.data
      transactions.value = Array.isArray(paginatedData.data) ? paginatedData.data : []
      lastPage.value = paginatedData.last_page || 1
      total.value = paginatedData.total || 0
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

// Refetch when filters/sorts change
const refetchTransactions = () => {
  currentPage.value = 1 // Reset to first page
  fetchTransactions()
}

// Watch for filter/search changes and refetch
watch([searchQuery, typeFilter], () => {
  refetchTransactions()
})


const filteredAndSortedItems = computed(() => {
  // Filtering and sorting is now handled by backend via server-side pagination
  // Return transactions directly as they come from API
  return transactions.value
})

const handleSort = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
  refetchTransactions() // Refetch with new sort
}

const itemsPaginated = computed(() => {
  // No longer need client-side slicing since backend handles pagination
  return transactions.value
})

const numPages = computed(() => lastPage.value)

const currentPageHuman = computed(() => currentPage.value)

const paginationItems = computed(() => {
  const totalPages = numPages.value
  const current = currentPage.value

  if (totalPages <= 7) {
    return Array.from({ length: totalPages }, (_, index) => index + 1)
  }

  const pages = [1]
  const windowStart = Math.max(2, current - 1)
  const windowEnd = Math.min(totalPages - 1, current + 1)

  if (windowStart > 2) {
    pages.push('...')
  }

  for (let page = windowStart; page <= windowEnd; page += 1) {
    pages.push(page)
  }

  if (windowEnd < totalPages - 1) {
    pages.push('...')
  }

  pages.push(totalPages)

  return pages
})

const viewTransactionDetail = (transaction) => {
  selectedTransaction.value = transaction
  isDetailModalActive.value = true
}

const canDeleteTransaction = (transaction) => {
  // Always show delete button - backend will validate authorization
  return true
}

const canEditTransaction = (transaction) => {
  // Always show edit button - backend will validate authorization
  return true
}

const openEditModal = (transaction) => {
  transactionToEdit.value = transaction
  editForm.value = {
    amount: transaction.amount,
    type: transaction.type,
    description: transaction.description || '',
  }
  isEditModalActive.value = true
}

const updateTransaction = async () => {
  if (!transactionToEdit.value) return

  try {
    const response = await axios.put(
      `/api/transactions/${transactionToEdit.value.id}`,
      editForm.value
    )

    // Update in list
    const index = transactions.value.findIndex((t) => t.id === transactionToEdit.value.id)
    if (index !== -1) {
      transactions.value[index] = response.data.data
    }

    isEditModalActive.value = false
    transactionToEdit.value = null
    
    // Show success modal
    messageModalTitle.value = 'Success'
    messageModalContent.value = 'Transaction updated successfully!'
    messageModalType.value = 'success'
    isMessageModalActive.value = true
  } catch (error) {
    console.error('Error updating transaction:', error)
    const errorMsg = error.response?.data?.message || error.response?.statusText || error.message
    
    // Show error modal
    messageModalTitle.value = 'Error'
    messageModalContent.value = `Failed to update transaction: ${errorMsg}`
    messageModalType.value = 'danger'
    isMessageModalActive.value = true
  }
}

const openDeleteConfirm = (transaction) => {
  transactionToDelete.value = transaction
  isDeleteConfirmActive.value = true
}

const deleteTransaction = async () => {
  if (!transactionToDelete.value) return

  try {
    const response = await axios.delete(`/api/transactions/${transactionToDelete.value.id}`)

    // Remove from list
    transactions.value = transactions.value.filter(
      (t) => t.id !== transactionToDelete.value.id
    )

    isDeleteConfirmActive.value = false
    transactionToDelete.value = null
    
    // Show success modal
    messageModalTitle.value = 'Success'
    messageModalContent.value = 'Transaction deleted successfully!'
    messageModalType.value = 'success'
    isMessageModalActive.value = true
  } catch (error) {
    console.error('Error deleting transaction:', error)
    const errorMsg = error.response?.data?.message || error.response?.statusText || error.message
    
    // Show error modal
    messageModalTitle.value = 'Error'
    messageModalContent.value = `Failed to delete transaction: ${errorMsg}`
    messageModalType.value = 'danger'
    isMessageModalActive.value = true
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  typeFilter.value = 'all'
  refetchTransactions()
}

const toggleSelectTransaction = (transactionId) => {
  if (selectedTransactions.value.has(transactionId)) {
    selectedTransactions.value.delete(transactionId)
  } else {
    selectedTransactions.value.add(transactionId)
  }
  updateSelectAllState()
}

const updateSelectAllState = () => {
  selectAll.value = itemsPaginated.value.length > 0 && 
    itemsPaginated.value.every((t) => selectedTransactions.value.has(t.id))
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    // Deselect all on current page
    itemsPaginated.value.forEach((t) => selectedTransactions.value.delete(t.id))
    selectAll.value = false
  } else {
    // Select all on current page
    itemsPaginated.value.forEach((t) => selectedTransactions.value.add(t.id))
    selectAll.value = true
  }
}

const isTransactionSelected = (transactionId) => {
  return selectedTransactions.value.has(transactionId)
}

const selectedCount = computed(() => selectedTransactions.value.size)

const openBulkDeleteConfirm = () => {
  if (selectedCount.value === 0) return
  isBulkDeleteConfirmActive.value = true
}

const bulkDeleteTransactions = async () => {
  if (selectedCount.value === 0) return

  try {
    const selectedIds = Array.from(selectedTransactions.value)
    const response = await axios.post('/api/transactions/bulk-delete', {
      ids: selectedIds,
    })

    // Remove deleted transactions from list
    transactions.value = transactions.value.filter(
      (t) => !selectedTransactions.value.has(t.id)
    )
    selectedTransactions.value.clear()
    selectAll.value = false

    isBulkDeleteConfirmActive.value = false

    // Show success modal
    messageModalTitle.value = 'Success'
    messageModalContent.value = `${response.data.data.deleted} transaction(s) deleted successfully!`
    messageModalType.value = 'success'
    isMessageModalActive.value = true
  } catch (error) {
    console.error('Error bulk deleting transactions:', error)
    const errorMsg = error.response?.data?.message || error.response?.statusText || error.message

    // Show error modal
    messageModalTitle.value = 'Error'
    messageModalContent.value = `Failed to delete transactions: ${errorMsg}`
    messageModalType.value = 'danger'
    isMessageModalActive.value = true
  }
}
</script>

<template>
  <div class="border border-gray-100 dark:border-slate-800 rounded-lg overflow-hidden">
    <!-- Filters & Toolbar -->
    <div class="p-3 lg:px-6 border-b border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
      <!-- Bulk Actions Toolbar -->
      <div v-if="selectedCount > 0" class="flex items-center justify-between gap-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
        <span class="text-sm font-medium text-blue-900 dark:text-blue-100">
          {{ selectedCount }} transaction(s) selected
        </span>
        <BaseButton
          label="Delete Selected"
          color="danger"
          small
          @click="openBulkDeleteConfirm"
        />
      </div>

      <!-- Filters -->
      <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
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
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="p-6 space-y-4 animate-pulse">
      <div v-for="row in 6" :key="row" class="flex items-center gap-4">
        <div class="h-4 w-24 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 w-32 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 w-20 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 w-16 rounded bg-gray-200 dark:bg-slate-700"></div>
      </div>
    </div>

    <!-- Table -->
    <table v-else class="min-w-full text-gray-800 dark:text-gray-100 bg-white dark:bg-slate-900">
      <thead>
        <tr>
          <th class="w-10">
            <input
              type="checkbox"
              :checked="selectAll"
              @change="toggleSelectAll"
              class="w-4 h-4 text-blue-600 focus:ring-blue-500 cursor-pointer"
            />
          </th>
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
          <td class="w-10">
            <input
              type="checkbox"
              :checked="isTransactionSelected(transaction.id)"
              @change="toggleSelectTransaction(transaction.id)"
              class="w-4 h-4 text-blue-600 focus:ring-blue-500 cursor-pointer"
            />
          </td>
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
                v-if="canEditTransaction(transaction)"
                color="success"
                :icon="mdiPencil"
                small
                @click="openEditModal(transaction)"
              />
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
            :disabled="currentPage === 1"
            @click="currentPage = Math.max(currentPage - 1, 1); fetchTransactions()"
          />
          <BaseButton
            v-for="(item, index) in paginationItems"
            :key="`${item}-${index}`"
            :active="item === currentPage"
            :label="String(item)"
            :color="item === currentPage ? 'lightDark' : 'whiteDark'"
            :disabled="item === '...'"
            small
            @click="item !== '...' && (currentPage = item, fetchTransactions())"
          />
          <BaseButton
            label="Next"
            color="whiteDark"
            small
            :disabled="currentPage >= numPages"
            @click="currentPage = Math.min(currentPage + 1, numPages); fetchTransactions()"
          />
        </BaseButtons>
        <small>Page {{ currentPageHuman }} of {{ numPages }} (Total: {{ total }})</small>
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

  <!-- Edit Modal -->
  <CardBoxModal
    v-model="isEditModalActive"
    title="Edit Transaction"
    button="success"
    button-label="Save Changes"
    has-cancel
    @confirm="updateTransaction"
    @cancel="isEditModalActive = false"
  >
    <div v-if="transactionToEdit" class="space-y-4">
      <FormField label="Amount">
        <FormControl
          v-model="editForm.amount"
          type="number"
          placeholder="Enter amount"
          min="0"
          step="0.01"
        />
      </FormField>

      <FormField label="Type">
        <div class="flex gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="editForm.type"
              type="radio"
              value="income"
              class="w-4 h-4 text-green-600 focus:ring-green-500"
            />
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Income</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              v-model="editForm.type"
              type="radio"
              value="expense"
              class="w-4 h-4 text-red-600 focus:ring-red-500"
            />
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Expense</span>
          </label>
        </div>
      </FormField>

      <FormField label="Description">
        <FormControl
          v-model="editForm.description"
          type="textarea"
          placeholder="Enter description (optional)"
        />
      </FormField>

      <div class="bg-gray-50 dark:bg-slate-800 p-3 rounded">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          <strong>User:</strong> {{ getFullName(transactionToEdit.user) }}
        </p>
        <p class="text-sm text-gray-600 dark:text-gray-400">
          <strong>Date:</strong> {{ formatShortDate(transactionToEdit.created_at) }}
        </p>
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

  <!-- Bulk Delete Confirmation Modal -->
  <CardBoxModal
    v-model="isBulkDeleteConfirmActive"
    title="Delete Selected Transactions"
    button="danger"
    button-label="Yes, Delete All"
    has-cancel
    @confirm="bulkDeleteTransactions"
    @cancel="isBulkDeleteConfirmActive = false"
  >
    <p class="mb-4">
      Are you sure you want to delete {{ selectedCount }} transaction(s)? This action cannot be undone.
    </p>
  </CardBoxModal>

  <!-- Message Modal (Success/Error) -->
  <CardBoxModal
    v-model="isMessageModalActive"
    :title="messageModalTitle"
    :button="messageModalType"
    button-label="OK"
    @confirm="isMessageModalActive = false"
  >
    <p>{{ messageModalContent }}</p>
  </CardBoxModal>
</template>
