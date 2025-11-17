<script setup>
import { mdiLogout, mdiClose } from '@mdi/js'
import { computed } from 'vue'
import AsideMenuList from '@/components/AsideMenuList.vue'
import AsideMenuItem from '@/components/AsideMenuItem.vue'
import BaseIcon from '@/components/BaseIcon.vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

defineProps({
  menu: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['menu-click', 'aside-lg-close-click'])
const router = useRouter()

const logoutItem = computed(() => ({
  label: 'Logout',
  icon: mdiLogout,
  color: 'info',
  isLogout: true,
}))


const menuClick = async (event, item) => {
  if (item.isLogout) {
    if (!confirm('Did you sure want to logout?')) {
      return
    }
    try {
      console.log('Attempting logout via /api/logout from AsideMenuLayer...');
      await axios.post('/api/logout');

      console.log('Logout successful on server. Clearing local data...');
      localStorage.clear();
      delete axios.defaults.headers.common['Authorization'];

      console.log('Redirecting to login page...');
      router.push('/');

    } catch (error) {
      console.error('Logout failed:', error);
      alert('Gagal untuk logout. Mungkin sesi Anda sudah berakhir.');
      localStorage.clear();
      delete axios.defaults.headers.common['Authorization'];
      router.push('/');
    }
  } else {
    emit('menu-click', event, item)
  }
}

const asideLgCloseClick = (event) => {
  emit('aside-lg-close-click', event)
}
</script>

<template>
  <aside
    id="aside"
    class="lg:py-2 lg:pl-2 w-60 fixed flex z-40 top-0 h-screen transition-position overflow-hidden"
  >
    <div class="aside lg:rounded-2xl flex-1 flex flex-col overflow-hidden bg-white dark:bg-slate-900">
      <div class="aside-brand flex flex-row h-14 items-center justify-between dark:bg-slate-900">
        <div class="text-center flex-1 lg:text-left lg:pl-6 xl:text-center xl:pl-0">
          <b class="font-black">My Asisstant</b>
        </div>
        <button class="hidden lg:inline-block xl:hidden p-3" @click.prevent="asideLgCloseClick">
          <BaseIcon :path="mdiClose" />
        </button>
      </div>
      <div
        class="flex-1 overflow-y-auto overflow-x-hidden aside-scrollbars dark:aside-scrollbars-[slate]-700"
      >
        <AsideMenuList :menu="menu" @menu-click="menuClick" />
      </div>

      <ul>
        <AsideMenuItem :item="logoutItem" @menu-click="menuClick" />
      </ul>
    </div>
  </aside>
</template>
