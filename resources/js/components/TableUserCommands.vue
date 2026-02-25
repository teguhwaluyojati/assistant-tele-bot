<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import axios from 'axios'
import { mdiArrowUp, mdiArrowDown } from '@mdi/js'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const emit = defineEmits(['search-change'])

const props = defineProps({
  dateStart: {
    type: String,
    default: '',
  },
  dateEnd: {
    type: String,
    default: '',
  },
  refreshKey: {
    type: Number,
    default: 0,
  },
})

const commands = ref([])
const isLoading = ref(true)
const searchQuery = ref('')
const sortField = ref('created_at')
const sortDirection = ref('desc')

const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 15

let searchDebounceTimer = null

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

const getDisplayName = (row) => {
  const fullName = [row.first_name, row.last_name].filter(Boolean).join(' ').trim()
  if (fullName) {
    return fullName
  }

  if (row.username) {
    return `@${row.username}`
  }

  return `User ${row.user_id}`
}

const fetchCommands = async () => {
  isLoading.value = true

  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      per_page: String(perPage),
    })

    if (searchQuery.value.trim()) {
      params.set('search', searchQuery.value.trim())
    }

    if (props.dateStart) {
      params.set('start_date', props.dateStart)
    }

    if (props.dateEnd) {
      params.set('end_date', props.dateEnd)
    }

    const response = await axios.get(`/api/users/commands?${params.toString()}`)
    const paginated = response.data?.data

    commands.value = Array.isArray(paginated?.data) ? paginated.data : []
    lastPage.value = paginated?.last_page || 1
    total.value = paginated?.total || 0
  } catch (error) {
    console.error('Error fetching user commands:', error)
    commands.value = []
  } finally {
    isLoading.value = false
  }
}

const sortedItems = computed(() => {
  const sorted = [...commands.value]

  sorted.sort((a, b) => {
    let aVal
    let bVal

    if (sortField.value === 'created_at') {
      aVal = new Date(a.created_at || 0).getTime()
      bVal = new Date(b.created_at || 0).getTime()
    } else if (sortField.value === 'command') {
      aVal = (a.command || '').toLowerCase()
      bVal = (b.command || '').toLowerCase()
    } else {
      aVal = (getDisplayName(a) || '').toLowerCase()
      bVal = (getDisplayName(b) || '').toLowerCase()
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

const paginationItems = computed(() => {
  const totalPages = lastPage.value
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

const currentPageHuman = computed(() => currentPage.value)

const clearSearch = () => {
  searchQuery.value = ''
}

watch([() => props.dateStart, () => props.dateEnd], () => {
  currentPage.value = 1
  fetchCommands()
})

watch(
  () => props.refreshKey,
  () => {
    currentPage.value = 1
    fetchCommands()
  },
)

watch(searchQuery, () => {
  emit('search-change', searchQuery.value)

  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
  }

  searchDebounceTimer = setTimeout(() => {
    currentPage.value = 1
    fetchCommands()
  }, 300)
})

onMounted(() => {
  fetchCommands()
})
</script>

<template>
  <div class="border border-gray-100 dark:border-slate-800 rounded-lg overflow-hidden">
    <div class="p-3 lg:px-6 border-b border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900">
      <div class="flex items-center gap-2 justify-end">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search..."
          class="w-full lg:w-48 px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <button
          v-if="searchQuery"
          @click="clearSearch"
          class="px-3 py-1.5 text-sm bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-gray-300 rounded hover:opacity-70 transition"
        >
          Clear
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="p-6 space-y-4 animate-pulse">
      <div v-for="row in 6" :key="row" class="flex items-center gap-4">
        <div class="h-4 w-32 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 w-40 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 w-24 rounded bg-gray-200 dark:bg-slate-700"></div>
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-slate-700"></div>
      </div>
    </div>

    <table v-else class="min-w-full text-gray-800 dark:text-gray-100 bg-white dark:bg-slate-900">
      <thead>
        <tr>
          <th>
            <button class="flex items-center gap-1 hover:opacity-70" @click="handleSort('created_at')">
              Date
              <BaseIcon
                v-if="sortField === 'created_at'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>
            <button class="flex items-center gap-1 hover:opacity-70" @click="handleSort('user')">
              User
              <BaseIcon
                v-if="sortField === 'user'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
          <th>Telegram ID</th>
          <th>
            <button class="flex items-center gap-1 hover:opacity-70" @click="handleSort('command')">
              Command
              <BaseIcon
                v-if="sortField === 'command'"
                :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
                size="16"
              />
            </button>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in sortedItems" :key="row.id" class="border-b border-gray-100 dark:border-slate-800">
          <td class="py-2 px-3 text-sm text-gray-500 dark:text-slate-400">{{ formatShortDate(row.created_at) }}</td>
          <td class="py-2 px-3">{{ getDisplayName(row) }}</td>
          <td class="py-2 px-3">{{ row.user_id }}</td>
          <td class="py-2 px-3 font-mono text-sm text-blue-600 dark:text-blue-400">{{ row.command }}</td>
        </tr>
      </tbody>
    </table>

    <div v-if="!isLoading && sortedItems.length === 0" class="p-6 text-center text-gray-500">
      No command data found
    </div>

    <div v-if="lastPage > 1" class="p-3 lg:px-6 border-t border-gray-100 dark:border-slate-800">
      <BaseLevel>
        <BaseButtons>
          <BaseButton
            label="Prev"
            color="whiteDark"
            small
            :disabled="currentPage === 1"
            @click="currentPage = Math.max(currentPage - 1, 1); fetchCommands()"
          />
          <BaseButton
            v-for="(item, index) in paginationItems"
            :key="`${item}-${index}`"
            :active="item === currentPage"
            :label="String(item)"
            :color="item === currentPage ? 'lightDark' : 'whiteDark'"
            :disabled="item === '...'"
            small
            @click="item !== '...' && (currentPage = item, fetchCommands())"
          />
          <BaseButton
            label="Next"
            color="whiteDark"
            small
            :disabled="currentPage >= lastPage"
            @click="currentPage = Math.min(currentPage + 1, lastPage); fetchCommands()"
          />
        </BaseButtons>
        <small>Page {{ currentPageHuman }} of {{ lastPage }} (Total: {{ total }})</small>
      </BaseLevel>
    </div>
  </div>
</template>
