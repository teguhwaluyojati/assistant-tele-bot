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
    const token = localStorage.getItem('auth_token')
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }

    axios
      .get('/api/users')
      .then((result) => {
        clients.value = result?.data?.data || []
      })
      .catch((error) => {
        alert(error.message)
      })
  }

  function fetchSampleHistory() {
    axios
      .get(`data-sources/history.json`)
      .then((result) => {
        history.value = result?.data?.data
      })
      .catch((error) => {
        alert(error.message)
      })
  }

  function fetchTransactionsFromApi() {
    const token = localStorage.getItem('auth_token')
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }

    axios
      .get('/api/transactions')
      .then((result) => {
        const transactions = result?.data?.data?.data || result?.data?.data || []
        if (Array.isArray(transactions) && transactions.length > 0) {
          history.value = transactions.map((t) => ({
            id: t.id,
            amount: t.amount,
            date: new Date(t.created_at).toLocaleDateString('id-ID'),
            business: t.user?.username || 'Unknown',
            type: t.type,
            name: t.description,
            account: t.user?.first_name + ' ' + t.user?.last_name || 'User',
          }))
        } else {
          fetchSampleHistory()
        }
      })
      .catch((error) => {
        console.error('Failed to fetch transactions:', error)
        fetchSampleHistory()
      })
  }

  function fetchCurrentUser() {
    const token = localStorage.getItem('auth_token')
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
    }

    axios
      .get('/api/user')
      .then((result) => {
        currentUser.value = result?.data
      })
      .catch((error) => {
        console.error('Failed to fetch current user:', error)
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
