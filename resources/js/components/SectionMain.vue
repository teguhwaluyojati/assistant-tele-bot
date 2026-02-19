<script setup>
import { containerMaxW } from '@/config.js'
</script>

<template>
  <section class="px-4 py-6" :class="containerMaxW">
    <slot />
  </section>
</template>
