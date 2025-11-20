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
    axios
      .get(`data-sources/clients.json?v=3`)
      .then((result) => {
        clients.value = result?.data?.data
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

  return {
    userName,
    userEmail,
    userAvatar,
    isFieldFocusRegistered,
    clients,
    history,
    setUser,
    fetchSampleClients,
    fetchSampleHistory,
  }
})
