<script setup>
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiDelete, apiGet } from '@/api/client'

// The customer register: search, open a customer's requests, edit or delete.
const customers = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')

let timer = null

async function load() {
  loading.value = true
  error.value = ''

  try {
    const query = search.value.trim() ? `?search=${encodeURIComponent(search.value.trim())}` : ''
    customers.value = (await apiGet(`/customers${query}`)).data
  } catch {
    error.value = 'Asiakkaiden lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
}

// Searching waits for a short pause in typing.
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})

async function remove(customer) {
  if (!window.confirm(`Poistetaanko asiakas "${customer.name}"?`)) return

  try {
    await apiDelete(`/customers/${customer.id}`)
    customers.value = customers.value.filter((item) => item.id !== customer.id)
  } catch (e) {
    error.value = e.status === 409 ? e.data.message : 'Asiakkaan poistaminen epäonnistui.'
  }
}

load()
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Asiakkaat</h1>
        <p v-if="!loading && customers.length" class="page-subtitle mt-1">
          {{ customers.length }} {{ customers.length === 1 ? 'asiakas' : 'asiakasta' }}
        </p>
      </div>
      <RouterLink to="/customers/new" class="btn btn-primary w-full sm:w-auto">+ Uusi asiakas</RouterLink>
    </header>

    <div class="card mb-4 p-4" role="search" aria-label="Hae asiakkaita">
      <label for="customer-search" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Haku</label>
      <input
        id="customer-search"
        v-model="search"
        class="form-control mt-1 sm:max-w-96 lg:min-h-9 lg:py-1.5"
        type="search"
        placeholder="Nimi, y-tunnus, yhteyshenkilö tai kaupunki"
        autocomplete="off"
      />
    </div>

    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan asiakkaita…</div>

    <div v-else-if="customers.length === 0 && !error" class="card px-6 py-12 text-center">
      <p class="font-semibold">{{ search.trim() ? 'Ei hakua vastaavia asiakkaita.' : 'Ei asiakkaita.' }}</p>
    </div>

    <ul v-else class="grid gap-3 md:grid-cols-2">
      <li v-for="customer in customers" :key="customer.id" class="card card-body grid content-start gap-3">
        <div>
          <h2 class="text-lg font-bold break-words">{{ customer.name }}</h2>
          <p v-if="customer.business_id" class="text-sm text-slate-500 tabular-nums dark:text-slate-400">Y-tunnus {{ customer.business_id }}</p>
        </div>
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
          <template v-if="customer.contact_person">
            <dt class="text-slate-500 dark:text-slate-400">Yhteyshenkilö</dt>
            <dd>{{ customer.contact_person }}</dd>
          </template>
          <template v-if="customer.email">
            <dt class="text-slate-500 dark:text-slate-400">Sähköposti</dt>
            <dd class="break-all"><a :href="`mailto:${customer.email}`" class="text-primary-700 hover:underline dark:text-primary-400">{{ customer.email }}</a></dd>
          </template>
          <template v-if="customer.billing_city">
            <dt class="text-slate-500 dark:text-slate-400">Laskutus</dt>
            <dd>{{ [customer.billing_street_address, [customer.billing_postal_code, customer.billing_city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}</dd>
          </template>
          <dt class="text-slate-500 dark:text-slate-400">Maksuehto</dt>
          <dd>{{ customer.payment_terms_days }} pv</dd>
        </dl>
        <div class="flex flex-wrap gap-2 max-sm:*:flex-1">
          <RouterLink :to="`/requests?customer=${customer.id}`" class="btn btn-secondary">
            Pyynnöt ({{ customer.requests_count }})
          </RouterLink>
          <RouterLink :to="`/customers/${customer.id}/edit`" class="btn btn-secondary" :aria-label="`Muokkaa: ${customer.name}`">Muokkaa</RouterLink>
          <button type="button" class="btn btn-danger" :aria-label="`Poista: ${customer.name}`" @click="remove(customer)">Poista</button>
        </div>
      </li>
    </ul>
  </section>
</template>
