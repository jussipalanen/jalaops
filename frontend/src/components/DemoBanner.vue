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
  <div v-if="demo" class="demo-banner" role="status">
    <strong>Demotila:</strong> voit kokeilla vapaasti. Muutokset eivät tallennu pysyvästi, vaan
    demodata palautuu, kun palvelin käynnistyy uudelleen.
  </div>
</template>

<style scoped>
.demo-banner {
  padding: 0.625rem var(--page-gutter);
  background: var(--color-primary-soft);
  border-bottom: 1px solid #bae6fd;
  color: var(--color-primary-hover);
  font-size: 0.875rem;
  text-align: center;
}
</style>
