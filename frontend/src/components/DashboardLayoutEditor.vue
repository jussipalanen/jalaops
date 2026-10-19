<script setup>
import { HIDDEN } from '@/composables/useDashboardWidgets'

// "Muokkaa etusivua": where each module widget is shown on this user's
// dashboard, or whether it's hidden. Saved in this browser only.

defineProps({
  hooks: { type: Array, required: true },
  widgets: { type: Array, required: true },
  customized: { type: Boolean, default: false },
})

const emit = defineEmits(['place', 'reset', 'close'])
</script>

<template>
  <section class="card card-body" aria-labelledby="dashboard-layout-title">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h2 id="dashboard-layout-title" class="text-lg font-semibold">Muokkaa etusivua</h2>
      <button type="button" class="btn btn-secondary" @click="emit('close')">Valmis</button>
    </div>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
      Valitse, missä kohtaa etusivua moduulien sisältö näkyy. Valinta tallentuu vain tähän selaimeen. Moduuleja otetaan
      käyttöön kohdassa Työkalut.
    </p>

    <ul class="mt-4 grid gap-3">
      <li
        v-for="widget in widgets"
        :key="widget.id"
        class="grid gap-2 rounded-xl border border-slate-200 px-4 py-3 sm:grid-cols-[1fr_16rem] sm:items-center dark:border-slate-800"
      >
        <label :for="`place-${widget.id}`" class="min-w-0">
          <span class="block font-semibold">{{ widget.title }}</span>
          <span class="block text-sm text-slate-500 dark:text-slate-400">{{ widget.module_name }}</span>
        </label>
        <select
          :id="`place-${widget.id}`"
          class="form-control"
          :value="widget.place"
          @change="emit('place', widget, $event.target.value)"
        >
          <option v-for="hook in hooks" :key="hook.id" :value="hook.id">
            {{ hook.label }}{{ hook.id === widget.hook ? ' (oletus)' : '' }}
          </option>
          <option :value="HIDDEN">Piilotettu</option>
        </select>
      </li>
    </ul>

    <div v-if="customized" class="mt-4">
      <button type="button" class="btn btn-secondary" @click="emit('reset')">Palauta oletukset</button>
    </div>
  </section>
</template>
