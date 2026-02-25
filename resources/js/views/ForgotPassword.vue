<template>
  <div class="login-container">
    <div class="right-pane">
      <div class="login-box" v-if="!showVerificationStep">
        <div class="logo">🔐</div>
        <h2 class="login-title">Forgot Password</h2>
        <p class="login-subtitle">Enter your email to receive a verification code on Telegram.</p>

        <form @submit.prevent="handleInitiate">
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrapper">
              <input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="example@email.com"
                required
                :disabled="loading"
              />
            </div>
          </div>

          <div v-if="error" class="error-message">{{ error }}</div>

          <button type="submit" :disabled="!form.email || loading" :class="{ loading }">
            <span v-if="loading"><div class="spinner"></div></span>
            <span v-else>Send Verification Code</span>
          </button>

          <p class="helper-link">
            Back to
            <router-link to="/">Login</router-link>
          </p>
        </form>
      </div>

      <div class="login-box" v-else>
        <div class="logo">✅</div>
        <h2 class="login-title">Reset Password</h2>
        <p class="login-subtitle">Enter code from Telegram and set your new password.</p>

        <form @submit.prevent="handleVerify">
          <div class="form-group">
            <label for="verification_code">Verification Code</label>
            <div class="input-wrapper">
              <input
                id="verification_code"
                v-model="verifyForm.code"
                type="text"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                required
                :disabled="loading"
                @input="verifyForm.code = verifyForm.code.replace(/[^0-9]/g, '')"
              />
            </div>
          </div>

          <div class="form-group">
            <label for="password">New Password</label>
            <div class="input-wrapper">
              <input
                id="password"
                v-model="verifyForm.password"
                :type="passwordFieldType"
                placeholder="••••••••"
                required
                :disabled="loading"
              />
            </div>
          </div>

          <div class="form-group">
            <label for="password_confirmation">Confirm New Password</label>
            <div class="input-wrapper">
              <input
                id="password_confirmation"
                v-model="verifyForm.password_confirmation"
                :type="passwordFieldType"
                placeholder="••••••••"
                required
                :disabled="loading"
              />
            </div>
          </div>

          <div v-if="error" class="error-message">{{ error }}</div>
          <div v-if="successMessage" class="success-message">{{ successMessage }}</div>

          <button
            type="submit"
            :disabled="!canSubmitVerify || loading"
            :class="{ loading }"
          >
            <span v-if="loading"><div class="spinner"></div></span>
            <span v-else>Reset Password</span>
          </button>

          <button type="button" class="btn-back" :disabled="loading" @click="backToEmailStep">
            Back
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ForgotPassword',
  data() {
    return {
      loading: false,
      error: null,
      successMessage: null,
      showVerificationStep: false,
      passwordFieldType: 'password',
      form: {
        email: '',
      },
      verifyForm: {
        code: '',
        password: '',
        password_confirmation: '',
      },
    }
  },
  computed: {
    canSubmitVerify() {
      return (
        this.verifyForm.code.length === 6 &&
        this.verifyForm.password.length >= 6 &&
        this.verifyForm.password_confirmation.length >= 6
      )
    },
  },
  methods: {
    async handleInitiate() {
      this.loading = true
      this.error = null
      this.successMessage = null

      try {
        const email = this.form.email.trim().toLowerCase()
        const response = await axios.post('/api/forgot-password/initiate', { email })

        if (response.data?.status === 'pending_verification' || response.data?.status === 'ok') {
          this.showVerificationStep = true
          this.successMessage = null
        }
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to send verification code.'
      } finally {
        this.loading = false
      }
    },

    async handleVerify() {
      this.loading = true
      this.error = null
      this.successMessage = null

      try {
        const email = this.form.email.trim().toLowerCase()
        const response = await axios.post('/api/forgot-password/verify', {
          email,
          code: this.verifyForm.code.trim(),
          password: this.verifyForm.password,
          password_confirmation: this.verifyForm.password_confirmation,
        })

        this.successMessage = response.data?.message || 'Password reset successful.'

        setTimeout(() => {
          window.location.href = '/'
        }, 1200)
      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to reset password.'
      } finally {
        this.loading = false
      }
    },

    backToEmailStep() {
      this.showVerificationStep = false
      this.verifyForm = {
        code: '',
        password: '',
        password_confirmation: '',
      }
      this.error = null
      this.successMessage = null
    },
  },
}
</script>

<style scoped>
.login-container {
  display: flex;
  width: 100%;
  min-height: 100vh;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  justify-content: center;
  align-items: center;
}

.right-pane {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 40px;
  width: 100%;
}

.login-box {
  background-color: #fff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 420px;
}

.logo {
  font-size: 2.5rem;
  margin-bottom: 16px;
  text-align: center;
}

.login-title {
  color: #333;
  font-size: 1.75rem;
  font-weight: 600;
  text-align: center;
}

.login-subtitle {
  color: #666;
  margin-bottom: 24px;
  text-align: center;
}

.form-group {
  margin-bottom: 18px;
  text-align: left;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #555;
  font-weight: 500;
}

.input-wrapper input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 1rem;
}

button[type='submit'] {
  width: 100%;
  background: #4a90e2;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 12px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
}

button[type='submit']:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-back {
  width: 100%;
  margin-top: 10px;
  background: #f1f1f1;
  border: 1px solid #ddd;
  color: #444;
  border-radius: 8px;
  padding: 12px;
  cursor: pointer;
}

.error-message {
  background-color: #fdeaea;
  color: #e74c3c;
  border: 1px solid #e74c3c;
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 16px;
}

.success-message {
  background-color: #e8f7ef;
  color: #1f9254;
  border: 1px solid #1f9254;
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 16px;
}

.helper-link {
  margin-top: 14px;
  text-align: center;
  color: #555;
}

.helper-link a {
  color: #4a90e2;
  text-decoration: none;
}

.spinner {
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top: 2px solid #fff;
  border-radius: 50%;
  width: 16px;
  height: 16px;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
