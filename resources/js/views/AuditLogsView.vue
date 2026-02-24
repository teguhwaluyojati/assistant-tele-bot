<script setup>
import { computed, ref, onMounted } from 'vue'
import axios from 'axios'
import { mdiClipboardTextClockOutline, mdiReload } from '@mdi/js'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionMain from '@/components/SectionMain.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import CardBox from '@/components/CardBox.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'

const logs = ref([])
const isLoading = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

const fetchLogs = async () => {
  isLoading.value = true
  try {
    const response = await axios.get('/api/audit-logs', {
      params: {
        page: currentPage.value,
        per_page: 20,
      },
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
        <BaseButton :icon="mdiReload" color="whiteDark" @click="fetchLogs" />
      </SectionTitleLineWithButton>

      <CardBox>
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
    </SectionMain>
  </LayoutAuthenticated>
</template>
