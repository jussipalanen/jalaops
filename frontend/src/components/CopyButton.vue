<script setup>
import { onBeforeUnmount, ref } from 'vue'

// Copies `value` to the clipboard, e.g. an IBAN for pasting into a banking app,
// and says "Kopioitu" for a moment. Hidden where the browser can't copy (the
// clipboard needs HTTPS or localhost).
const props = defineProps({
  value: { type: String, required: true },
  // What is copied, for screen readers: "Kopioi tilinumero".
  label: { type: String, default: 'Kopioi' },
  text: { type: String, default: 'Kopioi' },
  copiedText: { type: String, default: 'Kopioitu' },
})

const canCopy = Boolean(window.navigator.clipboard?.writeText)
const copied = ref(false)
let timer = null

async function copy() {
  try {
    await window.navigator.clipboard.writeText(props.value)
    copied.value = true
    clearTimeout(timer)
    timer = setTimeout(() => (copied.value = false), 2000)
  } catch {
    // Not allowed: the value is still on the screen to copy by hand.
  }
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <button v-if="canCopy" type="button" class="btn btn-secondary" :aria-label="label" @click="copy">
    <svg v-if="copied" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="m5 12 5 5 9-10" />
    </svg>
    <svg v-else class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V6a2 2 0 0 1 2-2h9" />
    </svg>
    <span>{{ copied ? copiedText : text }}</span>
    <span class="sr-only" role="status">{{ copied ? copiedText : '' }}</span>
  </button>
</template>
