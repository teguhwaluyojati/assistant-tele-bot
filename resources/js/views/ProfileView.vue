<script setup>
import { computed, reactive, ref } from 'vue'
import { useMainStore } from '@/stores/main'
import { mdiAccount, mdiMail, mdiAsterisk, mdiFormTextboxPassword, mdiCheckCircle, mdiAlertCircle, mdiInformation } from '@mdi/js'
import SectionMain from '@/components/SectionMain.vue'
import CardBox from '@/components/CardBox.vue'
import BaseDivider from '@/components/BaseDivider.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'
import FormFilePicker from '@/components/FormFilePicker.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import UserCard from '@/components/UserCard.vue'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import axios from 'axios'
import { useActionToast } from '@/composables/useActionToast'

const mainStore = useMainStore()

const profileForm = reactive({
  name: mainStore.userName,
  email: mainStore.userEmail,
})

const passwordForm = reactive({
  password_current: '',
  password: '',
  password_confirmation: '',
})

const avatarFile = ref(null);
const handleFileUpload = (event) => {
  avatarFile.value = event.target.files[0];
};

const isLoading = ref(false);
const { toast: profileToast, runAction } = useActionToast(3000)

const profileToastClass = computed(() => {
  if (profileToast.value.type === 'success') {
    return 'bg-emerald-500'
  }

  if (profileToast.value.type === 'info') {
    return 'bg-blue-500'
  }

  return 'bg-red-500'
})

const profileToastIcon = computed(() => {
  if (profileToast.value.type === 'success') {
    return mdiCheckCircle
  }

  if (profileToast.value.type === 'info') {
    return mdiInformation
  }

  return mdiAlertCircle
})

const submitProfile = async () => {
  
  isLoading.value = true;

  try {
    const formData = new FormData()

    formData.append('name', profileForm.name)
    formData.append('email', profileForm.email)

    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value)
    }

    formData.append('_method', 'PUT')

    const { ok, result } = await runAction(
      () => axios.post('/api/update-profile', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      }),
      {
        successMessage: 'Profile updated successfully.',
        errorPrefix: 'Failed to update profile',
        fallbackMessage: 'Terjadi kesalahan saat memperbarui profil.',
        onError: (error) => {
          console.error('Error updating profile:', error)
        },
      }
    )

    if (!ok) {
      return
    }

    console.log('Profile updated successfully:', result.data)
    mainStore.setUser(result.data.user)
    localStorage.setItem('user', JSON.stringify(result.data.user))
    avatarFile.value = null
  } finally {
    isLoading.value = false;
  }
}

const submitPass = async() => {

  isLoading.value = true;

  try{
    const { ok, result } = await runAction(
      () => axios.post('/api/change-password',{
        current_password: passwordForm.password_current,
        new_password: passwordForm.password,
        new_password_confirmation: passwordForm.password_confirmation,
      }),
      {
        successMessage: 'Password updated successfully.',
        errorPrefix: 'Failed to update password',
        fallbackMessage: 'Something went wrong while changing password.',
        onError: (error) => {
          console.error('Error changing password:', error)
        },
      }
    )

    if (!ok) {
      return
    }

    console.log('Password changed successfully:', result.data)
    passwordForm.password_current = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } finally{
    isLoading.value = false;
  }
}
</script>

<template>
  <LayoutAuthenticated>
    <SectionMain>
      <SectionTitleLineWithButton :icon="mdiAccount" title="Profile" main>
      </SectionTitleLineWithButton>

      <UserCard class="mb-6" />

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <CardBox is-form @submit.prevent="submitProfile">
          <FormField label="Avatar" help="Max 500kb">
            <FormFilePicker 
            label="Upload" 
            @change="handleFileUpload"
            accept="image/*"
            />
          </FormField>

          <FormField label="Name" help="Required. Your name">
            <FormControl
              v-model="profileForm.name"
              :icon="mdiAccount"
              name="username"
              required
              autocomplete="username"
            />
          </FormField>
          <FormField label="E-mail" help="Required. Your e-mail">
            <FormControl
              v-model="profileForm.email"
              :icon="mdiMail"
              type="email"
              name="email"
              required
              autocomplete="email"
            />
          </FormField>

          <template #footer>
            <BaseButton color="info" type="submit" label="Submit" />
          </template>
        </CardBox>

        <CardBox is-form @submit.prevent="submitPass">
          <FormField label="Current password" help="Required. Your current password">
            <FormControl
              v-model="passwordForm.password_current"
              :icon="mdiAsterisk"
              name="password_current"
              type="password"
              required
              autocomplete="current-password"
            />
          </FormField>

          <BaseDivider />

          <FormField label="New password" help="Required. New password">
            <FormControl
              v-model="passwordForm.password"
              :icon="mdiFormTextboxPassword"
              name="password"
              type="password"
              required
              autocomplete="new-password"
            />
          </FormField>

          <FormField label="Confirm password" help="Required. New password one more time">
            <FormControl
              v-model="passwordForm.password_confirmation"
              :icon="mdiFormTextboxPassword"
              name="password_confirmation"
              type="password"
              required
              autocomplete="new-password"
            />
          </FormField>

          <template #footer>
            <BaseButton type="submit" color="info" label="Submit" />
          </template>
        </CardBox>
      </div>
    </SectionMain>
  </LayoutAuthenticated>
<div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm cursor-wait">
      <div class="bg-white dark:bg-slate-800 p-6 rounded-lg shadow-xl flex flex-col items-center transform transition-all scale-100">
        <svg class="animate-spin h-10 w-10 text-blue-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Memproses...</h3>
      </div>
    </div>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-2"
    >
      <div
        v-if="profileToast.visible"
        class="fixed top-20 right-4 z-50 px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 text-white"
        :class="profileToastClass"
      >
        <BaseIcon :path="profileToastIcon" size="18" />
        <span class="text-sm font-medium">{{ profileToast.message }}</span>
      </div>
    </transition>
</template>
