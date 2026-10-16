<script setup>
import { onBeforeUnmount, useTemplateRef, watch } from 'vue'
import QrCode from '@/components/QrCode.vue'

// A QR code on the whole screen, to be scanned with another phone: a
// technician shows a tablet to the customer, or a colleague scans a phone.
// It fits portrait and landscape, keeps the screen on where the browser can
// (Wake Lock), and closes with "Sulje", Escape or a tap outside the code.
const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  value: { type: String, required: true },
  title: { type: String, required: true },
  alt: { type: String, required: true },
  // Shown under the code in large text, e.g. [['Summa', '122,36 €']], so the
  // person scanning can check them.
  details: { type: Array, default: () => [] },
})

const dialog = useTemplateRef('dialog')
let wakeLock = null

async function keepScreenOn() {
  try {
    wakeLock = await window.navigator.wakeLock?.request('screen')
  } catch {
    // Not allowed or not supported: the screen dims as usual.
  }
}

function letScreenDim() {
  wakeLock?.release().catch(() => {})
  wakeLock = null
}

watch(
  [open, dialog],
  ([isOpen, element]) => {
    if (!element) return
    if (isOpen && !element.open) {
      // showModal() puts the dialog above the page, traps focus and closes on
      // Escape; older browsers just show it.
      element.showModal ? element.showModal() : element.setAttribute('open', '')
      keepScreenOn()
    } else if (!isOpen) {
      if (element.open) element.close ? element.close() : element.removeAttribute('open')
      letScreenDim()
    }
  },
  { immediate: true },
)

// The dialog closes itself on Escape.
function onClose() {
  open.value = false
}

// A tap on the backdrop (the dialog element itself, around the content) closes it.
function onClick(event) {
  if (event.target === dialog.value) open.value = false
}

onBeforeUnmount(letScreenDim)
</script>

<template>
  <dialog
    ref="dialog"
    class="m-auto max-h-[100dvh] max-w-[100dvw] bg-transparent p-4 backdrop:bg-slate-950/80"
    :aria-label="title"
    @close="onClose"
    @click="onClick"
  >
    <div
      v-if="open"
      class="flex max-h-[calc(100dvh-2rem)] flex-col items-center gap-4 overflow-auto rounded-2xl bg-white p-5 text-slate-900 shadow-xl [@media(max-height:36rem)]:flex-row"
    >
      <QrCode :value="value" :alt="alt" class="size-[min(78vw,62dvh,26rem)] shrink-0 [@media(max-height:36rem)]:size-[min(45vw,calc(100dvh-4.5rem))]" />
      <div class="flex w-full flex-col items-center gap-3 text-center">
        <h2 class="text-lg font-bold">{{ title }}</h2>
        <dl v-if="details.length" class="grid grid-cols-[auto_auto] gap-x-4 gap-y-1 text-left">
          <template v-for="[label, text] in details" :key="label">
            <dt class="text-slate-600">{{ label }}</dt>
            <dd class="text-lg font-bold tabular-nums">{{ text }}</dd>
          </template>
        </dl>
        <button type="button" class="btn btn-primary w-full max-w-xs" autofocus @click="open = false">Sulje</button>
      </div>
    </div>
  </dialog>
</template>
