<script setup>
import { computed, useTemplateRef } from 'vue'
import { useDropdown } from '@/composables/useDropdown'

// Download a request as a PDF or a self-contained HTML file. "buttons" shows
// both formats side by side; "menu" is a compact "Lataa" button for list rows.
const props = defineProps({
  requestId: { type: Number, required: true },
  title: { type: String, default: '' },
  variant: { type: String, default: 'buttons' },
})

const formats = computed(() => [
  { format: 'pdf', label: 'PDF', href: `/api/requests/${props.requestId}/export/pdf` },
  { format: 'html', label: 'HTML', href: `/api/requests/${props.requestId}/export/html` },
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
      :aria-label="title ? `Lataa: ${title}` : 'Lataa'"
      @click="toggle"
    >
      Lataa <span class="text-xs" aria-hidden="true">▾</span>
    </button>
    <div
      v-show="open"
      class="absolute inset-x-0 z-20 mt-1 overflow-hidden sm:inset-x-auto sm:right-0 sm:min-w-40 rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <a
        v-for="item in formats"
        :key="item.format"
        :href="item.href"
        download
        class="flex min-h-11 items-center px-4 font-semibold hover:bg-slate-100 lg:min-h-9 dark:hover:bg-slate-800"
        @click="close"
      >
        Lataa {{ item.label }}
      </a>
    </div>
  </div>

  <div v-else class="flex flex-wrap gap-2">
    <a v-for="item in formats" :key="item.format" :href="item.href" download class="btn btn-secondary max-sm:flex-1">
      Lataa {{ item.label }}
    </a>
  </div>
</template>
