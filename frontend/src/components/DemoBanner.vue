<script setup>
import { onMounted, ref } from 'vue'
import { apiGet } from '@/api/client'

const demo = ref(false)

onMounted(async () => {
  try {
    const health = await apiGet('/health')
    demo.value = health.demo === true
  } catch {
    // Without an answer from the API there is nothing to tell about demo mode.
  }
})
</script>

<template>
  <div
    v-if="demo"
    class="demo-banner border-b border-sky-200 bg-sky-50 px-4 py-2.5 text-center text-sm text-sky-800 sm:px-6 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200"
    role="status"
  >
    <strong>Demotila:</strong> voit kokeilla vapaasti. Muutokset eivät tallennu pysyvästi, vaan
    demodata palautuu, kun palvelin käynnistyy uudelleen.
  </div>
</template>
