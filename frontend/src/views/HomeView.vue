<script setup>
import { onMounted, ref } from 'vue'
import { apiGet } from '@/api/client'

// 'loading' | 'ok' | 'error'
const apiStatus = ref('loading')

onMounted(async () => {
  try {
    const data = await apiGet('/health')
    apiStatus.value = data.status === 'ok' ? 'ok' : 'error'
  } catch {
    apiStatus.value = 'error'
  }
})
</script>

<template>
  <section>
    <h1>Tervetuloa JalaOpsiin</h1>
    <p>Pieni sovellus huoltopyyntöjen hallintaan.</p>

    <p class="api-status" :class="apiStatus">
      <template v-if="apiStatus === 'loading'">Tarkistetaan API-yhteyttä…</template>
      <template v-else-if="apiStatus === 'ok'">API-yhteys toimii.</template>
      <template v-else>API-yhteys ei toimi.</template>
    </p>
  </section>
</template>

<style scoped>
.api-status.ok {
  color: #1a7f37;
}

.api-status.error {
  color: #cf222e;
}
</style>
