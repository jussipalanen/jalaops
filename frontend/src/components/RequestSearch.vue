<script setup>
import { ref, watch } from 'vue'

// Search box for the title, street address, postal code or city. The parent debounces and
// stores the value; this keeps what the user typed until the URL catches up.
const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['search'])

const text = ref(props.modelValue)

// Clearing the filters (or going back in history) empties or changes the box.
watch(
  () => props.modelValue,
  (value) => {
    if (value !== text.value.trim()) {
      text.value = value
    }
  },
)
</script>

<template>
  <div class="grid gap-1">
    <label :for="id" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Haku</label>
    <input
      :id="id"
      v-model="text"
      class="form-control lg:min-h-9 lg:py-1.5"
      type="search"
      maxlength="100"
      autocomplete="off"
      enterkeyhint="search"
      placeholder="Otsikko, osoite tai kaupunki"
      @input="emit('search', text)"
      @keydown.enter.prevent="emit('search', text)"
    />
  </div>
</template>
