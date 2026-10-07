<script setup>
import { RouterLink, RouterView } from 'vue-router'
import DemoBanner from '@/components/DemoBanner.vue'
</script>

<template>
  <DemoBanner />

  <header class="app-header">
    <div class="container header-inner">
      <RouterLink to="/" class="brand">
        <span class="brand-mark" aria-hidden="true">J</span>
        JalaOps
      </RouterLink>

      <nav class="app-nav" aria-label="Päävalikko">
        <RouterLink to="/" exact-active-class="active">Etusivu</RouterLink>
        <RouterLink to="/requests" active-class="active">Pyynnöt</RouterLink>
      </nav>
    </div>
  </header>

  <main class="app-main">
    <div class="container">
      <RouterView />
    </div>
  </main>
</template>

<style scoped>
.app-header {
  position: sticky;
  top: 0;
  z-index: 10;
  background: rgb(255 255 255 / 0.9);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--color-border);
}

.header-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  min-height: 64px;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-text);
  text-decoration: none;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-sm);
  background: linear-gradient(135deg, #38bdf8, var(--color-primary));
  color: #fff;
  font-size: 1rem;
}

.app-nav {
  display: flex;
  gap: 0.25rem;
}

.app-nav a {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 0 0.875rem;
  border-radius: 999px;
  color: var(--color-muted);
  font-weight: 600;
  text-decoration: none;
}

.app-nav a:hover {
  color: var(--color-text);
  background: var(--color-primary-softer);
}

.app-nav a.active {
  color: var(--color-primary-hover);
  background: var(--color-primary-soft);
}

.app-main {
  padding: 1.5rem 0 3rem;
}

@media (min-width: 640px) {
  .app-main {
    padding-top: 2rem;
  }
}
</style>
