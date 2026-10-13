<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiGet, apiPost, apiPut } from '@/api/client'
import CustomerForm from '@/components/CustomerForm.vue'
import { pageTitle } from '@/pageTitle'

// Creates a customer (/customers/new) or edits one (/customers/:id/edit).
const route = useRoute()
const router = useRouter()
const editing = computed(() => Boolean(route.params.id))

const customer = ref(editing.value ? null : {})
const loading = ref(editing.value)
const notFound = ref(false)
const errors = ref({})
const error = ref('')
const saving = ref(false)

onMounted(async () => {
  if (!editing.value) return

  try {
    customer.value = (await apiGet(`/customers/${route.params.id}`)).data
    document.title = pageTitle(customer.value.name)
  } catch (e) {
    e.status === 404 ? (notFound.value = true) : (error.value = 'Asiakkaan lataaminen epäonnistui.')
  } finally {
    loading.value = false
  }
})

async function save(data) {
  saving.value = true
  errors.value = {}
  error.value = ''

  try {
    await (editing.value ? apiPut(`/customers/${route.params.id}`, data) : apiPost('/customers', data))
    router.push('/customers')
  } catch (e) {
    if (e.status === 422) {
      errors.value = e.errors
      error.value = 'Tarkista lomakkeen tiedot.'
    } else {
      error.value = 'Asiakkaan tallentaminen epäonnistui.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <h1 class="page-title">{{ editing ? 'Muokkaa asiakasta' : 'Uusi asiakas' }}</h1>
    </header>

    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan asiakasta…</div>

    <div v-else-if="notFound" class="card card-body">
      <p class="mb-4">Asiakasta ei löytynyt.</p>
      <RouterLink to="/customers" class="btn btn-secondary">Takaisin asiakkaisiin</RouterLink>
    </div>

    <div v-else-if="customer" class="card card-body">
      <CustomerForm
        :customer="customer"
        :errors="errors"
        :saving="saving"
        :submit-label="editing ? 'Tallenna' : 'Luo asiakas'"
        @submit="save"
        @cancel="router.push('/customers')"
      />
    </div>
  </section>
</template>
