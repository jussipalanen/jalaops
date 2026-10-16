<script setup>
import CopyButton from '@/components/CopyButton.vue'

// "Jaa" opens the device's own share menu (messages, email, WhatsApp…) on
// phones and tablets that have one; elsewhere "Kopioi linkki" copies the link.
const props = defineProps({
  url: { type: String, required: true },
  title: { type: String, required: true },
})

const canShare = Boolean(window.navigator.share)

async function share() {
  try {
    await window.navigator.share({ title: props.title, url: props.url })
  } catch {
    // Cancelled, or not allowed.
  }
}
</script>

<template>
  <button v-if="canShare" type="button" class="btn btn-secondary" @click="share">
    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 3v12M7 8l5-5 5 5M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5" />
    </svg>
    Jaa
  </button>
  <CopyButton v-else :value="url" label="Kopioi linkki" text="Kopioi linkki" copied-text="Linkki kopioitu" />
</template>
