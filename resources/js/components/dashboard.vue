<template>
  <div class="dashboard-container">
    <header class="dashboard-header">
      <h1>Dashboard</h1>
      <p>Ringkasan aktivitas sistem Anda.</p>
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

      <div class="stat-card">
        <div class="card-icon revenue">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12.5a3.5 3.5 0 1 1 0-7a3.5 3.5 0 0 1 0 7m0-9a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11m6 10a1 1 0 0 0-1-1H7a1 1 0 1 0 0 2h10a1 1 0 0 0 1-1m1-9h-2.5a.5.5 0 0 0 0 1h2a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-8a.5.5 0 0 1 .5-.5h2a.5.5 0 0 0 0-1H4.5A1.5 1.5 0 0 0 3 6v8a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 19 14V6a1.5 1.5 0 0 0-1.5-1.5"/></svg>
        </div>
        <div class="card-content">
          <p>Pendapatan Bulan Ini</p>
          <h3>{{ stats.revenue.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="card-icon errors">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M13 13h-2V7h2m0 10h-2v-2h2m-1-9.99a.996.996 0 0 0-1-1H4a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V8l-6-6M5 19V5h10v4h4v10z"/></svg>
        </div>
        <div class="card-content">
          <p>Error (24 Jam)</p>
          <h3>{{ stats.errorsLast24h }}</h3>
        </div>
      </div>

      <div class="stat-card">
        <div class="card-icon activity">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="m16.27 15.22l-1.04-1.04l2.21-2.22l-3.23.01l.01-1.49l3.24-.01l-2.22-2.22l1.04-1.04L22.5 12zM3.5 12a8.5 8.5 0 0 1 8.5-8.5v1.5a7 7 0 0 0-7 7H3.5m8.5 8.5a8.5 8.5 0 0 1-8.5-8.5H2a10 10 0 0 0 10 10v-1.5m0-15.5V2a10 10 0 0 0-10 10h1.5a8.5 8.5 0 0 1 8.5-8.5"/></svg>
        </div>
        <div class="card-content">
          <p>Aktivitas Login</p>
          <h3>{{ stats.logins }}</h3>
        </div>
      </div>
    </div>

    <div class="more-content-placeholder">
      <div class="card">
        <h4>Grafik Aktivitas</h4>
        <p>Placeholder untuk grafik...</p>
      </div>
      <div class="card">
        <h4>Log Terbaru</h4>
        <p>Placeholder untuk tabel log...</p>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'SimpleDashboard',
  data() {
    return {
      stats: {
        totalUsers: 1472,
        revenue: 12550000,
        errorsLast24h: 3,
        logins: 218,
      }
    };
  },
  // Anda bisa menambahkan method untuk mengambil data dari API di sini,
  // misalnya di dalam mounted() hook.
  // mounted() {
  //   this.fetchDashboardData();
  // }
}
</script>

<style scoped>
.dashboard-container {
  padding: 32px;
  background-color: #f3f4f6;
  font-family: 'Segoe UI', system-ui, sans-serif;
  color: #1f2937;
}

.dashboard-header {
  margin-bottom: 32px;
}

.dashboard-header h1 {
  font-size: 2.25rem; /* 36px */
  font-weight: 700;
  margin: 0;
}

.dashboard-header p {
  color: #6b7280;
  font-size: 1rem;
  margin-top: 4px;
}

.stats-grid {
  display: grid;
  /* Membuat kolom responsif, minimal 240px */
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 24px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background-color: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
}

.card-icon svg {
  width: 24px;
  height: 24px;
  color: white;
}

.card-icon.users { background-color: #3b82f6; }
.card-icon.revenue { background-color: #22c55e; }
.card-icon.errors { background-color: #ef4444; }
.card-icon.activity { background-color: #f97316; }

.card-content p {
  color: #6b7280;
  margin: 0;
  font-size: 0.9rem;
  font-weight: 500;
}

.card-content h3 {
  color: #111827;
  margin: 4px 0 0 0;
  font-size: 1.875rem;
  font-weight: 600;
  letter-spacing: -0.025em;
}

.more-content-placeholder {
  margin-top: 32px;
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
}
.more-content-placeholder .card {
    background-color: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
}
.more-content-placeholder h4 { margin-top: 0; }
</style>