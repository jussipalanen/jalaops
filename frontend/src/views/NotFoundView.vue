<script setup>
import { RouterLink } from 'vue-router'

// An address with no page: a mistyped link, or a module's page while the
// module is off.
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <div>
        <h1 class="page-title">Sivua ei löytynyt</h1>
        <p class="page-subtitle mt-1">Osoitteessa ei ole sivua. Jos se on moduulin sivu, moduuli voi olla pois käytöstä.</p>
      </div>
    </header>

    <div class="flex flex-wrap gap-3">
      <RouterLink to="/" class="btn btn-primary">Etusivulle</RouterLink>
      <RouterLink to="/tools" class="btn btn-secondary">Työkalut</RouterLink>
    </div>
  </section>
</template>
