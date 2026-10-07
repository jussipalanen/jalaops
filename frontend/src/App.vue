<script setup>
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <header class="app-header">
    <RouterLink to="/" class="brand">JalaOps</RouterLink>
  </header>

  <main class="app-main">
    <RouterView />
  </main>
</template>

<style scoped>
.app-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--color-border);
}

.brand {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-text);
  text-decoration: none;
}

.app-main {
  padding: 1.5rem;
}
</style>
