<script setup>
import ModuleWidget from '@/components/ModuleWidget.vue'

// A place on a page where modules' widgets are shown (an action hook, e.g.
// "dashboard.after_counts"). Draws nothing when no widget is placed here.

defineProps({
  name: { type: String, required: true },
  widgets: { type: Array, default: () => [] },
})
</script>

<template>
  <div v-if="widgets.length" class="grid gap-6" :data-hook="name">
    <ModuleWidget v-for="widget in widgets" :key="widget.id" :widget="widget" />
  </div>
</template>
