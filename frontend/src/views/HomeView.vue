<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
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
    <div class="card hero">
      <div class="card-body">
        <h1 class="page-title">Tervetuloa JalaOpsiin</h1>
        <p class="page-subtitle">Pieni sovellus huoltopyyntöjen hallintaan.</p>

        <p class="api-status" :class="apiStatus">
          <template v-if="apiStatus === 'loading'">Tarkistetaan API-yhteyttä…</template>
          <template v-else-if="apiStatus === 'ok'">API-yhteys toimii.</template>
          <template v-else>API-yhteys ei toimi.</template>
        </p>

        <RouterLink to="/requests" class="btn btn-primary">Näytä pyynnöt</RouterLink>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hero {
  background: linear-gradient(135deg, var(--color-surface) 40%, var(--color-primary-soft));
}

.hero .page-subtitle {
  margin-top: 0.5rem;
}

.api-status {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  margin: 1.25rem 0;
  font-weight: 600;
}

.api-status::before {
  content: '';
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 50%;
  background: var(--color-muted);
}

.api-status.ok {
  color: var(--color-success);
}

.api-status.ok::before {
  background: var(--color-success);
}

.api-status.error {
  color: var(--color-danger);
}

.api-status.error::before {
  background: var(--color-danger);
}

.hero .btn {
  display: flex;
  width: 100%;
}

@media (min-width: 640px) {
  .hero .btn {
    display: inline-flex;
    width: auto;
  }
}
</style>
