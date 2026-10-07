<script setup>
import { reactive } from 'vue'
import { priorityLabels, statusLabels } from '@/labels'

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
})

function submit() {
  emit('submit', {
    ...form,
    // The API expects null for empty optional fields.
    description: form.description.trim() === '' ? null : form.description,
    due_date: form.due_date === '' ? null : form.due_date,
  })
}

function fieldError(field) {
  return props.errors[field]?.[0]
}
</script>

<template>
  <form class="request-form" novalidate @submit.prevent="submit">
    <div class="field">
      <label for="title">Otsikko <span class="required" aria-hidden="true">*</span></label>
      <input
        id="title"
        v-model="form.title"
        type="text"
        maxlength="255"
        :aria-invalid="Boolean(fieldError('title'))"
        :aria-describedby="fieldError('title') ? 'title-error' : undefined"
      />
      <p v-if="fieldError('title')" id="title-error" class="field-error">{{ fieldError('title') }}</p>
    </div>

    <div class="field">
      <label for="description">Kuvaus</label>
      <textarea
        id="description"
        v-model="form.description"
        rows="4"
        :aria-invalid="Boolean(fieldError('description'))"
        :aria-describedby="fieldError('description') ? 'description-error' : undefined"
      ></textarea>
      <p v-if="fieldError('description')" id="description-error" class="field-error">
        {{ fieldError('description') }}
      </p>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="priority">Prioriteetti</label>
        <select
          id="priority"
          v-model="form.priority"
          :aria-invalid="Boolean(fieldError('priority'))"
          :aria-describedby="fieldError('priority') ? 'priority-error' : undefined"
        >
          <option v-for="(label, value) in priorityLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p v-if="fieldError('priority')" id="priority-error" class="field-error">
          {{ fieldError('priority') }}
        </p>
      </div>

      <div class="field">
        <label for="status">Tila</label>
        <select
          id="status"
          v-model="form.status"
          :aria-invalid="Boolean(fieldError('status'))"
          :aria-describedby="fieldError('status') ? 'status-error' : undefined"
        >
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p v-if="fieldError('status')" id="status-error" class="field-error">
          {{ fieldError('status') }}
        </p>
      </div>

      <div class="field">
        <label for="due_date">Määräpäivä</label>
        <input
          id="due_date"
          v-model="form.due_date"
          type="date"
          :aria-invalid="Boolean(fieldError('due_date'))"
          :aria-describedby="fieldError('due_date') ? 'due_date-error' : undefined"
        />
        <p v-if="fieldError('due_date')" id="due_date-error" class="field-error">
          {{ fieldError('due_date') }}
        </p>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ saving ? 'Tallennetaan…' : submitLabel }}
      </button>
      <button type="button" class="btn btn-secondary" :disabled="saving" @click="emit('cancel')">
        Peruuta
      </button>
    </div>
  </form>
</template>

<style scoped>
.request-form {
  display: grid;
  gap: 1.25rem;
}

.field {
  display: grid;
  gap: 0.375rem;
  min-width: 0;
}

.field label {
  font-size: 0.9375rem;
  font-weight: 600;
}

.required {
  color: var(--color-danger);
}

.field input,
.field select,
.field textarea {
  width: 100%;
  min-height: 44px;
  padding: 0.625rem 0.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
}

.field textarea {
  resize: vertical;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgb(2 132 199 / 0.2);
  outline: none;
}

.field [aria-invalid='true'] {
  border-color: var(--color-danger);
}

.field [aria-invalid='true']:focus {
  box-shadow: 0 0 0 3px rgb(220 38 38 / 0.2);
}

.field-error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.875rem;
}

.field-row {
  display: grid;
  gap: 1.25rem;
}

@media (min-width: 640px) {
  .field-row {
    grid-template-columns: repeat(3, 1fr);
  }
}

.form-actions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding-top: 0.5rem;
}

@media (min-width: 640px) {
  .form-actions {
    flex-direction: row;
  }
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
