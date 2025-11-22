<script setup>
import { reactive, ref } from 'vue'
import { useMainStore } from '@/stores/main'
import { mdiAccount, mdiMail, mdiAsterisk, mdiFormTextboxPassword, mdiGithub } from '@mdi/js'
import SectionMain from '@/components/SectionMain.vue'
import CardBox from '@/components/CardBox.vue'
import BaseDivider from '@/components/BaseDivider.vue'
import FormField from '@/components/FormField.vue'
import FormControl from '@/components/FormControl.vue'
import FormFilePicker from '@/components/FormFilePicker.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseButtons from '@/components/BaseButtons.vue'
import UserCard from '@/components/UserCard.vue'
import LayoutAuthenticated from '@/layouts/LayoutAuthenticated.vue'
import SectionTitleLineWithButton from '@/components/SectionTitleLineWithButton.vue'
import axios from 'axios'

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

const alertState = reactive({
  show: false,
  message: '',
  type: 'success', 
  title: '',
});

const showAlert = (message, type = 'success', title = '') => {
  alertState.message = message;
  alertState.type = type;
  alertState.title = title;
  alertState.show = true;

  setTimeout(() => {
    alertState.show = false;
  }, 5000); 
};

const closeAlert = () => {
  alertState.show = false;
};

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

    const response = await axios.post('/api/update-profile', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })

    console.log('Profile updated successfully:', response.data)

    mainStore.setUser(response.data.user)

    localStorage.setItem('user', JSON.stringify(response.data.user))
    
    avatarFile.value = null

    showAlert('Profile updated successfully.', 'success', 'Success')

  } catch (error) {
    console.error('Error updating profile:', error)
    let errorMessage = 'Terjadi kesalahan saat memperbarui profil.'
    
    if (error.response) {
      if (error.response.status === 422) {
        errorMessage = error.response.data.message || 'Data not valid.'
      } else {
        errorMessage = error.response.data.message || errorMessage
      }
    }
    showAlert(errorMessage, 'error', 'Error')
  } finally {
    isLoading.value = false;
  }
}

const submitPass = async() => {

  isLoading.value = true;

  try{
    const response = await axios.post('/api/change-password',{
      current_password: passwordForm.password_current,
      new_password: passwordForm.password,
      new_password_confirmation: passwordForm.password_confirmation,
    })

    console.log('Password changed successfully:', response.data)
    showAlert('Password updated successfully.', 'success', 'Success')

  }catch(error){
    console.error('Error changing password:', error)
    let errorMessage = 'Something went wrong while changing password.';
    if(error.response){
      if(error.response.status === 422){
        errorMessage = error.response.data.message || 'Data not valid.';
      }else {
        errorMessage = error.response.data.message || errorMessage;
      }
    }
    alert(errorMessage);
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
            <BaseButtons>
              <BaseButton color="info" type="submit" label="Submit" />
              <BaseButton color="info" label="Options" outline />
            </BaseButtons>
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
            <BaseButtons>
              <BaseButton type="submit" color="info" label="Submit" />
              <BaseButton color="info" label="Options" outline />
            </BaseButtons>
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

    <div v-if="alertState.show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-2xl flex flex-col items-center text-center max-w-sm w-full border border-gray-100 dark:border-slate-700">
        
        <div v-if="alertState.type === 'success'" class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div v-else class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </div>

        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ alertState.title }}</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">{{ alertState.message }}</p>

        <button @click="closeAlert" class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
          Ok, got it!
        </button>
      </div>
    </div>
</template>
