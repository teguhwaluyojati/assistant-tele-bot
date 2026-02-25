import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useMainStore = defineStore('main', () => {
  let storedUser = {};
  try {
    const item = localStorage.getItem('user');
    if (item) {
      storedUser = JSON.parse(item);
    }
  } catch (e) {
    console.error("Gagal memuat user dari localStorage:", e);
    localStorage.removeItem('user');
  }
  const userName = ref(storedUser.name || 'John Doe');
  const userEmail = ref(storedUser.email || 'doe.doe.doe@example.com');

  const seed = (userEmail.value || 'default').replace(/[^a-z0-9]+/gi, '-');
  const defaultAvatar = `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}`;  
  const userAvatar = ref(storedUser?.avatar_url || defaultAvatar);

  const isFieldFocusRegistered = ref(false)

  const clients = ref([])
  const history = ref([])
  const currentUser = ref(null)

  function setUser(payload) {
    if (payload.name) {
      userName.value = payload.name
    }
    if (payload.email) {
      userEmail.value = payload.email
    }
    if (payload.avatar_url) {
      userAvatar.value = payload.avatar_url
    }
  }

  function fetchSampleClients() {
    return axios
      .get('/api/users')
      .then((result) => {
        clients.value = result?.data?.data || []
        return clients.value
      })
      .catch((error) => {
        console.error('Failed to fetch clients:', error)
        return []
      })
  }

  function fetchSampleHistory() {
    return axios
      .get(`data-sources/history.json`)
      .then((result) => {
        history.value = result?.data?.data || []
        return history.value
      })
      .catch((error) => {
        alert(error.message)
        return []
      })
  }

  function fetchTransactionsFromApi() {
    return axios
      .get('/api/transactions')
      .then((result) => {
        const transactions = result?.data?.data?.data || result?.data?.data || []
        if (Array.isArray(transactions) && transactions.length > 0) {
          history.value = transactions.map((t) => ({
            id: t.id,
            amount: t.amount,
            date: new Intl.DateTimeFormat('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
              timeZone: 'Asia/Jakarta',
            }).format(new Date(t.created_at)),
            business: t.user?.username || 'Unknown',
            type: t.type,
            name: t.description,
            account: t.user?.first_name + ' ' + t.user?.last_name || 'User',
          }))
          return history.value
        }
        return fetchSampleHistory()
      })
      .catch((error) => {
        console.error('Failed to fetch transactions:', error)
        return fetchSampleHistory()
      })
  }

  function fetchCurrentUser() {
    return axios
      .get('/api/user')
      .then((result) => {
        currentUser.value = result?.data
        return currentUser.value
      })
      .catch((error) => {
        console.error('Failed to fetch current user:', error)
        return null
      })
  }

  return {
    userName,
    userEmail,
    userAvatar,
    isFieldFocusRegistered,
    clients,
    history,
    currentUser,
    setUser,
    fetchSampleClients,
    fetchSampleHistory,
    fetchTransactionsFromApi,
    fetchCurrentUser,
  }
})
