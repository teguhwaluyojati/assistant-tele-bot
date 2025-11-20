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

const submitProfile = async () => {
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

    alert('Profile updated successfully!')
    
    avatarFile.value = null

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
    alert(errorMessage)
  }
}

const submitPass = async() => {
  try{
    const response = await axios.post('/api/change-password',{
      current_password: passwordForm.password_current,
      new_password: passwordForm.password,
      new_password_confirmation: passwordForm.password_confirmation,
    })

    console.log('Password changed successfully:', response.data)

    alert('Password changed successfully!')
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
</template>
