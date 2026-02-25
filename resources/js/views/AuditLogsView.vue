<script setup>
import { computed, ref, onMounted, watch } from 'vue'
import axios from 'axios'
import { mdiClipboardTextClockOutline, mdiReload, mdiDownload, mdiCog } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import CardBox from '@/components/CardBox.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'

const logs = ref([])
const isLoading = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const searchKeyword = ref('')
const filterStartDate = ref('')
const filterEndDate = ref('')
const isFilterModalOpen = ref(false)

const fetchLogs = async () => {
  isLoading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 20,
    }

    if (searchKeyword.value?.trim()) {
      params.search = searchKeyword.value.trim()
    }

    if (filterStartDate.value) {
      params.start_date = filterStartDate.value
    }

    if (filterEndDate.value) {
      params.end_date = filterEndDate.value
    }

    const response = await axios.get('/api/audit-logs', {
      params,
    })

    const paginated = response.data?.data
    logs.value = paginated?.data || []
    lastPage.value = paginated?.last_page || 1
    total.value = paginated?.total || 0
  } catch (error) {
    logs.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchLogs()
})

const numPages = computed(() => lastPage.value)
const currentPageHuman = computed(() => currentPage.value)
const pagesList = computed(() => {
  const pages = []
  for (let i = 1; i <= numPages.value; i++) {
    pages.push(i)
  }
  return pages
})

const changePage = (page) => {
  if (page < 1 || page > numPages.value || page === currentPage.value) {
    return
  }

  currentPage.value = page
  fetchLogs()
}

watch(searchKeyword, () => {
  currentPage.value = 1
  fetchLogs()
})

const openFilterModal = () => {
  isFilterModalOpen.value = true
}

const applyDateFilter = () => {
  if (filterStartDate.value && filterEndDate.value && filterStartDate.value > filterEndDate.value) {
    return
  }

  isFilterModalOpen.value = false
  currentPage.value = 1
  fetchLogs()
}

const clearDateFilter = () => {
  filterStartDate.value = ''
  filterEndDate.value = ''
  currentPage.value = 1
  fetchLogs()
}

const clearSearch = () => {
  searchKeyword.value = ''
}

const exportLogs = async () => {
  try {
    const params = {}

    if (searchKeyword.value?.trim()) {
      params.search = searchKeyword.value.trim()
    }

    if (filterStartDate.value) {
      params.start_date = filterStartDate.value
    }

    if (filterEndDate.value) {
      params.end_date = filterEndDate.value
    }

    const response = await axios.get('/api/audit-logs/export', {
      params,
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `audit-logs-${new Date().toISOString().split('T')[0]}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.parentNode.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error exporting audit logs:', error)
  }
}

const formatDate = (value) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Asia/Jakarta',
  }).format(date)
}

const displayCauser = (log) => {
  return log?.causer?.email || log?.causer?.name || 'system'
}
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiClipboardTextClockOutline" title="Audit Logs" main>
        <div class="flex gap-2">
          <BaseButton :icon="mdiDownload" color="whiteDark" @click="exportLogs" />
          <BaseButton :icon="mdiCog" color="whiteDark" @click="openFilterModal" />
          <BaseButton :icon="mdiReload" color="whiteDark" @click="fetchLogs" />
        </div>
      </SectionTitleLineWithButton>

      <CardBox>
        <div class="mb-4 flex items-center justify-end gap-2">
          <input
            v-model="searchKeyword"
            type="text"
            placeholder="Search..."
            class="w-full max-w-xs px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
          <div v-if="searchKeyword" class="shrink-0">
            <BaseButton label="Clear" color="whiteDark" outline @click="clearSearch" />
          </div>
        </div>

        <div v-if="isLoading" class="space-y-4 animate-pulse">
          <div v-for="row in 6" :key="row" class="flex items-center gap-4">
            <div class="h-4 w-32 rounded bg-gray-200 dark:bg-slate-700"></div>
            <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-slate-700"></div>
            <div class="h-4 w-32 rounded bg-gray-200 dark:bg-slate-700"></div>
            <div class="h-4 w-24 rounded bg-gray-200 dark:bg-slate-700"></div>
          </div>
        </div>

        <div v-else>
          <div v-if="logs.length === 0" class="text-center text-gray-500 dark:text-slate-400 p-6">
            No audit logs yet.
          </div>

          <table v-else class="min-w-full text-gray-800 dark:text-gray-100">
            <thead>
              <tr class="text-left border-b border-gray-100 dark:border-slate-800">
                <th class="py-2 px-3">Date</th>
                <th class="py-2 px-3">Action</th>
                <th class="py-2 px-3">User</th>
                <th class="py-2 px-3">Subject</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs" :key="log.id" class="border-b border-gray-100 dark:border-slate-800">
                <td class="py-2 px-3 text-sm text-gray-500 dark:text-slate-400">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="py-2 px-3 font-medium">
                  {{ log.description }}
                </td>
                <td class="py-2 px-3">
                  {{ displayCauser(log) }}
                </td>
                <td class="py-2 px-3 text-sm text-gray-500 dark:text-slate-400">
                  {{ log.subject_type ? log.subject_type.split('\\').pop() : '-' }}
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="numPages > 1" class="pt-4">
            <BaseLevel>
              <BaseButtons>
                <BaseButton
                  label="Prev"
                  color="whiteDark"
                  small
                  :disabled="currentPage === 1"
                  @click="changePage(currentPage - 1)"
                />
                <BaseButton
                  v-for="page in pagesList"
                  :key="page"
                  :active="page === currentPage"
                  :label="String(page)"
                  :color="page === currentPage ? 'lightDark' : 'whiteDark'"
                  small
                  @click="changePage(page)"
                />
                <BaseButton
                  label="Next"
                  color="whiteDark"
                  small
                  :disabled="currentPage >= numPages"
                  @click="changePage(currentPage + 1)"
                />
              </BaseButtons>
              <small>Page {{ currentPageHuman }} of {{ numPages }} (Total: {{ total }})</small>
            </BaseLevel>
          </div>
        </div>
      </CardBox>

      <CardBoxModal
        v-model="isFilterModalOpen"
        title="Filter Audit Logs by Date"
        button-label="Apply"
        :has-cancel="true"
        @confirm="applyDateFilter"
        @cancel="isFilterModalOpen = false"
      >
        <FormField label="Start date" label-for="audit-filter-start-date">
          <FormControl id="audit-filter-start-date" v-model="filterStartDate" type="date" />
        </FormField>
        <FormField label="End date" label-for="audit-filter-end-date">
          <FormControl id="audit-filter-end-date" v-model="filterEndDate" type="date" />
        </FormField>
        <div class="mt-4">
          <BaseButton label="Clear Filter" color="whiteDark" outline @click="clearDateFilter" />
        </div>
      </CardBoxModal>
    </SectionMain>
  </LayoutAuthenticated>
</template>
