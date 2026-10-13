<script setup>
import { computed, useTemplateRef } from 'vue'
import { useDropdown } from '@/composables/useDropdown'

// A request or an invoice as a PDF or a self-contained HTML file: "Esikatsele"
// opens it in a new browser tab, "Lataa" saves it. "buttons" shows them side
// by side; "menu" is a compact "Tiedosto" button for list rows.
const props = defineProps({
  // The document's export address without the format, e.g. /api/requests/12/export.
  base: { type: String, required: true },
  title: { type: String, default: '' },
  variant: { type: String, default: 'buttons' },
})

// Previews first, then downloads.
const formats = ['pdf', 'html']
const ordered = computed(() => [
  ...formats.map((format) => ({ key: `${format}-preview`, preview: true, label: `Esikatsele ${format.toUpperCase()}`, href: `${props.base}/${format}?inline=1` })),
  ...formats.map((format) => ({ key: `${format}-download`, preview: false, label: `Lataa ${format.toUpperCase()}`, href: `${props.base}/${format}` })),
])

const menu = useTemplateRef('menu')
const { open, toggle, close } = useDropdown(menu)
</script>

<template>
  <div v-if="variant === 'menu'" ref="menu" class="relative">
    <button
      type="button"
      class="btn btn-secondary w-full"
      :aria-expanded="open"
      aria-haspopup="true"
      :aria-label="title ? `Tiedosto: ${title}` : 'Tiedosto'"
      @click="toggle"
    >
      Tiedosto <span class="text-xs" aria-hidden="true">▾</span>
    </button>
    <div
      v-show="open"
      class="absolute inset-x-0 z-20 mt-1 overflow-hidden sm:inset-x-auto sm:right-0 sm:min-w-48 rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <a
        v-for="link in ordered"
        :key="link.key"
        :href="link.href"
        :download="link.preview ? undefined : ''"
        :target="link.preview ? '_blank' : undefined"
        :rel="link.preview ? 'noopener' : undefined"
        class="flex min-h-11 items-center gap-1 px-4 font-semibold hover:bg-slate-100 lg:min-h-9 dark:hover:bg-slate-800"
        @click="close"
      >
        <span>{{ link.label }}<span v-if="link.preview" aria-hidden="true">&nbsp;↗</span></span>
        <span v-if="link.preview" class="sr-only">(avautuu uuteen välilehteen)</span>
      </a>
    </div>
  </div>

  <!-- Phones: a 2 × 2 grid, previews on the first row. -->
  <div v-else class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
    <a
      v-for="link in ordered"
      :key="link.key"
      :href="link.href"
      :download="link.preview ? undefined : ''"
      :target="link.preview ? '_blank' : undefined"
      :rel="link.preview ? 'noopener' : undefined"
      class="btn btn-secondary text-center"
    >
      <span>{{ link.label }}<span v-if="link.preview" aria-hidden="true">&nbsp;↗</span></span>
      <span v-if="link.preview" class="sr-only">(avautuu uuteen välilehteen)</span>
    </a>
  </div>
</template>
