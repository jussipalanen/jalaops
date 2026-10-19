<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { moduleErrorMessage, moduleParams, runModule } from '@/modules'

// A module's own page (view.html in its folder) in a sandboxed frame, with the
// CSS and JS files the module lists in "assets" (its own, or copies of
// libraries) put in inline. The frame can't reach the app, its storage or the
// network: the sandbox gives it an origin of its own, and the
// Content-Security-Policy below blocks every request. It runs its module only
// through the bridge, jalaops.run(input), which asks this component over postMessage.
//
// A view can have several pages with addresses of their own: jalaops.params
// are the page address's parameters (?news=3), jalaops.navigate(params)
// changes them, and a "jalaops:params" event tells the view when they change
// (also with the back button). Links in the view may open in a new tab.

const props = defineProps({
  module: { type: Object, required: true },
})

const route = useRoute()
const router = useRouter()
const frame = useTemplateRef('frame')
const height = ref(240)

// The parameters when the page opened. Later changes are sent as messages,
// so the frame isn't drawn again.
const initialParams = moduleParams(route.query)

// The app's colours for the module's styles, in the light and the dark theme.
const STYLE = `
:root { --primary: #0369a1; --primary-text: #fff; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --surface: #fff; --error: #dc2626; color-scheme: light; }
:root.dark { --primary: #10b981; --primary-text: #020617; --text: #f1f5f9; --muted: #94a3b8; --border: #1e293b; --surface: #0f172a; --error: #f87171; color-scheme: dark; }
* { box-sizing: border-box; }
html, body { margin: 0; background: transparent; }
body { padding: 2px; color: var(--text); font: 16px/1.5 ui-sans-serif, system-ui, sans-serif; }
h1, h2, h3 { margin: 0; line-height: 1.25; }
h2 { font-size: 1.25rem; }
.stack { display: grid; gap: 1rem; }
label { display: grid; gap: 0.375rem; font-size: 0.9375rem; font-weight: 600; }
small { color: var(--muted); font-size: 0.875rem; font-weight: 400; }
input, select, textarea { width: 100%; min-height: 2.75rem; padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 0.5rem; background: var(--surface); color: inherit; font: inherit; font-weight: 400; }
input:focus-visible, select:focus-visible, textarea:focus-visible, button:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
button { min-height: 2.75rem; padding: 0 1rem; border: 0; border-radius: 0.5rem; background: var(--primary); color: var(--primary-text); font: inherit; font-weight: 600; cursor: pointer; }
button:disabled { cursor: not-allowed; opacity: 0.6; }
.error { margin: 0; color: var(--error); font-weight: 600; }
[hidden] { display: none !important; }
`

// jalaops.run(input) and the page parameters for the module's script, and the
// frame's height for the app.
const BRIDGE = `
(() => {
  const pending = new Map()
  let next = 0
  window.jalaops = {
    params: JSON.parse(document.getElementById('jalaops-params').textContent),
    run(input = {}) {
      const id = ++next
      parent.postMessage({ type: 'jalaops:run', id, input }, '*')
      return new Promise((resolve, reject) => pending.set(id, { resolve, reject }))
    },
    navigate(params = {}) {
      parent.postMessage({ type: 'jalaops:navigate', params }, '*')
    },
  }
  addEventListener('message', (event) => {
    if (event.source !== parent) return
    const { type, id, output, message, params } = event.data || {}
    if (type === 'jalaops:params') {
      window.jalaops.params = params
      dispatchEvent(new CustomEvent('jalaops:params', { detail: params }))
      return
    }
    const call = pending.get(id)
    if (!call) return
    pending.delete(id)
    type === 'jalaops:result' ? call.resolve(output) : call.reject(new Error(message))
  })
  // The body's own height: the document's scrollHeight never gets smaller
  // than the frame, so the frame couldn't shrink when the content does.
  const report = () => parent.postMessage({ type: 'jalaops:height', height: document.body.getBoundingClientRect().height }, '*')
  addEventListener('load', () => {
    report()
    new ResizeObserver(report).observe(document.body)
  })
})()
`

// A file's content as an inline <style> or <script>. A closing tag inside it
// would end the element early, so "</" before it is escaped ("<\/" means the
// same in CSS and JS strings).
function inline({ type, content }) {
  const tag = type === 'script' ? 'script' : 'style'
  const safe = content.replace(new RegExp(`</(${tag})`, 'gi'), '<\\/$1')

  return `<${tag}>${safe}</${tag}>`
}

const srcdoc = computed(() => {
  const dark = document.documentElement.classList.contains('dark')
  const assets = (props.module.assets ?? []).map(inline).join('')

  return [
    `<!doctype html><html lang="fi"${dark ? ' class="dark"' : ''}><head><meta charset="utf-8">`,
    `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:">`,
    // The parameters as JSON data, with "<" escaped so they can't end the element.
    `<script type="application/json" id="jalaops-params">${JSON.stringify(initialParams).replace(/</g, '\\u003c')}</` + 'script>',
    // The closing script tag is split, so it doesn't end this component's own script block.
    `<style>${STYLE}</style><script>${BRIDGE}</` + 'script>',
    `${assets}</head>`,
    `<body>${props.module.view}</body></html>`,
  ].join('')
})

function reply(message) {
  frame.value?.contentWindow?.postMessage(message, '*')
}

async function onMessage(event) {
  // Only messages from this module's own frame.
  if (!frame.value || event.source !== frame.value.contentWindow) {
    return
  }

  const { type, id, input, params, height: reported } = event.data ?? {}

  if (type === 'jalaops:height') {
    height.value = Math.min(Math.max(Math.ceil(Number(reported) || 0), 120), 20000)
    return
  }

  if (type === 'jalaops:navigate') {
    navigate(params)
    return
  }

  if (type !== 'jalaops:run') {
    return
  }

  if (!input || typeof input !== 'object' || Array.isArray(input)) {
    reply({ type: 'jalaops:error', id, message: 'Syötteen pitää olla objekti.' })
    return
  }

  try {
    reply({ type: 'jalaops:result', id, output: await runModule(props.module.id, input) })
  } catch (exception) {
    reply({ type: 'jalaops:error', id, message: moduleErrorMessage(exception) })
  }
}

// A new page of the view: a new address in the browser's history, from the top.
async function navigate(params) {
  await router.push({ query: moduleParams(params) })
  window.scrollTo({ top: 0 })
}

// The address changed (navigate, the back button or a link): the view draws
// the page for the new parameters.
watch(
  () => route.query,
  (query) => reply({ type: 'jalaops:params', params: moduleParams(query) }),
)

onMounted(() => window.addEventListener('message', onMessage))
onBeforeUnmount(() => window.removeEventListener('message', onMessage))
</script>

<template>
  <div class="card card-body">
    <iframe
      ref="frame"
      class="block w-full"
      :style="{ height: `${height}px` }"
      :title="module.name"
      sandbox="allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox"
      referrerpolicy="no-referrer"
      :srcdoc="srcdoc"
    ></iframe>
  </div>
</template>
