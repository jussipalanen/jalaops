<script setup>
import { onMounted, ref, useTemplateRef } from 'vue'

// A pad for drawing a signature with a finger, pen or mouse. Emits `change`
// with the drawing as a PNG data URL after each stroke, or null when cleared.
defineProps({
  id: { type: String, required: true },
  invalid: { type: Boolean, default: false },
  describedby: { type: String, default: undefined },
})

const emit = defineEmits(['change'])

const canvas = useTemplateRef('canvas')
const hasInk = ref(false)
let context = null
let drawing = false

// The pad stays white in the dark theme too, like paper, so the saved image reads anywhere.
function paintBackground() {
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, canvas.value.width, canvas.value.height)
}

onMounted(() => {
  const element = canvas.value
  const rect = element.getBoundingClientRect()
  // Draw at the screen's pixel density (at most 2×) for sharp lines without a huge image.
  const scale = Math.min(window.devicePixelRatio || 1, 2)
  element.width = Math.round((rect.width || 600) * scale)
  element.height = Math.round((rect.height || 200) * scale)

  context = element.getContext?.('2d') ?? null

  if (context) {
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.lineWidth = 2.5 * scale
    context.strokeStyle = '#0f172a'
    paintBackground()
  }
})

// Pointer position in canvas pixels; the pad keeps its 3:1 shape, so scaling stays even.
function point(event) {
  const rect = canvas.value.getBoundingClientRect()
  return [
    ((event.clientX - rect.left) * canvas.value.width) / rect.width,
    ((event.clientY - rect.top) * canvas.value.height) / rect.height,
  ]
}

function start(event) {
  if (!context) return
  drawing = true
  canvas.value.setPointerCapture?.(event.pointerId)
  const [x, y] = point(event)
  context.beginPath()
  context.moveTo(x, y)
  // A dot for a tap without movement.
  context.lineTo(x + 0.1, y + 0.1)
  context.stroke()
}

function move(event) {
  if (!drawing) return
  const [x, y] = point(event)
  context.lineTo(x, y)
  context.stroke()
}

function end() {
  if (!drawing) return
  drawing = false
  hasInk.value = true
  emit('change', canvas.value.toDataURL('image/png'))
}

function clear() {
  if (context) paintBackground()
  hasInk.value = false
  emit('change', null)
}
</script>

<template>
  <div>
    <canvas
      :id="id"
      ref="canvas"
      class="block aspect-[3/1] w-full cursor-crosshair touch-none rounded-lg border-2 border-dashed bg-white"
      :class="invalid ? 'border-red-500 dark:border-red-400' : 'border-slate-300 dark:border-slate-600'"
      role="img"
      aria-label="Allekirjoitusalue: piirrä allekirjoitus sormella, kynällä tai hiirellä"
      :aria-describedby="describedby"
      @pointerdown.prevent="start"
      @pointermove.prevent="move"
      @pointerup="end"
      @pointercancel="end"
      @pointerleave="end"
    ></canvas>
    <div class="mt-1.5 flex items-center justify-between gap-3">
      <p class="text-sm text-slate-500 dark:text-slate-400">Piirrä allekirjoitus yllä olevaan kenttään.</p>
      <button
        type="button"
        class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-700 hover:underline disabled:cursor-not-allowed disabled:opacity-50 lg:min-h-9 dark:text-primary-400"
        :disabled="!hasInk"
        @click="clear"
      >
        Tyhjennä
      </button>
    </div>
  </div>
</template>
