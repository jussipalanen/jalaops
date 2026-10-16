<script setup>
import { ref, watch } from 'vue'

// A QR code of `value`, drawn as an SVG image. The qrcode library is loaded only
// when a QR code is shown. The code is always dark on white, also in the dark
// theme, because many scanners can't read a light code on a dark background.
// Without the library (offline) nothing is shown: the page works without it.
const props = defineProps({
  value: { type: String, required: true },
  alt: { type: String, required: true },
})

const src = ref(null)

watch(
  () => props.value,
  async (value) => {
    try {
      const { default: QRCode } = await import('qrcode')
      const svg = await QRCode.toString(value, { type: 'svg', margin: 0, errorCorrectionLevel: 'M' })
      src.value = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
    } catch {
      src.value = null
    }
  },
  { immediate: true },
)
</script>

<template>
  <img v-if="src" :src="src" :alt="alt" class="block aspect-square rounded-lg bg-white p-2" />
</template>
