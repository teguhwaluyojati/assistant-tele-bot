<script setup>
import { computed, ref, onBeforeUnmount } from 'vue'
import { useMainStore } from '@/stores/main'
import {
  mdiEye,
  mdiTrashCan,
  mdiArrowUp,
  mdiArrowDown,
  mdiAccountSwitch,
  mdiCheckCircle,
  mdiAlertCircle,
} from '@mdi/js'
import CardBoxModal from '@/components/CardBoxModal.vue'
import TableCheckboxCell from '@/components/TableCheckboxCell.vue'
import BaseLevel from '@/components/BaseLevel.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import FormControl from '@/components/FormControl.vue'
import axios from 'axios'

defineProps({
  checkable: Boolean,
  isLoading: {
    type: Boolean,
    default: false,
  },
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
const selectedUser = ref(null)
const userCommands = ref([])
const isLoadingDetail = ref(false)

const isRoleModalActive = ref(false)
const roleTarget = ref(null)
const roleValue = ref(2)
const roleError = ref('')
const isUpdatingRole = ref(false)
const roleToast = ref({
  visible: false,
  type: 'success',
  message: '',
})

let roleToastTimer = null

const roleToastClass = computed(() =>
  roleToast.value.type === 'success'
    ? 'bg-emerald-500 text-white'
    : 'bg-red-500 text-white',
)
const roleToastIcon = computed(() =>
  roleToast.value.type === 'success' ? mdiCheckCircle : mdiAlertCircle,
)
const roleButtonLabel = computed(() => (isUpdatingRole.value ? 'Saving...' : 'Save'))

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

const viewUserDetail = async (client) => {
  isLoadingDetail.value = true
  selectedUser.value = client
  userCommands.value = []
  isModalActive.value = true

  try {
    const response = await axios.get(`/api/users/${client.user_id}`)

    if (response.data.success) {
      selectedUser.value = response.data.data.user
      userCommands.value = response.data.data.commands
    }
  } catch (error) {
    console.error('Error fetching user detail:', error)
    if (error.response?.status === 401) {
      alert('Session expired. Please login again.')
      localStorage.removeItem('user')
      window.location.href = '/login'
    } else {
      alert('Failed to load user detail. Please try again.')
    }
  } finally {
    isLoadingDetail.value = false
  }
}

const openRoleModal = (client) => {
  roleTarget.value = client
  roleValue.value = client.level ?? 2
  roleError.value = ''
  isRoleModalActive.value = true
}

const showRoleToast = (type, message) => {
  roleToast.value = {
    visible: true,
    type,
    message,
  }

  if (roleToastTimer) {
    clearTimeout(roleToastTimer)
  }

  roleToastTimer = setTimeout(() => {
    roleToast.value.visible = false
  }, 2500)
}

const updateClientLevel = (userId, level) => {
  const index = mainStore.clients.findIndex((client) => client.user_id === userId || client.id === userId)
  if (index !== -1) {
    mainStore.clients[index] = {
      ...mainStore.clients[index],
      level,
    }
  }

  if (selectedUser.value && (selectedUser.value.user_id === userId || selectedUser.value.id === userId)) {
    selectedUser.value = {
      ...selectedUser.value,
      level,
    }
  }
}

const submitRoleUpdate = async () => {
  if (!roleTarget.value || isUpdatingRole.value) {
    return
  }

  isUpdatingRole.value = true
  roleError.value = ''

  try {
    const response = await axios.put(`/api/users/${roleTarget.value.user_id}/role`, {
      level: roleValue.value,
    })

    if (response.data?.success) {
      updateClientLevel(roleTarget.value.user_id, roleValue.value)
      showRoleToast(
        'success',
        `Role for ${displayName(roleTarget.value)} updated to ${getLevelLabel(roleValue.value)}.`,
      )
      isRoleModalActive.value = false
    } else {
      roleError.value = response.data?.message || 'Failed to update role.'
      showRoleToast('error', roleError.value)
    }
  } catch (error) {
    roleError.value = error.response?.data?.message || 'Failed to update role.'
    showRoleToast('error', roleError.value)
  } finally {
    isUpdatingRole.value = false
  }
}

onBeforeUnmount(() => {
  if (roleToastTimer) {
    clearTimeout(roleToastTimer)
  }
})
</script>

<template>
  <CardBoxModal v-model="isModalActive" title="User Detail" large>
    <div v-if="isLoadingDetail" class="text-center py-4">
      <p>Loading...</p>
    </div>
    <div v-else-if="selectedUser" class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
          <p class="font-semibold">{{ displayName(selectedUser) }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Username</p>
          <p class="font-semibold">{{ selectedUser.username || '-' }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Level</p>
          <p class="font-semibold">{{ getLevelLabel(selectedUser.level) }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Telegram ID</p>
          <p class="font-semibold">{{ selectedUser.user_id }}</p>
        </div>
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">Last Interaction</p>
          <p class="font-semibold">{{ formatShortDate(selectedUser.last_interaction_at) }}</p>
        </div>
      </div>
      
      <div class="border-t border-gray-200 dark:border-slate-700 pt-4 mt-4">
        <h3 class="font-semibold text-lg mb-3">Command History (Last 50)</h3>
        <div v-if="userCommands.length === 0" class="text-gray-500 dark:text-gray-400 text-sm">
          No commands found
        </div>
        <div v-else class="max-h-64 overflow-y-auto space-y-2">
          <div
            v-for="cmd in userCommands"
            :key="cmd.id"
            class="p-3 bg-gray-50 dark:bg-slate-800 rounded text-sm"
          >
            <div class="flex justify-between items-start">
              <span class="font-mono text-blue-600 dark:text-blue-400">{{ cmd.command }}</span>
              <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatShortDate(cmd.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </CardBoxModal>

  <CardBoxModal
    v-model="isRoleModalActive"
    title="Update Role"
    button="info"
    :buttonLabel="roleButtonLabel"
    has-cancel
    @confirm="submitRoleUpdate"
  >
    <div class="space-y-3">
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Update role for <b>{{ roleTarget ? displayName(roleTarget) : '-' }}</b>
      </p>
      <select
        v-model.number="roleValue"
        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-100"
        :disabled="isUpdatingRole"
      >
        <option :value="1">Admin</option>
        <option :value="2">Member</option>
      </select>
      <p v-if="isUpdatingRole" class="text-sm text-gray-500 dark:text-gray-400">Saving changes...</p>
      <p v-if="roleError" class="text-sm text-red-500">{{ roleError }}</p>
    </div>
  </CardBoxModal>

  <CardBoxModal v-model="isModalDangerActive" title="Please confirm" button="danger" has-cancel>
    <p>Lorem ipsum dolor sit amet <b>adipiscing elit</b></p>
    <p>This is sample modal</p>
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
      v-if="roleToast.visible"
      class="fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2"
      :class="roleToastClass"
    >
      <BaseIcon :path="roleToastIcon" size="18" />
      <span class="text-sm font-medium">{{ roleToast.message }}</span>
    </div>
  </transition>

  <!-- Table -->
  <div class="border border-gray-100 dark:border-slate-800 rounded-lg overflow-hidden">
    <div v-if="isLoading" class="p-6 space-y-4 animate-pulse">
      <div v-for="row in 5" :key="row" class="flex items-center gap-4">
        <div class="h-8 w-8 rounded-full bg-gray-200 dark:bg-slate-700"></div>
        <div class="flex-1 space-y-2">
          <div class="h-4 w-1/3 rounded bg-gray-200 dark:bg-slate-700"></div>
          <div class="h-3 w-1/4 rounded bg-gray-200 dark:bg-slate-700"></div>
        </div>
        <div class="h-6 w-20 rounded bg-gray-200 dark:bg-slate-700"></div>
      </div>
    </div>
    <table v-else class="min-w-full text-gray-800 dark:text-gray-100 bg-white dark:bg-slate-900">
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
          <UserAvatar
            :username="displayName(client)"
            :avatar="client.avatar_url"
            class="w-24 h-24 mx-auto lg:w-6 lg:h-6"
          />
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
            <BaseButton color="info" :icon="mdiEye" small @click="viewUserDetail(client)" />
            <BaseButton color="warning" :icon="mdiAccountSwitch" small @click="openRoleModal(client)" />
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
