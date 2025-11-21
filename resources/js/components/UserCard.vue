<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useMainStore } from '@/stores/main'
import { mdiCheckDecagram, mdiClose } from '@mdi/js'
import BaseLevel from '@/components/BaseLevel.vue'
import UserAvatarCurrentUser from '@/components/UserAvatarCurrentUser.vue'
import CardBox from '@/components/CardBox.vue'
import FormCheckRadio from '@/components/FormCheckRadio.vue'
import PillTag from '@/components/PillTag.vue'
import BaseIcon from '@/components/BaseIcon.vue'

const mainStore = useMainStore()

const userName = computed(() => mainStore.userName)
const userAvatar = computed(() => mainStore.userAvatar)

const isModalActive = ref(false)

const openModal = () => {
  isModalActive.value = true
}

const closeModal = () => {  
  isModalActive.value = false
}

const userSwitchVal = ref(false)

const handleKeydown = (e) => {
  if (e.key === 'Escape' && isModalActive.value) {
    closeModal()
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <CardBox>
    <BaseLevel type="justify-around lg:justify-center">
      <div @click="openModal" class="cursor-pointer transition-transform hover:scale-105" title="Klik untuk memperbesar">
      <UserAvatarCurrentUser class="lg:mx-12" />
      </div>
      <div class="space-y-3 text-center md:text-left lg:mx-12">
        <div class="flex justify-center md:block">
          <FormCheckRadio
            v-model="userSwitchVal"
            name="notifications-switch"
            type="switch"
            label="Notifications"
            :input-value="true"
          />
        </div>
        <h1 class="text-2xl">
          Howdy, <b>{{ userName }}</b
          >!
        </h1>
        <p>Last login <b>12 mins ago</b> from <b>127.0.0.1</b></p>
        <div class="flex justify-center md:block">
          <PillTag label="Verified" color="info" :icon="mdiCheckDecagram" />
        </div>
      </div>
    </BaseLevel>
  </CardBox>
  <div v-if="isModalActive" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="closeModal">
    
    <button @click="closeModal" class="absolute top-5 right-5 text-white hover:text-gray-300 transition">
      <BaseIcon :path="mdiClose" size="36" />
    </button>

    <div class="relative max-w-3xl w-full">
      <img 
        :src="userAvatar" 
        alt="Full Avatar" 
        class="w-full h-auto rounded-lg shadow-2xl border-4 border-white/20"
      >
    </div>
  </div>
</template>
