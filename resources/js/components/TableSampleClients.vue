<script setup>
import { computed, ref } from 'vue'
import { useMainStore } from '@/stores/main'
import { mdiEye, mdiTrashCan, mdiArrowUp, mdiArrowDown } from '@mdi/js'
import CardBoxModal from '@/components/CardBoxModal.vue'
import TableCheckboxCell from '@/components/TableCheckboxCell.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import FormControl from '@/components/FormControl.vue'

defineProps({
  checkable: Boolean,
})

const mainStore = useMainStore()

const items = computed(() => mainStore.clients)

const searchQuery = ref('')
const sortField = ref('last_interaction_at')
const sortDirection = ref('desc')

const displayName = (client) => {
  const fullName = [client.first_name, client.last_name].filter(Boolean).join(' ').trim()
  return fullName || client.username || `User ${client.user_id}`
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

const getLevelLabel = (level) => {
  const levels = {
    1: 'Admin',
    2: 'Member',
  }
  return levels[level] || '-'
}

const filteredAndSortedItems = computed(() => {
  let filtered = items.value

  // Search filter
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase().trim()
    filtered = filtered.filter((client) => {
      const name = displayName(client).toLowerCase()
      const username = (client.username || '').toLowerCase()
      const userId = String(client.user_id || '').toLowerCase()
      const level = getLevelLabel(client.level).toLowerCase()
      
      return name.includes(query) || username.includes(query) || userId.includes(query) || level.includes(query)
    })
  }

  // Sort
  const sorted = [...filtered].sort((a, b) => {
    let aVal, bVal

    switch (sortField.value) {
      case 'name':
        aVal = displayName(a).toLowerCase()
        bVal = displayName(b).toLowerCase()
        break
      case 'username':
        aVal = (a.username || '').toLowerCase()
        bVal = (b.username || '').toLowerCase()
        break
      case 'level':
        aVal = a.level ?? 0
        bVal = b.level ?? 0
        break
      case 'user_id':
        aVal = a.user_id || 0
        bVal = b.user_id || 0
        break
      case 'last_interaction_at':
        aVal = new Date(a.last_interaction_at || 0).getTime()
        bVal = new Date(b.last_interaction_at || 0).getTime()
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
    // Toggle direction if clicking same field
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    // New field, default to asc
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const isModalActive = ref(false)

const isModalDangerActive = ref(false)

const perPage = ref(5)

const currentPage = ref(0)

const checkedRows = ref([])

const itemsPaginated = computed(() =>
  filteredAndSortedItems.value.slice(perPage.value * currentPage.value, perPage.value * (currentPage.value + 1)),
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

const remove = (arr, cb) => {
  const newArr = []

  arr.forEach((item) => {
    if (!cb(item)) {
      newArr.push(item)
    }
  })

  return newArr
}

const checked = (isChecked, client) => {
  if (isChecked) {
    checkedRows.value.push(client)
  } else {
    checkedRows.value = remove(checkedRows.value, (row) => row.id === client.id)
  }
}
</script>

<template>
  <CardBoxModal v-model="isModalActive" title="Sample modal">
    <p>Lorem ipsum dolor sit amet <b>adipiscing elit</b></p>
    <p>This is sample modal</p>
  </CardBoxModal>

  <CardBoxModal v-model="isModalDangerActive" title="Please confirm" button="danger" has-cancel>
    <p>Lorem ipsum dolor sit amet <b>adipiscing elit</b></p>
    <p>This is sample modal</p>
  </CardBoxModal>

  <!-- Table -->
  <div class="border border-gray-100 dark:border-slate-800 rounded">
    <table class="min-w-full text-gray-800 dark:text-gray-100 bg-white dark:bg-slate-900">
      <thead>
        <tr>
        <th v-if="checkable" />
        <th />
        <th>
          <button
            class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
            @click="handleSort('name')"
          >
            Name
            <BaseIcon
              v-if="sortField === 'name'"
              :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
              size="16"
            />
          </button>
        </th>
        <th>
          <button
            class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
            @click="handleSort('username')"
          >
            Username
            <BaseIcon
              v-if="sortField === 'username'"
              :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
              size="16"
            />
          </button>
        </th>
        <th>
          <button
            class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
            @click="handleSort('level')"
          >
            Level
            <BaseIcon
              v-if="sortField === 'level'"
              :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
              size="16"
            />
          </button>
        </th>
        <th>
          <button
            class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
            @click="handleSort('user_id')"
          >
            Telegram ID
            <BaseIcon
              v-if="sortField === 'user_id'"
              :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
              size="16"
            />
          </button>
        </th>
        <th>
          <button
            class="flex items-center gap-1 hover:opacity-70 cursor-pointer"
            @click="handleSort('last_interaction_at')"
          >
            Last Interaction
            <BaseIcon
              v-if="sortField === 'last_interaction_at'"
              :path="sortDirection === 'asc' ? mdiArrowUp : mdiArrowDown"
              size="16"
            />
          </button>
        </th>
        <th class="flex justify-end items-center p-2 lg:px-4">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search..."
            class="w-40 px-3 py-1.5 text-sm border border-gray-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          />
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="client in itemsPaginated" :key="client.id">
        <TableCheckboxCell v-if="checkable" @checked="checked($event, client)" />
        <td class="border-b-0 lg:w-6 before:hidden">
          <UserAvatar :username="displayName(client)" class="w-24 h-24 mx-auto lg:w-6 lg:h-6" />
        </td>
        <td data-label="Name">
          {{ displayName(client) }}
        </td>
        <td data-label="Username">
          {{ client.username || '-' }}
        </td>
        <td data-label="Level">
          {{ getLevelLabel(client.level) }}
        </td>
        <td data-label="Telegram ID">
          {{ client.user_id || '-' }}
        </td>
        <td data-label="Last Interaction" class="lg:w-1 whitespace-nowrap">
          <small
            class="text-gray-500 dark:text-slate-400"
            :title="client.last_interaction_at"
          >{{
            formatShortDate(client.last_interaction_at)
          }}</small>
        </td>
        <td class="before:hidden lg:w-1 whitespace-nowrap">
          <BaseButtons type="justify-center" no-wrap>
            <BaseButton color="info" :icon="mdiEye" small @click="isModalActive = true" />
            <BaseButton
              color="danger"
              :icon="mdiTrashCan"
              small
              @click="isModalDangerActive = true"
            />
          </BaseButtons>
        </td>
      </tr>
    </tbody>
  </table>
  </div>
  <div class="p-3 lg:px-6 border-t border-gray-100 dark:border-slate-800">
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
</template>
