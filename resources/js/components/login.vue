<template>
  <div class="login-container">
    <div class="left-pane">
      <div class="welcome-text">
        <h1>Kelola Bot Anda</h1>
        <p>Akses dasbor admin untuk memonitor
            <span class="typing-text">{{ typedText }}</span>
        </p>
      </div>
      <div class="welcome-illustration">
        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          <path d="m9 12 2 2 4-4"></path>
        </svg>
      </div>
    </div>

    <div class="right-pane">
      <div class="login-box">
        <div class="logo">
          🤖
        </div>
        <h2 class="login-title">Selamat Datang!</h2>
        <p class="login-subtitle">Silakan login untuk melanjutkan.</p>

        <form @submit.prevent="handleLogin">
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              <input type="email" id="email" v-model="form.email" placeholder="contoh@email.com" required :disabled="loading" />
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <input :type="passwordFieldType" id="password" v-model="form.password" placeholder="••••••••" required :disabled="loading" />
              <button type="button" class="password-toggle" @click="togglePasswordVisibility">
                <svg v-if="passwordFieldType === 'password'" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
              </button>
            </div>
          </div>

          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <button type="submit" :disabled="!isFormValid || loading" :class="{ 'loading': loading }">
            <span v-if="loading">
              <div class="spinner"></div>
            </span>
            <span v-else>Login</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Login',
  data() {
    return {
      form: {
        email: '',
        password: ''
      },
      loading: false,
      error: null,
      passwordFieldType: 'password',
      phrases: [
        'bot Telegram Anda',
        'data pengguna',
        'laporan harian',
        'laporan mingguan',
        'laporan bulanan'
      ],
      typedText: '',
      phraseIndex: 0,
      charIndex: 0,
      isDeleting: false,
      typingSpeed: 100,
      deletingSpeed: 50,
      delayBetweenPhrases: 2000 
    };
  },
  computed: {
    isFormValid() {
      return this.form.email.length > 0 && this.form.password.length > 0;
    }
  },
  mounted() {
    this.typingEffect();
  },
  methods: {
    togglePasswordVisibility() {
      this.passwordFieldType = this.passwordFieldType === 'password' ? 'text' : 'password';
    },
    typingEffect() {
      const currentPhrase = this.phrases[this.phraseIndex];
      let timeoutSpeed = this.typingSpeed;

      if (this.isDeleting) {
        this.typedText = currentPhrase.substring(0, this.charIndex - 1);
        this.charIndex--;
        timeoutSpeed = this.deletingSpeed;
        
        if (this.typedText === '') {
          this.isDeleting = false;
          this.phraseIndex = (this.phraseIndex + 1) % this.phrases.length;
          timeoutSpeed = 500; 
        }
      } 
      else {
        this.typedText = currentPhrase.substring(0, this.charIndex + 1);
        this.charIndex++;

        if (this.typedText === currentPhrase) {
          this.isDeleting = true;
          timeoutSpeed = this.delayBetweenPhrases; 
        }
      }

      setTimeout(this.typingEffect, timeoutSpeed);
    },
    async handleLogin() {
      this.error = null;
      this.loading = true;

      try {
        await new Promise(resolve => setTimeout(resolve, 2000));
        if (this.form.email === 'user@example.com' && this.form.password === 'password') {
          alert('Login Berhasil!');
        } else {
          throw new Error('Email atau password salah.');
        }
      } catch (err) {
        this.error = err.message;
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
<style scoped>
:root {
  --primary-color: #4a90e2;
  --primary-color-dark: #357ABD;
  --text-color: #333;
  --text-color-light: #fff;
  --border-color: #ddd;
  --error-color: #e74c3c;
}

.login-container {
  display: flex;
  width: 100%;
  min-height: 100vh;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  
  background-image:
    linear-gradient(rgba(10, 25, 47, 0.7), rgba(10, 25, 47, 0.7)),
    url('/images/background-login.jpg');
  
  background-size: cover; 
  background-position: center;
}

.left-pane {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 40px;
  color: var(--text-color-light);
  background: transparent; 
  text-align: center;
}
.welcome-text h1 {
  color: white;
  font-size: 3rem;
  margin-bottom: 20px;
  font-weight: 300;
  text-shadow: 2px 2px 8px rgba(0,0,0,0.5); 
}
.welcome-text p {
  color: white;
  font-size: 1.1rem;
  line-height: 1.6;
  max-width: 450px;
  opacity: 0.9;
  text-shadow: 1px 1px 4px rgba(0,0,0,0.5);
  height: 50px; 
}

.typing-text {
  font-weight: bold;
  color: #f1c40f; 
  border-right: .15em solid #f1c40f;
  animation: blink-caret .75s step-end infinite;
}

@keyframes blink-caret {
  from, to { border-color: transparent }
  50% { border-color: #f1c40f; }
}
.welcome-illustration {
  margin-top: 40px;
}
.welcome-illustration svg {
  stroke: var(--text-color-light);
  opacity: 0.8;
  filter: drop-shadow(0px 0px 10px rgba(0,0,0,0.5)); 
}

.right-pane {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 40px;
}

.login-box {
  background-color: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 400px;
  animation: fadeIn 0.8s ease-in-out;
  transition: transform 0.3s ease, box-shadow 0.3s ease;

}

.login-box:hover {
  transform: translateY(-10px); 
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.logo {
  font-size: 3rem;
  margin-bottom: 20px;
}

.login-title {
  color: var(--text-color);
  font-size: 2rem;
  font-weight: 600;
}

.login-subtitle {
  color: #666;
  margin-bottom: 30px;
}

.form-group {
  margin-bottom: 25px;
  text-align: left;
}
.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
}
.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}
.input-wrapper svg:not(.password-toggle-icon) {
  position: absolute;
  left: 15px;
  color: #aaa;
  z-index: 10;
}

.input-wrapper input {
  width: 100%;
  padding: 12px 12px 12px 45px; 
  border: 1px solid var(--border-color);
  border-radius: 8px;
  font-size: 1rem;
  transition: all 0.3s ease;
}
.input-wrapper input:focus {
  border-color: var(--primary-color);
  outline: none;
  box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.2);
}
.password-toggle {
  position: absolute;
  right: 0;
  top: 50%;
  transform: translateY(-50%);
  
  height: 100%;
  width: 45px; 
  
  background: transparent;
  border: none;
  cursor: pointer;

  display: flex;
  justify-content: center;
  align-items: center;
}

.error-message {
  color: var(--error-color);
  background-color: rgba(231, 76, 60, 0.1);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
  animation: slideDown 0.5s ease-out;
}

button[type="submit"] {
  width: 100%;
  padding: 14px;
  background-color: var(--primary-color);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.3s ease;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 50px;
}
button[type="submit"]:hover:not(:disabled) {
  background-color: var(--primary-color-dark);
}
button[type="submit"]:disabled {
  background-color: #a0ccec;
  cursor: not-allowed;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.spinner {
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top: 3px solid #fff;
  width: 20px;
  height: 20px;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .left-pane {
    display: none;
  }
  .right-pane {
    padding: 20px;
  }
}
</style>