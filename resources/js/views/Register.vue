<template>
  <div class="login-container">
    <div class="left-pane">
      <div class="welcome-text">
        <h1>Join Now</h1>
        <p>Create an account to access
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
      <!-- Registration Form -->
      <div class="login-box" v-if="!showVerificationModal">
        <div class="logo">
          🤖
        </div>
        <h2 class="login-title">Create New Account</h2>
        <p class="login-subtitle">Fill in the form below to create an account.</p>

        <form @submit.prevent="handleRegister">
          <div class="form-group">
            <label for="name">Full Name</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              <input type="text" id="name" v-model="form.name" placeholder="Full Name" required :disabled="loading" />
            </div>
          </div>

          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              <input type="email" id="email" v-model="form.email" placeholder="example@email.com" required :disabled="loading" />
            </div>
          </div>

          <div class="form-group">
            <label for="telegram_username">Telegram Username</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              <input type="text" id="telegram_username" v-model="form.telegram_username" placeholder="@username" required :disabled="loading" />
            </div>
            <small class="help-text">Your Telegram username (without @)</small>
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

          <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <div class="input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <input :type="passwordFieldType" id="password_confirmation" v-model="form.password_confirmation" placeholder="••••••••" required :disabled="loading" />
            </div>
          </div>

          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <button type="submit" :disabled="!isFormValid || loading" :class="{ 'loading': loading }">
            <span v-if="loading">
              <div class="spinner"></div>
            </span>
            <span v-else>Proceed to Verification</span>
          </button>

          <p class="login-link">
            Already have an account? 
            <router-link to="/">Login here!</router-link>
          </p>
        </form>
      </div>

      <!-- Verification Modal -->
      <div class="login-box" v-else>
        <div class="logo">
          🔐
        </div>
        <h2 class="login-title">Verify Your Account</h2>
        <p class="login-subtitle">Enter the 6-digit code sent to your Telegram</p>

        <form @submit.prevent="handleVerify">
          <div class="form-group">
            <label for="verification_code">Verification Code</label>
            <div class="input-wrapper verify-input-wrapper">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
              <input 
                type="text" 
                id="verification_code" 
                v-model="verificationCode" 
                placeholder="000000" 
                maxlength="6"
                inputmode="numeric"
                required 
                :disabled="loading"
                @input="verificationCode = verificationCode.replace(/[^0-9]/g, '')"
              />
            </div>
            <small class="help-text">Check your Telegram for the code. It expires in 15 minutes.</small>
          </div>

          <div v-if="verificationError" class="error-message">
            {{ verificationError }}
          </div>

          <button type="submit" :disabled="verificationCode.length !== 6 || loading" :class="{ 'loading': loading }">
            <span v-if="loading">
              <div class="spinner"></div>
            </span>
            <span v-else>Verify & Complete Registration</span>
          </button>

          <button type="button" @click="backToForm" :disabled="loading" class="btn-back">
            Back to Form
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'Register',
  data() {
    return {
      form: {
        name: '',
        email: '',
        telegram_username: '',
        password: '',
        password_confirmation: ''
      },
      loading: false,
      error: null,
      passwordFieldType: 'password',
      showVerificationModal: false,
      verificationCode: '',
      verificationError: null,
      phrases: [
        'your Telegram bot',
        'user data',
        'daily reports',
        'weekly reports',
        'monthly reports'
      ],
      typedText: '',
      phraseIndex: 0,
      charIndex: 0,
      isDeleting: false,
      typingSpeed: 100,
      deletingSpeed: 50,
      delayBetweenPhrases: 2000,
      _typingTimer: null,
    };
  },
  computed: {
    isFormValid() {
      return (
        this.form.name.length > 0 &&
        this.form.email.length > 0 &&
        this.form.telegram_username.length > 0 &&
        this.form.password.length > 0 &&
        this.form.password_confirmation.length > 0 &&
        this.form.password === this.form.password_confirmation
      );
    }
  },
  mounted() {
    this.typingEffect();
  },
  beforeUnmount() {
    if (this._typingTimer) {
      clearTimeout(this._typingTimer);
    }
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

      this._typingTimer = setTimeout(this.typingEffect, timeoutSpeed);
    },
    async handleRegister() {
      this.error = null;
      this.loading = true;
      
      if (this.form.password !== this.form.password_confirmation) {
        this.error = 'Passwords do not match.';
        this.loading = false;
        return;
      }

      try {
        const response = await axios.post('/api/register/initiate', {
          name: this.form.name,
          email: this.form.email,
          telegram_username: this.form.telegram_username.replace('@', ''),
          password: this.form.password,
          password_confirmation: this.form.password_confirmation,
        });
        
        console.log('Registration initiated:', response.data);
        this.showVerificationModal = true;
        this.verificationCode = '';
        this.verificationError = null;

      } catch (error) {
        if (error.response) {
          this.error = error.response.data.message || 'Registration initiation failed. Please try again.';
          console.error('Register error response:', error.response.data);
        } else if (error.request) {
          this.error = 'Unable to connect to server. Please try again.';
          console.error('Register error request:', error.request);
        } else {
          this.error = 'An error occurred. Please reload the page.';
          console.error('Register error:', error.message);
        }
      } finally {
        this.loading = false;
      }
    },
    async handleVerify() {
      this.verificationError = null;
      this.loading = true;

      try {
        const response = await axios.post('/api/register/verify', {
          email: this.form.email,
          code: this.verificationCode,
        });

        localStorage.setItem('user', JSON.stringify(response.data.user));

        console.log('Registration successful:', response.data);

        window.location.href = '/dashboard';

      } catch (error) {
        if (error.response) {
          this.verificationError = error.response.data.message || 'Invalid verification code. Please try again.';
          console.error('Verify error response:', error.response.data);
        } else if (error.request) {
          this.verificationError = 'Unable to connect to server. Please try again.';
          console.error('Verify error request:', error.request);
        } else {
          this.verificationError = 'An error occurred. Please reload the page.';
          console.error('Verify error:', error.message);
        }
      } finally {
        this.loading = false;
      }
    },
    backToForm() {
      this.showVerificationModal = false;
      this.verificationCode = '';
      this.verificationError = null;
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
  
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  background-attachment: fixed;
  justify-content: center;
  align-items: center;
}

.left-pane {
  display: none;
  flex: 1;
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
  margin-top: 50px;
  color: rgba(255, 255, 255, 0.6);
}

.right-pane {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
  background: transparent;
  width: 100%;
  max-width: 500px;
}

.login-box {
  width: 100%;
  max-width: 400px;
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.logo {
  text-align: center;
  font-size: 3rem;
  margin-bottom: 20px;
}

.login-title {
  text-align: center;
  color: var(--text-color);
  margin-bottom: 10px;
  font-size: 1.6rem;
  font-weight: 600;
}

.login-subtitle {
  text-align: center;
  color: #999;
  margin-bottom: 30px;
  font-size: 0.9rem;
}

form {
  display: flex;
  flex-direction: column;
}

.form-group {
  margin-bottom: 18px;
}

label {
  display: block;
  color: var(--text-color);
  margin-bottom: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #555;
}

.input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  border: 2px solid var(--border-color);
  border-radius: 10px;
  padding: 0 12px;
  transition: all 0.3s ease;
  background-color: #f8f9fa;
}

.input-wrapper:focus-within {
  border-color: var(--primary-color);
  background-color: #fff;
  box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
}

.input-wrapper svg {
  color: #999;
  margin-right: 10px;
  flex-shrink: 0;
}

input {
  flex: 1;
  padding: 12px 0;
  border: none;
  background: transparent;
  font-size: 1rem;
  color: var(--text-color);
  outline: none;
}

input::placeholder {
  color: #ccc;
}
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
  display: none;
}

.password-toggle {
  background: none;
  border: none;
  cursor: pointer;
  color: #999;
  padding: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.3s ease;
}

.password-toggle:hover {
  color: #333;
}

.error-message {
  color: var(--error-color);
  background-color: rgba(231, 76, 60, 0.1);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 20px;
  font-size: 0.9rem;
  border-left: 4px solid var(--error-color);
  animation: slideDown 0.5s ease-out;
}

button[type="submit"] {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 48px;
  margin-top: 10px;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

button[type="submit"]:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

button[type="submit"].loading {
  cursor: not-allowed;
  opacity: 0.8;
}

button[type="submit"]:disabled {
  opacity: 0.6;
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

.login-link {
  text-align: center;
  margin-top: 20px;
  color: #666;
  font-size: 0.9rem;
}

.login-link a {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
}

.login-link a:hover {
  color: #764ba2;
  text-decoration: underline;
}

.help-text {
  display: block;
  font-size: 0.8rem;
  color: #999;
  margin-top: 4px;
  margin-left: 0;
}

.verify-input-wrapper {
  text-align: center;
}

.verify-input-wrapper input {
  text-align: center;
  letter-spacing: 0.3em;
  font-weight: 600;
  font-size: 1.2rem;
}

.btn-back {
  width: 100%;
  padding: 12px;
  background-color: #f0f0f0;
  color: #333;
  border: 1px solid #ddd;
  border-radius: 10px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
}

.btn-back:hover:not(:disabled) {
  background-color: #e0e0e0;
  border-color: #999;
}

.btn-back:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 1024px) {
  .left-pane {
    display: none;
  }

  .login-box {
    padding: 35px;
    max-width: 380px;
  }
}

@media (max-width: 768px) {
  .login-container {
    min-height: auto;
    padding: 20px 0;
  }

  .right-pane {
    padding: 15px;
    max-width: 100%;
  }

  .login-box {
    padding: 30px;
    max-width: 100%;
    border-radius: 12px;
  }

  .login-title {
    font-size: 1.4rem;
  }

  label {
    font-size: 0.85rem;
  }

  input {
    font-size: 1rem;
  }

  button[type="submit"] {
    height: 44px;
  }
}

@media (max-width: 480px) {
  .login-container {
    justify-content: stretch;
  }

  .right-pane {
    max-width: 100%;
    padding: 15px;
  }

  .login-box {
    padding: 20px;
    max-width: 100%;
    border-radius: 12px;
  }

  .logo {
    font-size: 2.5rem;
    margin-bottom: 15px;
  }

  .login-title {
    font-size: 1.3rem;
    margin-bottom: 8px;
  }

  .login-subtitle {
    margin-bottom: 20px;
    font-size: 0.85rem;
  }

  .form-group {
    margin-bottom: 14px;
  }

  label {
    font-size: 0.8rem;
  }

  input {
    font-size: 1rem;
  }

  .input-wrapper {
    padding: 0 10px;
  }

  .input-wrapper svg {
    width: 18px;
    height: 18px;
  }

  button[type="submit"] {
    height: 42px;
    font-size: 0.95rem;
  }

  .login-link {
    font-size: 0.85rem;
    margin-top: 15px;
  }
}
</style>
