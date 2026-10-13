<script setup>
import { computed, defineAsyncComponent, reactive, ref } from 'vue'
import { apiGet } from '@/api/client'
import AddressAutocomplete from '@/components/AddressAutocomplete.vue'
import SignaturePad from '@/components/SignaturePad.vue'
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
  // Customers for the "Asiakas" select, as { id, name }.
  customers: { type: Array, default: () => [] },
})

const emit = defineEmits(['submit', 'cancel'])

const form = reactive({
  customer_id: props.request.customer?.id ?? '',
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
  signed_by: props.request.signed_by ?? '',
})

// Sign-off for completed requests. `signature` stays undefined while the saved
// signature is kept; a new drawing is a PNG data URL and a removed one null.
const signature = ref(undefined)
const keepsSavedSignature = computed(() => Boolean(props.request.has_signature) && signature.value === undefined)
const redrawing = ref(false)
const savedSignatureUrl = computed(() =>
  props.request.id ? `/api/requests/${props.request.id}/signature?v=${encodeURIComponent(props.request.signed_at ?? '')}` : null,
)

function removeSavedSignature() {
  signature.value = null
}

// The API expects null for empty optional fields.
function textOrNull(value) {
  return String(value).trim() === '' ? null : String(value).trim()
}

function numberOrNull(value) {
  return value === '' || value === null ? null : Number(value)
}

function submit() {
  const { signed_by: signedBy, ...fields } = form

  emit('submit', {
    ...fields,
    customer_id: form.customer_id === '' ? null : Number(form.customer_id),
    description: form.description.trim() === '' ? null : form.description,
    due_date: form.due_date === '' ? null : form.due_date,
    street_address: textOrNull(form.street_address),
    postal_code: textOrNull(form.postal_code),
    city: textOrNull(form.city),
    latitude: numberOrNull(form.latitude),
    longitude: numberOrNull(form.longitude),
    // Only completed requests are signed off; the signature is left out to keep the saved one.
    ...(form.status === 'completed' && {
      signed_by: textOrNull(signedBy),
      ...(signature.value !== undefined && { completion_signature: signature.value }),
    }),
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
    <div class="grid min-w-0 content-start gap-1.5 sm:max-w-md">
      <label for="customer_id" class="text-[0.9375rem] font-semibold">Asiakas</label>
      <select
        id="customer_id"
        v-model="form.customer_id"
        class="form-control"
        :aria-invalid="Boolean(fieldError('customer_id'))"
        :aria-describedby="fieldError('customer_id') ? 'customer_id-error' : undefined"
      >
        <option value="">Ei asiakasta</option>
        <option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }}</option>
      </select>
      <p v-if="fieldError('customer_id')" id="customer_id-error" class="text-sm text-red-600 dark:text-red-400">
        {{ fieldError('customer_id') }}
      </p>
    </div>

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

    <fieldset v-if="form.status === 'completed'" class="grid min-w-0 gap-4 border-t border-slate-200 pt-5 dark:border-slate-800">
      <legend class="float-left mb-1 text-lg font-bold">Kuittaus</legend>
      <p class="-mt-2 text-sm text-slate-500 dark:text-slate-400">Valinnainen: kuittaa työ tehdyksi nimellä ja allekirjoituksella.</p>

      <div class="grid min-w-0 content-start gap-1.5 sm:max-w-sm">
        <label for="signed_by" class="text-[0.9375rem] font-semibold">Kuittaajan nimi</label>
        <input
          id="signed_by"
          class="form-control"
          v-model="form.signed_by"
          type="text"
          maxlength="100"
          autocomplete="name"
          :aria-invalid="Boolean(fieldError('signed_by'))"
          :aria-describedby="fieldError('signed_by') ? 'signed_by-error' : undefined"
        />
        <p v-if="fieldError('signed_by')" id="signed_by-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('signed_by') }}
        </p>
      </div>

      <div class="grid min-w-0 content-start gap-1.5">
        <span class="text-[0.9375rem] font-semibold">Allekirjoitus</span>

        <div v-if="keepsSavedSignature && !redrawing">
          <img
            :src="savedSignatureUrl"
            alt="Tallennettu allekirjoitus"
            class="block aspect-[3/1] w-full rounded-lg border border-slate-300 bg-white object-contain dark:border-slate-600"
          />
          <div class="mt-1.5 flex flex-wrap gap-x-5">
            <button type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-700 hover:underline lg:min-h-9 dark:text-primary-400" @click="redrawing = true">
              Piirrä uusi allekirjoitus
            </button>
            <button type="button" class="inline-flex min-h-11 items-center text-sm font-semibold text-red-600 hover:underline lg:min-h-9 dark:text-red-400" @click="removeSavedSignature">
              Poista allekirjoitus
            </button>
          </div>
        </div>

        <SignaturePad
          v-else
          id="completion_signature"
          :invalid="Boolean(fieldError('completion_signature'))"
          :describedby="fieldError('completion_signature') ? 'completion_signature-error' : undefined"
          @change="signature = $event"
        />
        <p v-if="fieldError('completion_signature')" id="completion_signature-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('completion_signature') }}
        </p>
      </div>
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
