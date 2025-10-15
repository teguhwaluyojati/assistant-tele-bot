<template>
  <div class="dashboard-container">
    <header class="dashboard-header">
      <div>
        <h1 v-if="user">Dashboard Admin</h1>
        <p v-if="user">Selamat datang kembali, {{ user.name }}!</p>
      </div>
      <button @click="handleLogout" class="logout-button">
        Logout
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h7v2H5v14h7v2zm11-4l-1.375-1.45l2.55-2.55H9v-2h8.175l-2.55-2.55L16 7l5 5z"/></svg>
      </button>
    </header>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-icon users">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M16 17v2a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2m-4-9a4 4 0 1 1 0-8a4 4 0 0 1 0 8m-4.5 9H14a.5.5 0 0 1 0 1H1.5a.5.5 0 0 1 0-1m18-3a3 3 0 1 1 0-6a3 3 0 0 1 0 6m-5.3-3.1a.5.5 0 0 1 0-.7l1.5-1.5a.5.5 0 0 1 .7 0l1.5 1.5a.5.5 0 0 1 0 .7l-1.5 1.5a.5.5 0 0 1-.7 0z"/></svg>
        </div>
        <div class="card-content">
            <p>Total Pengguna</p>
            <h3>{{ stats.totalUsers }}</h3>
        </div>
      </div>
       </div>

    <div class="card user-table">
      <h4>Daftar Pengguna Telegram</h4>
      <div v-if="loadingUsers" class="loading-state">Memuat data pengguna...</div>
      <div v-else-if="users.length === 0">Tidak ada data pengguna untuk ditampilkan.</div>
      <table v-else>
        <thead>
          <tr>
            <th>User ID</th>
            <th>Nama</th>
            <th>Username</th>
            <th>Interaksi Terakhir</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="telegramUser in users" :key="telegramUser.user_id">
            <td>{{ telegramUser.user_id }}</td>
            <td>{{ telegramUser.first_name }} {{ telegramUser.last_name }}</td>
            <td>{{ telegramUser.username ? '@' + telegramUser.username : 'N/A' }}</td>
            <td>{{ new Date(telegramUser.last_interaction_at).toLocaleString('id-ID') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Dashboard',
  data() {
    return {
      user: null, 
      users: [],
      loadingUsers: true, 
      stats: {
        totalUsers: 0,
      }
    };
  },
  created() {
    const userDataString = localStorage.getItem('user');
    const token = localStorage.getItem('auth_token');

    if (!token || !userDataString) {
      alert('Anda belum login. Silakan login terlebih dahulu.');
      window.location.href = '/';
      return;
    }
    
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

    try {
      this.user = JSON.parse(userDataString);
    } catch (error) {
      console.error('Gagal mem-parsing data user dari localStorage:', error);
      localStorage.clear();
      window.location.href = '/';
    }
  },
  mounted() {
    this.fetchUsers();
  },
  methods: {
    async fetchUsers() {
      this.loadingUsers = true;
      try {
        const response = await axios.get('/api/users');
        
        this.users = response.data.data; 
        this.stats.totalUsers = response.data.total; 

      } catch (error) {
        if (error.response && error.response.status === 401) {
          
          alert('Sesi Anda telah berakhir. Silakan login kembali.');
          
          this.clearAuthData();
          
        } else {
          console.error('Gagal mengambil data pengguna:', error);
          alert('Gagal mengambil data dari server.');
        }
      } finally {
        this.loadingUsers = false;
      }
    },

    async handleLogout() {
      if (!confirm('Apakah Anda yakin ingin logout?')) {
        return;
      }

      try {
        await axios.post('/api/logout');
        this.clearAuthData();
      } catch (error) {
        console.error('Logout gagal:', error);
        alert('Gagal untuk logout, mungkin sesi Anda sudah berakhir.');
        this.clearAuthData();
      }
    },

    clearAuthData() {
        localStorage.clear();
        delete axios.defaults.headers.common['Authorization'];
        window.location.href = '/';
    }
  }
}
</script>

<style scoped>
.dashboard-container {
  padding: 32px;
  background-color: #f3f4f6;
  font-family: 'Segoe UI', system-ui, sans-serif;
  color: #1f2937;
  min-height: 100vh;
}

.dashboard-header {
  margin-bottom: 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.dashboard-header h1 { font-size: 2.25rem; font-weight: 700; margin: 0; }
.dashboard-header p { color: #6b7280; font-size: 1rem; margin-top: 4px; }

.logout-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background-color: #ef4444;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease;
}
.logout-button:hover { background-color: #b91c1c; }
.logout-button svg { width: 20px; height: 20px; }

.card {
  background-color: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
}

.user-table { margin-top: 32px; }
.loading-state { text-align: center; padding: 40px; color: #6b7280; }

table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
th { background-color: #f9fafb; font-weight: 600; color: #374151; }
</style>