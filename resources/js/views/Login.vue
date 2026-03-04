<template>
  <div class="relative min-h-screen overflow-hidden bg-gray-50 dark:bg-slate-900">
    <div
      class="absolute inset-0 bg-cover bg-center"
      style="background-image: url('/images/background-login.jpg')"
    ></div>
    <div class="absolute inset-0 bg-gradient-to-br from-slate-900/75 via-slate-900/65 to-slate-800/70"></div>

    <div
      class="relative"
    >
    <div
      class="mx-auto grid min-h-screen max-w-7xl grid-cols-1 gap-8 px-4 py-8 sm:px-6 lg:grid-cols-2 lg:gap-12 lg:px-8 lg:py-12"
    >
      <section class="order-2 flex flex-col justify-center lg:order-1">
        <div
          class="rounded-2xl border border-gray-200 bg-white/95 p-6 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800/90 sm:p-8"
        >
          <p class="text-sm font-medium text-blue-600 dark:text-blue-400">
            Welcome to Assistant Tele Bot
          </p>
          <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">
            Financial control from Telegram to Dashboard
          </h1>
          <p class="mt-4 text-sm leading-6 text-gray-600 dark:text-slate-300 sm:text-base">
            Track transactions, monitor user activity, and manage roles in one secure workspace.
          </p>

          <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Core Features</h2>
              <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-slate-300">
                <li>Transaction tracking and export</li>
                <li>Dashboard analytics and insights</li>
                <li>User and role management</li>
                <li>Audit logging for key actions</li>
              </ul>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Access Roles</h2>
              <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-slate-300">
                <li>Superadmin: full system control</li>
                <li>Admin: operational management</li>
                <li>Member: personal finance access</li>
              </ul>
            </div>
          </div>

          <div class="mt-6 rounded-xl border border-gray-200 p-4 dark:border-slate-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">How it works</h2>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-slate-300">
              <li>Start the Telegram bot and register your account.</li>
              <li>Login to web dashboard with your credentials.</li>
              <li>Monitor activity and manage operations by role.</li>
            </ol>
          </div>
        </div>
      </section>

      <section class="order-1 flex items-center justify-center lg:order-2">
        <div
          class="w-full max-w-md rounded-2xl border border-gray-200 bg-white/95 p-6 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800/90 sm:p-8"
        >
          <div class="mb-6 text-center">
            <p class="text-3xl">🤖</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">Sign in</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Access your dashboard securely.</p>
          </div>

          <form class="space-y-4" @submit.prevent="handleLogin">
            <div>
              <label
                for="email"
                class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200"
              >
                Email
              </label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                placeholder="you@example.com"
                autocomplete="email"
                :disabled="loading"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                required
              />
            </div>

            <div>
              <label
                for="password"
                class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-200"
              >
                Password
              </label>
              <div class="relative">
                <input
                  id="password"
                  v-model="form.password"
                  :type="passwordFieldType"
                  placeholder="••••••••"
                  autocomplete="current-password"
                  :disabled="loading"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-12 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-70 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                  required
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed dark:text-slate-400 dark:hover:text-slate-200"
                  :disabled="loading"
                  @click="togglePasswordVisibility"
                >
                  {{ passwordFieldType === 'password' ? 'Show' : 'Hide' }}
                </button>
              </div>
            </div>

            <p
              v-if="error"
              class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300"
            >
              {{ error }}
            </p>

            <button
              type="submit"
              :disabled="!isFormValid || loading"
              class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <span v-if="loading">Signing in...</span>
              <span v-else>Login</span>
            </button>
          </form>

          <div class="mt-5 flex items-center justify-between text-sm">
            <router-link
              to="/forgot-password"
              class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
              Forgot password?
            </router-link>
            <router-link
              to="/register"
              class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
              Create account
            </router-link>
          </div>

          <p class="mt-5 text-center text-xs text-gray-500 dark:text-slate-400">
            Authenticated with Sanctum and activity logging enabled.
          </p>

          <p class="mt-2 text-center text-xs text-gray-500 dark:text-slate-400">
            Built by
            <a
              href="https://teguhwaluyojati.github.io"
              target="_blank"
              rel="noopener noreferrer"
              class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
            >
              Teguh Waluyojati
            </a>
          </p>
        </div>
      </section>
    </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'Login',
  data() {
    return {
      form: {
        email: '',
        password: '',
      },
      loading: false,
      error: null,
      passwordFieldType: 'password',
    }
  },
  computed: {
    isFormValid() {
      return this.form.email.length > 0 && this.form.password.length > 0
    },
  },
  methods: {
    togglePasswordVisibility() {
      this.passwordFieldType = this.passwordFieldType === 'password' ? 'text' : 'password'
    },
    async handleLogin() {
      this.error = null
      this.loading = true

      try {
        const response = await axios.post('/api/login', {
          email: this.form.email.trim().toLowerCase(),
          password: this.form.password,
        })

        localStorage.setItem('user', JSON.stringify(response.data.user))
        this.$router.replace('/dashboard')
      } catch (error) {
        if (error.response) {
          this.error = error.response.data.message || 'Email or password is incorrect.'
        } else if (error.request) {
          this.error = 'Unable to connect to server. Please try again.'
        } else {
          this.error = 'An error occurred. Please reload the page.'
        }
      } finally {
        this.loading = false
      }
    },
  },
}
</script>
