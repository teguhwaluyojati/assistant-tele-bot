<script setup>
import { mdiForwardburger, mdiBackburger, mdiMenu } from '@mdi/js'
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import menuAside from '@/menuAside.js'
import menuNavBar from '@/menuNavBar.js'
import { useDarkModeStore } from '@/stores/darkMode.js'
import { useMainStore } from '@/stores/main'
import BaseIcon from '@/components/BaseIcon.vue'
import FormControl from '@/components/FormControl.vue'
import NavBar from '@/components/NavBar.vue'
import NavBarItemPlain from '@/components/NavBarItemPlain.vue'
import AsideMenu from '@/components/AsideMenu.vue'
import FooterBar from '@/components/FooterBar.vue'
import CardBoxModal from '@/components/CardBoxModal.vue'
import axios from 'axios'

const layoutAsidePadding = 'xl:pl-60'

const darkModeStore = useDarkModeStore()
const mainStore = useMainStore()

const router = useRouter()

const isAsideMobileExpanded = ref(false)
const isAsideLgActive = ref(false)
const isLogoutModalOpen = ref(false)

const isAdminUser = computed(() => mainStore.currentUser?.telegram_user?.level === 1)
const menuAsideFiltered = computed(() =>
  menuAside.filter((item) => !item.requiresAdmin || isAdminUser.value),
)

onMounted(async () => {
  if (!mainStore.currentUser) {
    await mainStore.fetchCurrentUser()
  }
})

router.beforeEach(() => {
  isAsideMobileExpanded.value = false
  isAsideLgActive.value = false
})

const handleLogout = () => {
  isLogoutModalOpen.value = true
}

const confirmLogout = async () => {
  try {
    await axios.post('/api/logout')
    localStorage.removeItem('user')
    router.push('/')
  } catch (error) {
    console.error('Logout failed:', error)
    localStorage.removeItem('user')
    router.push('/')
  }
}

const menuClick = (event, item) => {
  if (item.isToggleLightDark) {
    darkModeStore.set()
  }

  if (item.isLogout) {
    handleLogout()
  }else if(item.to){
    router.push(item.to);
  }
}
</script>

<template>
  <div
    v-bind="$attrs"
    :class="{
      'overflow-hidden lg:overflow-visible': isAsideMobileExpanded,
    }"
  >
    <div
      :class="[layoutAsidePadding, { 'ml-60 lg:ml-0': isAsideMobileExpanded }]"
      class="pt-14 min-h-screen w-screen transition-position lg:w-auto bg-gray-50 dark:bg-slate-800 dark:text-slate-100"
    >
      <NavBar
        :menu="menuNavBar"
        :class="[layoutAsidePadding, { 'ml-60 lg:ml-0': isAsideMobileExpanded }]"
        @menu-click="menuClick"
      >
        <NavBarItemPlain
          display="flex lg:hidden"
          @click.prevent="isAsideMobileExpanded = !isAsideMobileExpanded"
        >
          <BaseIcon :path="isAsideMobileExpanded ? mdiBackburger : mdiForwardburger" size="24" />
        </NavBarItemPlain>
        <NavBarItemPlain display="hidden lg:flex xl:hidden" @click.prevent="isAsideLgActive = true">
          <BaseIcon :path="mdiMenu" size="24" />
        </NavBarItemPlain>
        <NavBarItemPlain use-margin>
          <FormControl placeholder="Search (ctrl+k)" ctrl-k-focus transparent borderless />
        </NavBarItemPlain>
      </NavBar>
      <AsideMenu
        :is-aside-mobile-expanded="isAsideMobileExpanded"
        :is-aside-lg-active="isAsideLgActive"
        :menu="menuAsideFiltered"
        @menu-click="menuClick"
        @aside-lg-close-click="isAsideLgActive = false"
      />
      <slot />
      <FooterBar>
        Get more with
        <a href="https://tailwind-vue.justboil.me/" target="_blank" class="text-blue-600"
          >Premium version</a
        >
      </FooterBar>
    </div>

    <CardBoxModal
      v-model="isLogoutModalOpen"
      title="Confirm Logout"
      button="danger"
      buttonLabel="Yes, Logout"
      has-cancel
      @confirm="confirmLogout"
    >
      Are you sure you want to logout?
    </CardBoxModal>
  </div>
</template>
