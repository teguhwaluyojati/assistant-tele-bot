<script setup>
defineProps({
  noPadding: Boolean,
})
</script>

<template>
  <div class="flex-1 text-gray-900 dark:text-gray-100" :class="{ 'p-6': !noPadding }">
    <slot />
  </div>
</template>
