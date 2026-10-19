<script setup>
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError, apiGet } from '@/api/client'
import ModuleFrame from '@/components/ModuleFrame.vue'
import { moduleErrorMessage, runModule } from '@/modules'

// A Python module's page: the module's own view (view.html, in a sandboxed
// frame), or else a form and results drawn from its module.json.

const route = useRoute()

const module = ref(null)
const loadError = ref('')
const values = reactive({})
const running = ref(false)
const output = ref(null)
const error = ref('')


onMounted(async () => {
  try {
    module.value = (await apiGet(`/modules/${encodeURIComponent(route.params.id)}`)).data

    for (const field of module.value.form) {
      values[field.name] = initialValue(field)
    }
  } catch (exception) {
    loadError.value =
      exception instanceof ApiError && exception.status === 404
        ? 'Moduulia ei löytynyt.'
        : 'Moduulin haku epäonnistui. Yritä hetken kuluttua uudelleen.'
  }
})

function initialValue(field) {
  if (field.type === 'numbers') {
    return Array.isArray(field.default) ? field.default.join(', ') : ''
  }

  return field.default ?? (field.type === 'select' ? field.options[0] : '')
}

// "3, 5, 10" → [3, 5, 10]; null when a part isn't a number.
function parseNumbers(text) {
  const numbers = text
    .split(/[,;\s]+/)
    .filter(Boolean)
    .map((part) => Number(part))

  return numbers.every(Number.isFinite) ? numbers : null
}

// The module's input from the form. Empty fields are left out, so the module
// uses its own defaults for them.
function buildInput() {
  const input = {}
  const errors = []

  for (const field of module.value.form) {
    const text = String(values[field.name] ?? '').trim()

    if (text === '') {
      if (field.required) errors.push(`${field.label}: pakollinen tieto.`)
      continue
    }

    if (field.type === 'number') {
      const number = Number(text.replace(',', '.'))
      if (Number.isFinite(number)) input[field.name] = number
      else errors.push(`${field.label}: anna luku.`)
    } else if (field.type === 'numbers') {
      const numbers = parseNumbers(text)
      if (numbers) input[field.name] = numbers
      else errors.push(`${field.label}: anna luvut pilkulla erotettuina, desimaalit pisteellä.`)
    } else {
      input[field.name] = text
    }
  }

  return { input, errors }
}

async function run() {
  const { input, errors } = buildInput()
  output.value = null
  error.value = errors.join(' ')

  if (errors.length) {
    return
  }

  running.value = true

  try {
    output.value = await runModule(module.value.id, input)
  } catch (exception) {
    error.value = moduleErrorMessage(exception)
  } finally {
    running.value = false
  }
}

// The results to show: those listed in module.json, or else everything the
// module returned.
function resultRows() {
  const results = module.value.results.length
    ? module.value.results
    : Object.keys(output.value).map((name) => ({ name, label: name }))

  return results
    .filter((result) => output.value[result.name] !== undefined && output.value[result.name] !== null)
    .map((result) => [result.label, format(output.value[result.name], result.decimals)])
}

// Numbers in Finnish format, with the result's own number of decimals if it has one.
function format(value, decimals) {
  if (typeof value === 'number') {
    return new Intl.NumberFormat('fi-FI', {
      minimumFractionDigits: decimals ?? 0,
      maximumFractionDigits: decimals ?? 2,
    }).format(value)
  }
  if (Array.isArray(value)) return value.map((item) => format(item, decimals)).join(', ')
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}
</script>

<template>
  <section class="max-w-3xl">
    <RouterLink to="/tools" class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400">
      ← Työkalut
    </RouterLink>

    <p v-if="loadError" class="alert-error mt-4" role="alert">{{ loadError }}</p>
    <p v-else-if="!module" class="mt-4 text-slate-500 dark:text-slate-400">Haetaan moduulia…</p>

    <template v-else>
      <header class="page-header">
        <div>
          <h1 class="page-title">{{ module.name }}</h1>
          <p v-if="module.description" class="page-subtitle mt-1">{{ module.description }}</p>
        </div>
      </header>

      <p v-if="!module.enabled" class="alert-error" role="alert">Moduuli ei ole käytössä.</p>

      <ModuleFrame v-else-if="module.view" :module="module" />

      <div v-else class="grid gap-4">
        <form class="card card-body grid gap-5" novalidate @submit.prevent="run">
          <div v-for="field in module.form" :key="field.name" class="grid min-w-0 content-start gap-1.5">
            <label :for="`field-${field.name}`" class="text-[0.9375rem] font-semibold">
              {{ field.label }}
              <span v-if="field.required" class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
            </label>
            <select v-if="field.type === 'select'" :id="`field-${field.name}`" v-model="values[field.name]" class="form-control">
              <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
            </select>
            <textarea
              v-else-if="field.type === 'textarea'"
              :id="`field-${field.name}`"
              v-model="values[field.name]"
              class="form-control resize-y"
              rows="4"
              :aria-describedby="field.help ? `field-${field.name}-help` : undefined"
            ></textarea>
            <input
              v-else
              :id="`field-${field.name}`"
              v-model="values[field.name]"
              class="form-control"
              type="text"
              :inputmode="field.type === 'text' ? undefined : 'decimal'"
              :aria-describedby="field.help ? `field-${field.name}-help` : undefined"
            />
            <p v-if="field.help" :id="`field-${field.name}-help`" class="text-sm text-slate-500 dark:text-slate-400">{{ field.help }}</p>
          </div>

          <p v-if="error" class="alert-error mb-0" role="alert">{{ error }}</p>

          <div>
            <button type="submit" class="btn btn-primary w-full sm:w-auto" :disabled="running">
              {{ running ? 'Suoritetaan…' : 'Suorita' }}
            </button>
          </div>
        </form>

        <div v-if="output" class="card card-body" aria-live="polite">
          <h2 class="text-lg font-bold">Tulos</h2>
          <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2">
            <div v-for="[label, value] in resultRows()" :key="label">
              <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ label }}</dt>
              <dd class="mt-0.5 tabular-nums">{{ value }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </template>
  </section>
</template>
