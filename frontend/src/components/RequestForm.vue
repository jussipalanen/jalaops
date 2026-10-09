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
