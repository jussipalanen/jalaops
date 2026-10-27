<script setup>
import ModuleList from '@/components/ModuleList.vue'

// Settings for the whole app, for admins only (the router and the API both
// check the role).
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Asetukset</h1>
        <p class="page-subtitle mt-1">Sovelluksen asetukset kaikille käyttäjille. Vain ylläpitäjä voi muuttaa niitä.</p>
      </div>
    </header>

    <ModuleList editable />
  </section>
</template>
