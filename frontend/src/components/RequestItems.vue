<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { apiDelete, apiGet, apiPost, apiPut } from '@/api/client'
import { formatEuro, formatNumber, itemTypeLabels, parseDecimal } from '@/labels'

// "Työt ja tarvikkeet": rows of work and materials on a request, with totals.
const props = defineProps({
  requestId: { type: Number, required: true },
})

const items = ref([])
const totals = ref({ net: 0, vat: 0, total: 0 })
const defaults = ref({ vat_rate: 25.5, hourly_rate: 65 })
// An invoiced request's rows can't change.
const locked = ref(false)
const loading = ref(true)
const error = ref('')

// The row being added (editingId null) or edited.
const editingId = ref(null)
const formOpen = ref(false)
const errors = ref({})
const saving = ref(false)
const form = reactive({ type: 'work', description: '', quantity: '', unit: 'h', unit_price: '', vat_rate: '' })

const formTitle = computed(() => (editingId.value ? 'Muokkaa riviä' : 'Uusi rivi'))

// Shown with a decimal comma in the inputs.
const asInput = (value) => String(value).replace('.', ',')

function apply(response) {
  items.value = response.data
  totals.value = response.totals
  defaults.value = response.defaults
  locked.value = Boolean(response.locked)
}

onMounted(async () => {
  try {
    apply(await apiGet(`/requests/${props.requestId}/items`))
  } catch {
    error.value = 'Töiden ja tarvikkeiden lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
})

// A new row starts as work at the default hourly rate; a material row as pieces.
function prefill(type) {
  form.type = type
  form.unit = type === 'work' ? 'h' : 'kpl'
  form.unit_price = type === 'work' ? asInput(defaults.value.hourly_rate) : ''
}

function openNew() {
  editingId.value = null
  Object.assign(form, { description: '', quantity: '1', vat_rate: asInput(defaults.value.vat_rate) })
  prefill('work')
  errors.value = {}
  formOpen.value = true
}

function openEdit(item) {
  editingId.value = item.id
  Object.assign(form, {
    type: item.type,
    description: item.description,
    quantity: asInput(item.quantity),
    unit: item.unit,
    unit_price: asInput(item.unit_price),
    vat_rate: asInput(item.vat_rate),
  })
  errors.value = {}
  formOpen.value = true
}

function changeType(type) {
  // Only a new row is re-filled; an edited row keeps its own price.
  if (editingId.value === null) {
    prefill(type)
  } else {
    form.type = type
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  const data = {
    type: form.type,
    description: form.description.trim(),
    quantity: parseDecimal(form.quantity),
    unit: form.unit.trim(),
    unit_price: parseDecimal(form.unit_price),
    vat_rate: parseDecimal(form.vat_rate),
  }

  try {
    const path = `/requests/${props.requestId}/items`
    apply(await (editingId.value ? apiPut(`${path}/${editingId.value}`, data) : apiPost(path, data)))
    formOpen.value = false
  } catch (e) {
    if (e.status === 422) {
      errors.value = e.errors
    } else {
      error.value = 'Rivin tallentaminen epäonnistui.'
    }
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!window.confirm(`Poistetaanko rivi "${item.description}"?`)) return

  try {
    await apiDelete(`/requests/${props.requestId}/items/${item.id}`)
    apply(await apiGet(`/requests/${props.requestId}/items`))
  } catch {
    error.value = 'Rivin poistaminen epäonnistui.'
  }
}

const fieldError = (field) => errors.value[field]?.[0]
</script>

<template>
  <section class="card card-body" aria-labelledby="request-items-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 id="request-items-title" class="text-lg font-bold">Työt ja tarvikkeet</h2>
      <button v-if="!formOpen && !locked && !loading" type="button" class="btn btn-secondary max-sm:w-full" @click="openNew">+ Lisää rivi</button>
    </div>

    <p v-if="locked" class="mt-2 text-sm text-slate-500 dark:text-slate-400">Pyyntö on laskutettu, joten rivejä ei voi enää muuttaa.</p>

    <p v-if="error" class="alert-error mt-3" role="alert">{{ error }}</p>
    <p v-if="loading" class="mt-3 text-slate-500 dark:text-slate-400">Ladataan…</p>
    <p v-else-if="items.length === 0 && !formOpen" class="mt-3 text-slate-500 dark:text-slate-400">
      Ei vielä töitä tai tarvikkeita. Ne ovat laskutuksen perusta.
    </p>

    <template v-if="items.length">
      <!-- Wider screens: a table. Phones: one card per row. -->
      <table class="mt-3 w-full text-left text-sm max-sm:hidden">
        <thead class="text-slate-500 dark:text-slate-400">
          <tr class="border-b border-slate-200 dark:border-slate-800">
            <th class="py-2 pr-3 font-semibold">Kuvaus</th>
            <th class="py-2 pr-3 text-right font-semibold">Määrä</th>
            <th class="py-2 pr-3 text-right font-semibold">á-hinta (alv 0 %)</th>
            <th class="py-2 pr-3 text-right font-semibold">ALV</th>
            <th class="py-2 pr-3 text-right font-semibold">Yhteensä</th>
            <th class="py-2"><span class="sr-only">Toiminnot</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-b border-slate-200 dark:border-slate-800">
            <td class="py-2 pr-3">
              <span class="font-semibold">{{ item.description }}</span>
              <span class="ml-2 text-xs text-slate-500 dark:text-slate-400">{{ itemTypeLabels[item.type] }}</span>
            </td>
            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ formatNumber(item.quantity) }} {{ item.unit }}</td>
            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ formatEuro(item.unit_price) }}</td>
            <td class="py-2 pr-3 text-right tabular-nums whitespace-nowrap">{{ formatNumber(item.vat_rate) }} %</td>
            <td class="py-2 pr-3 text-right font-semibold tabular-nums whitespace-nowrap">{{ formatEuro(item.total) }}</td>
            <td class="py-2 text-right whitespace-nowrap">
              <template v-if="!locked">
              <button type="button" class="font-semibold text-primary-700 hover:underline dark:text-primary-400" :aria-label="`Muokkaa: ${item.description}`" @click="openEdit(item)">Muokkaa</button>
              <button type="button" class="ml-3 font-semibold text-red-600 hover:underline dark:text-red-400" :aria-label="`Poista: ${item.description}`" @click="remove(item)">Poista</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>

      <ul class="mt-3 grid gap-2 sm:hidden">
        <li v-for="item in items" :key="item.id" class="rounded-xl border border-slate-200 p-3 dark:border-slate-800">
          <p class="font-semibold">{{ item.description }} <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ itemTypeLabels[item.type] }}</span></p>
          <p class="mt-1 flex justify-between text-sm tabular-nums">
            <span>{{ formatNumber(item.quantity) }} {{ item.unit }} × {{ formatEuro(item.unit_price) }} + {{ formatNumber(item.vat_rate) }} %</span>
            <strong>{{ formatEuro(item.total) }}</strong>
          </p>
          <div v-if="!locked" class="mt-2 grid grid-cols-2 gap-2">
            <button type="button" class="btn btn-secondary" :aria-label="`Muokkaa: ${item.description}`" @click="openEdit(item)">Muokkaa</button>
            <button type="button" class="btn btn-danger" :aria-label="`Poista: ${item.description}`" @click="remove(item)">Poista</button>
          </div>
        </li>
      </ul>

      <dl class="mt-3 ml-auto grid max-w-xs grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-sm tabular-nums" aria-label="Summat">
        <dt class="text-slate-500 dark:text-slate-400">Veroton yhteensä</dt>
        <dd class="text-right">{{ formatEuro(totals.net) }}</dd>
        <dt class="text-slate-500 dark:text-slate-400">ALV yhteensä</dt>
        <dd class="text-right">{{ formatEuro(totals.vat) }}</dd>
        <dt class="border-t border-slate-300 pt-1 font-bold dark:border-slate-700">Yhteensä</dt>
        <dd class="border-t border-slate-300 pt-1 text-right font-bold dark:border-slate-700">{{ formatEuro(totals.total) }}</dd>
      </dl>
    </template>

    <form v-if="formOpen" class="mt-4 grid gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800" novalidate @submit.prevent="save">
      <h3 class="font-semibold">{{ formTitle }}</h3>
      <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
        <div class="grid content-start gap-1.5">
          <label for="item-type" class="text-sm font-semibold">Tyyppi</label>
          <select id="item-type" class="form-control" :value="form.type" @change="changeType($event.target.value)">
            <option v-for="(label, value) in itemTypeLabels" :key="value" :value="value">{{ label }}</option>
          </select>
        </div>
        <div class="grid min-w-0 content-start gap-1.5">
          <label for="item-description" class="text-sm font-semibold">Kuvaus</label>
          <input id="item-description" v-model="form.description" class="form-control" type="text" maxlength="255" :aria-invalid="Boolean(fieldError('description'))" :aria-describedby="fieldError('description') ? 'item-description-error' : undefined" />
          <p v-if="fieldError('description')" id="item-description-error" class="text-sm text-red-600 dark:text-red-400">{{ fieldError('description') }}</p>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div v-for="field in [['quantity', 'Määrä', 'decimal'], ['unit', 'Yksikkö', 'text'], ['unit_price', 'á-hinta, € (alv 0 %)', 'decimal'], ['vat_rate', 'ALV %', 'decimal']]" :key="field[0]" class="grid min-w-0 content-start gap-1.5">
          <label :for="`item-${field[0]}`" class="text-sm font-semibold">{{ field[1] }}</label>
          <input
            :id="`item-${field[0]}`"
            v-model="form[field[0]]"
            class="form-control tabular-nums"
            type="text"
            :inputmode="field[2] === 'decimal' ? 'decimal' : 'text'"
            :aria-invalid="Boolean(fieldError(field[0]))"
            :aria-describedby="fieldError(field[0]) ? `item-${field[0]}-error` : undefined"
          />
          <p v-if="fieldError(field[0])" :id="`item-${field[0]}-error`" class="text-sm text-red-600 dark:text-red-400">{{ fieldError(field[0]) }}</p>
        </div>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row">
        <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Tallennetaan…' : editingId ? 'Tallenna' : 'Lisää rivi' }}</button>
        <button type="button" class="btn btn-secondary" :disabled="saving" @click="formOpen = false">Peruuta</button>
      </div>
    </form>
  </section>
</template>
