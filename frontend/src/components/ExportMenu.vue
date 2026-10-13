<script setup>
import { computed, useTemplateRef } from 'vue'
import { useDropdown } from '@/composables/useDropdown'

// "Vie" menu: downloads the requests as CSV (Excel) or JSON. `query` holds the
// export's filters or ids, e.g. "status=open" or "ids=1,5".
const props = defineProps({
  query: { type: String, default: '' },
  label: { type: String, default: 'Vie' },
  // The export endpoint; requests by default.
  base: { type: String, default: '/api/requests/export' },
})

const formats = computed(() =>
  ['csv', 'json'].map((format) => ({
    format,
    label: format === 'csv' ? 'CSV (Excel)' : 'JSON (täysi kopio)',
    href: `${props.base}?format=${format}${props.query ? `&${props.query}` : ''}`,
  })),
)

const menu = useTemplateRef('menu')
const { open, toggle, close } = useDropdown(menu)
</script>

<template>
  <div ref="menu" class="relative">
    <button type="button" class="btn btn-secondary w-full" :aria-expanded="open" aria-haspopup="true" @click="toggle">
      {{ label }} <span class="text-xs" aria-hidden="true">▾</span>
    </button>
    <div
      v-show="open"
      class="absolute inset-x-0 z-20 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg sm:inset-x-auto sm:right-0 sm:min-w-48 dark:border-slate-700 dark:bg-slate-900"
    >
      <a
        v-for="item in formats"
        :key="item.format"
        :href="item.href"
        download
        class="flex min-h-11 items-center px-4 font-semibold hover:bg-slate-100 lg:min-h-9 dark:hover:bg-slate-800"
        @click="close"
      >
        {{ item.label }}
      </a>
    </div>
  </div>
</template>
