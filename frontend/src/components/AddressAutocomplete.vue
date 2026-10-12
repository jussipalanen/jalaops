<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { apiGet } from '@/api/client'

// Street address field that suggests addresses while typing (Photon through the
// backend). Choosing one emits `select` with the street address, postal code,
// city and coordinates. Follows the ARIA combobox pattern: arrow keys move in
// the list, Enter chooses and Escape closes it.
const model = defineModel({ type: String, default: '' })

const props = defineProps({
  id: { type: String, required: true },
  invalid: { type: Boolean, default: false },
  describedby: { type: String, default: undefined },
})

const emit = defineEmits(['select'])

const suggestions = ref([])
const open = ref(false)
const active = ref(-1)
const message = ref('')
const listId = computed(() => `${props.id}-suggestions`)

let timer = null
let latestQuery = ''

async function loadSuggestions(query) {
  latestQuery = query

  try {
    const response = await apiGet(`/geocode/suggest?q=${encodeURIComponent(query)}`)

    // An older, slower answer must not replace the suggestions for newer text.
    if (query !== latestQuery) {
      return
    }

    suggestions.value = response.data
    active.value = -1
    open.value = true
    message.value = response.data.length === 0 ? 'Ei osoite-ehdotuksia.' : ''
  } catch {
    if (query === latestQuery) {
      suggestions.value = []
      open.value = true
      message.value = 'Osoite-ehdotuksia ei juuri nyt saada. Kirjoita osoite itse.'
    }
  }
}

// Suggestions are fetched after a short pause in typing, from three characters on.
// The text is read from the event: the model only updates after the parent re-renders.
function onInput(event) {
  clearTimeout(timer)
  const query = event.target.value.trim()

  if (query.length < 3) {
    latestQuery = ''
    close()
    return
  }

  timer = setTimeout(() => loadSuggestions(query), 300)
}

function close() {
  open.value = false
  active.value = -1
}

function choose(suggestion) {
  model.value = suggestion.street_address
  emit('select', suggestion)
  latestQuery = ''
  close()
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close()
    return
  }

  if (!open.value || suggestions.value.length === 0) {
    return
  }

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    const step = event.key === 'ArrowDown' ? 1 : -1
    active.value = (active.value + step + suggestions.value.length) % suggestions.value.length
  } else if (event.key === 'Enter' && active.value >= 0) {
    // Choose the highlighted address instead of submitting the form.
    event.preventDefault()
    choose(suggestions.value[active.value])
  }
}

// On phones the on-screen keyboard covers the lower half of the screen, so the
// field moves to the top to leave room for the suggestions below it.
function onFocus(event) {
  if (window.matchMedia?.('(max-width: 39.99rem)').matches) {
    event.target.scrollIntoView({ block: 'start', behavior: 'smooth' })
  }
}

onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="relative">
    <input
      :id="id"
      v-model="model"
      class="form-control scroll-mt-28"
      type="text"
      maxlength="255"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-activedescendant="active >= 0 ? `${listId}-${active}` : undefined"
      :aria-invalid="invalid"
      :aria-describedby="describedby"
      placeholder="Aloita kirjoittamalla, esim. Hämeenkatu 1 Tampere"
      @input="onInput"
      @keydown="onKeydown"
      @focus="onFocus"
      @blur="close"
    />

    <ul
      v-show="open"
      :id="listId"
      role="listbox"
      aria-label="Osoite-ehdotukset"
      class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <li
        v-for="(suggestion, index) in suggestions"
        :id="`${listId}-${index}`"
        :key="suggestion.label"
        role="option"
        :aria-selected="index === active"
        class="flex min-h-11 cursor-pointer items-center px-3 py-2 lg:min-h-9"
        :class="index === active ? 'bg-primary-100 dark:bg-primary-500/15' : 'hover:bg-slate-100 dark:hover:bg-slate-800'"
        @mousedown.prevent="choose(suggestion)"
      >
        {{ suggestion.label }}
      </li>
      <li v-if="message" class="px-3 py-2 text-sm text-slate-500 dark:text-slate-400" role="presentation">
        {{ message }}
      </li>
    </ul>
  </div>
</template>
