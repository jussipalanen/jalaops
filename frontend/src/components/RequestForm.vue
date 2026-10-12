<script setup>
import { computed, defineAsyncComponent, reactive, ref } from 'vue'
import { apiGet } from '@/api/client'
import AddressAutocomplete from '@/components/AddressAutocomplete.vue'
import { formatAddress, priorityLabels, statusLabels } from '@/labels'

// The map loads Leaflet, so it's fetched only when the form is shown.
const LocationPicker = defineAsyncComponent(() => import('@/components/LocationPicker.vue'))

const props = defineProps({
  // Initial field values; omitted fields use the defaults below.
  request: { type: Object, default: () => ({}) },
  // Field errors from the API, e.g. { title: ['Kenttä otsikko on pakollinen.'] }.
  errors: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  submitLabel: { type: String, default: 'Tallenna' },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({
  title: props.request.title ?? '',
  description: props.request.description ?? '',
  priority: props.request.priority ?? 'normal',
  status: props.request.status ?? 'open',
  due_date: props.request.due_date ?? '',
  street_address: props.request.street_address ?? '',
  postal_code: props.request.postal_code ?? '',
  city: props.request.city ?? '',
  latitude: props.request.latitude ?? '',
  longitude: props.request.longitude ?? '',
})

// The API expects null for empty optional fields.
function textOrNull(value) {
  return String(value).trim() === '' ? null : String(value).trim()
}

function numberOrNull(value) {
  return value === '' || value === null ? null : Number(value)
}

function submit() {
  emit('submit', {
    ...form,
    description: form.description.trim() === '' ? null : form.description,
    due_date: form.due_date === '' ? null : form.due_date,
    street_address: textOrNull(form.street_address),
    postal_code: textOrNull(form.postal_code),
    city: textOrNull(form.city),
    latitude: numberOrNull(form.latitude),
    longitude: numberOrNull(form.longitude),
  })
}

// "Hae koordinaatit osoitteesta": looks the address up through the backend (Nominatim).
const geocoding = ref(false)
const geocodeMessage = ref('')
const geocodeFailed = ref(false)
const addressQuery = computed(() => formatAddress(form))
const hasCoordinates = computed(() => form.latitude !== '' && form.longitude !== '')

async function findCoordinates() {
  geocoding.value = true
  geocodeMessage.value = ''
  geocodeFailed.value = false

  try {
    const place = await apiGet(`/geocode?q=${encodeURIComponent(addressQuery.value)}`)
    form.latitude = place.latitude
    form.longitude = place.longitude
    geocodeMessage.value = `Sijainti löytyi: ${place.display_name}`
  } catch (e) {
    geocodeFailed.value = true
    geocodeMessage.value =
      e.status === 404
        ? 'Osoitetta ei löytynyt kartalta. Tarkista osoite tai valitse sijainti kartalta.'
        : e.status === 429
          ? 'Liian monta hakua. Odota hetki ja yritä uudelleen.'
          : 'Osoitehaku ei juuri nyt onnistu. Valitse sijainti kartalta.'
  } finally {
    geocoding.value = false
  }
}

// A chosen address suggestion fills in the whole location.
function applySuggestion(suggestion) {
  form.street_address = suggestion.street_address
  form.postal_code = suggestion.postal_code ?? ''
  form.city = suggestion.city ?? ''
  form.latitude = suggestion.latitude
  form.longitude = suggestion.longitude
  geocodeFailed.value = false
  geocodeMessage.value = ''
}

function pickLocation({ latitude, longitude }) {
  form.latitude = latitude
  form.longitude = longitude
  geocodeMessage.value = ''
}

function clearCoordinates() {
  form.latitude = ''
  form.longitude = ''
  geocodeMessage.value = ''
}

function fieldError(field) {
  return props.errors[field]?.[0]
}
</script>

<template>
  <form class="grid gap-5" novalidate @submit.prevent="submit">
    <div class="grid min-w-0 content-start gap-1.5">
      <label for="title" class="text-[0.9375rem] font-semibold">Otsikko <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></label>
      <input
        id="title"
        class="form-control"
        v-model="form.title"
        type="text"
        maxlength="255"
        :aria-invalid="Boolean(fieldError('title'))"
        :aria-describedby="fieldError('title') ? 'title-error' : undefined"
      />
      <p v-if="fieldError('title')" id="title-error" class="text-sm text-red-600 dark:text-red-400">{{ fieldError('title') }}</p>
    </div>

    <div class="grid min-w-0 content-start gap-1.5">
      <label for="description" class="text-[0.9375rem] font-semibold">Kuvaus</label>
      <textarea
        id="description"
        class="form-control resize-y"
        v-model="form.description"
        rows="4"
        :aria-invalid="Boolean(fieldError('description'))"
        :aria-describedby="fieldError('description') ? 'description-error' : undefined"
      ></textarea>
      <p v-if="fieldError('description')" id="description-error" class="text-sm text-red-600 dark:text-red-400">
        {{ fieldError('description') }}
      </p>
    </div>

    <div class="grid gap-5 sm:grid-cols-3">
      <div class="grid min-w-0 content-start gap-1.5">
        <label for="priority" class="text-[0.9375rem] font-semibold">Prioriteetti</label>
        <select
          id="priority"
          class="form-control"
          v-model="form.priority"
          :aria-invalid="Boolean(fieldError('priority'))"
          :aria-describedby="fieldError('priority') ? 'priority-error' : undefined"
        >
          <option v-for="(label, value) in priorityLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p v-if="fieldError('priority')" id="priority-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('priority') }}
        </p>
      </div>

      <div class="grid min-w-0 content-start gap-1.5">
        <label for="status" class="text-[0.9375rem] font-semibold">Tila</label>
        <select
          id="status"
          class="form-control"
          v-model="form.status"
          :aria-invalid="Boolean(fieldError('status'))"
          :aria-describedby="fieldError('status') ? 'status-error' : undefined"
        >
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p v-if="fieldError('status')" id="status-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('status') }}
        </p>
      </div>

      <div class="grid min-w-0 content-start gap-1.5">
        <label for="due_date" class="text-[0.9375rem] font-semibold">Määräpäivä</label>
        <input
          id="due_date"
          class="form-control"
          v-model="form.due_date"
          type="date"
          :aria-invalid="Boolean(fieldError('due_date'))"
          :aria-describedby="fieldError('due_date') ? 'due_date-error' : undefined"
        />
        <p v-if="fieldError('due_date')" id="due_date-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('due_date') }}
        </p>
      </div>
    </div>

    <fieldset class="grid min-w-0 gap-4 border-t border-slate-200 pt-5 dark:border-slate-800">
      <legend class="float-left mb-1 text-lg font-bold">Sijainti</legend>

      <div class="grid min-w-0 content-start gap-1.5">
        <label for="street_address" class="text-[0.9375rem] font-semibold">Katuosoite</label>
        <AddressAutocomplete
          id="street_address"
          v-model="form.street_address"
          :invalid="Boolean(fieldError('street_address'))"
          :describedby="fieldError('street_address') ? 'street_address-error' : undefined"
          @select="applySuggestion"
        />
        <p v-if="fieldError('street_address')" id="street_address-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('street_address') }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
        <div class="grid min-w-0 content-start gap-1.5">
          <label for="postal_code" class="text-[0.9375rem] font-semibold">Postinumero</label>
          <input
            id="postal_code"
            class="form-control"
            v-model="form.postal_code"
            type="text"
            maxlength="10"
            inputmode="numeric"
            autocomplete="postal-code"
            :aria-invalid="Boolean(fieldError('postal_code'))"
            :aria-describedby="fieldError('postal_code') ? 'postal_code-error' : undefined"
          />
          <p v-if="fieldError('postal_code')" id="postal_code-error" class="text-sm text-red-600 dark:text-red-400">
            {{ fieldError('postal_code') }}
          </p>
        </div>

        <div class="grid min-w-0 content-start gap-1.5">
          <label for="city" class="text-[0.9375rem] font-semibold">Kaupunki</label>
          <input
            id="city"
            class="form-control"
            v-model="form.city"
            type="text"
            maxlength="100"
            autocomplete="address-level2"
            :aria-invalid="Boolean(fieldError('city'))"
            :aria-describedby="fieldError('city') ? 'city-error' : undefined"
          />
          <p v-if="fieldError('city')" id="city-error" class="text-sm text-red-600 dark:text-red-400">
            {{ fieldError('city') }}
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="geocoding || addressQuery === ''"
          @click="findCoordinates"
        >
          {{ geocoding ? 'Haetaan…' : 'Hae koordinaatit osoitteesta' }}
        </button>
        <p
          v-if="geocodeMessage"
          class="text-sm"
          :class="geocodeFailed ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'"
          role="status"
        >
          {{ geocodeMessage }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="grid min-w-0 content-start gap-1.5">
          <label for="latitude" class="text-[0.9375rem] font-semibold">Leveysaste</label>
          <input
            id="latitude"
            class="form-control tabular-nums"
            v-model="form.latitude"
            type="number"
            step="any"
            min="-90"
            max="90"
            placeholder="esim. 61.4980"
            :aria-invalid="Boolean(fieldError('latitude'))"
            :aria-describedby="fieldError('latitude') ? 'latitude-error' : undefined"
          />
          <p v-if="fieldError('latitude')" id="latitude-error" class="text-sm text-red-600 dark:text-red-400">
            {{ fieldError('latitude') }}
          </p>
        </div>

        <div class="grid min-w-0 content-start gap-1.5">
          <label for="longitude" class="text-[0.9375rem] font-semibold">Pituusaste</label>
          <input
            id="longitude"
            class="form-control tabular-nums"
            v-model="form.longitude"
            type="number"
            step="any"
            min="-180"
            max="180"
            placeholder="esim. 23.7660"
            :aria-invalid="Boolean(fieldError('longitude'))"
            :aria-describedby="fieldError('longitude') ? 'longitude-error' : undefined"
          />
          <p v-if="fieldError('longitude')" id="longitude-error" class="text-sm text-red-600 dark:text-red-400">
            {{ fieldError('longitude') }}
          </p>
        </div>
      </div>

      <LocationPicker :latitude="form.latitude" :longitude="form.longitude" @pick="pickLocation" />

      <button
        v-if="hasCoordinates"
        type="button"
        class="inline-flex min-h-11 items-center justify-self-start text-sm font-semibold text-primary-700 hover:underline lg:min-h-9 dark:text-primary-400"
        @click="clearCoordinates"
      >
        Poista sijainti kartalta
      </button>
    </fieldset>

    <div class="flex flex-col gap-3 pt-2 sm:flex-row">
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ saving ? 'Tallennetaan…' : submitLabel }}
      </button>
      <button type="button" class="btn btn-secondary" :disabled="saving" @click="emit('cancel')">
        Peruuta
      </button>
    </div>
  </form>
</template>
