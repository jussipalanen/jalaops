<script setup>
import { reactive } from 'vue'

// Create and edit form for a customer. Emits `submit` with the fields the API
// expects (empty optional fields as null) and `cancel`.
const props = defineProps({
  customer: { type: Object, default: () => ({}) },
  // Field errors from the API, e.g. { name: ['Kenttä nimi on pakollinen.'] }.
  errors: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
  submitLabel: { type: String, default: 'Tallenna' },
})

const emit = defineEmits(['submit', 'cancel'])

const fields = [
  { key: 'name', label: 'Nimi', required: true, autocomplete: 'organization' },
  { key: 'business_id', label: 'Y-tunnus', placeholder: '1234567-8', short: true },
  { key: 'contact_person', label: 'Yhteyshenkilö', autocomplete: 'name' },
  { key: 'email', label: 'Sähköposti', type: 'email', autocomplete: 'email' },
  { key: 'phone', label: 'Puhelin', type: 'tel', autocomplete: 'tel', short: true },
  { key: 'billing_street_address', label: 'Laskutusosoite', autocomplete: 'street-address' },
  { key: 'billing_postal_code', label: 'Postinumero', autocomplete: 'postal-code', short: true },
  { key: 'billing_city', label: 'Kaupunki', autocomplete: 'address-level2' },
]

const form = reactive({
  ...Object.fromEntries(fields.map(({ key }) => [key, props.customer[key] ?? ''])),
  payment_terms_days: props.customer.payment_terms_days ?? 14,
  notes: props.customer.notes ?? '',
})

function submit() {
  const data = Object.fromEntries(
    Object.entries(form).map(([key, value]) => [key, typeof value === 'string' && value.trim() === '' ? null : value]),
  )
  emit('submit', { ...data, payment_terms_days: Number(form.payment_terms_days) })
}

function fieldError(field) {
  return props.errors[field]?.[0]
}
</script>

<template>
  <form class="grid gap-5" novalidate @submit.prevent="submit">
    <div class="grid gap-5 sm:grid-cols-2">
      <div v-for="field in fields" :key="field.key" class="grid min-w-0 content-start gap-1.5">
        <label :for="field.key" class="text-[0.9375rem] font-semibold">
          {{ field.label }}
          <span v-if="field.required" class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>
        </label>
        <input
          :id="field.key"
          v-model="form[field.key]"
          class="form-control"
          :type="field.type ?? 'text'"
          :placeholder="field.placeholder"
          :autocomplete="field.autocomplete"
          :aria-invalid="Boolean(fieldError(field.key))"
          :aria-describedby="fieldError(field.key) ? `${field.key}-error` : undefined"
        />
        <p v-if="fieldError(field.key)" :id="`${field.key}-error`" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError(field.key) }}
        </p>
      </div>

      <div class="grid min-w-0 content-start gap-1.5">
        <label for="payment_terms_days" class="text-[0.9375rem] font-semibold">Maksuehto (päivää)</label>
        <input
          id="payment_terms_days"
          v-model="form.payment_terms_days"
          class="form-control"
          type="number"
          min="0"
          max="365"
          inputmode="numeric"
          :aria-invalid="Boolean(fieldError('payment_terms_days'))"
          :aria-describedby="fieldError('payment_terms_days') ? 'payment_terms_days-error' : 'payment_terms_days-hint'"
        />
        <p v-if="fieldError('payment_terms_days')" id="payment_terms_days-error" class="text-sm text-red-600 dark:text-red-400">
          {{ fieldError('payment_terms_days') }}
        </p>
        <p v-else id="payment_terms_days-hint" class="text-sm text-slate-500 dark:text-slate-400">Laskun eräpäivä laskupäivästä.</p>
      </div>
    </div>

    <div class="grid min-w-0 content-start gap-1.5">
      <label for="notes" class="text-[0.9375rem] font-semibold">Muistiinpanot</label>
      <textarea id="notes" v-model="form.notes" class="form-control resize-y" rows="3"></textarea>
    </div>

    <div class="flex flex-col gap-3 pt-2 sm:flex-row">
      <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Tallennetaan…' : submitLabel }}</button>
      <button type="button" class="btn btn-secondary" :disabled="saving" @click="emit('cancel')">Peruuta</button>
    </div>
  </form>
</template>
