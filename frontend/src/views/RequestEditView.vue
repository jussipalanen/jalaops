<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiGet, apiPut } from '@/api/client'
import RequestForm from '@/components/RequestForm.vue'

const route = useRoute()
const router = useRouter()

const request = ref(null)
const loading = ref(true)
const notFound = ref(false)
const errors = ref({})
const error = ref('')
const saving = ref(false)

onMounted(async () => {
  try {
    const response = await apiGet(`/requests/${route.params.id}`)
    request.value = response.data
  } catch (e) {
    if (e.status === 404) {
      notFound.value = true
    } else {
      error.value = 'Pyynnön lataaminen epäonnistui.'
    }
  } finally {
    loading.value = false
  }
})

async function update(data) {
  saving.value = true
  errors.value = {}
  error.value = ''

  try {
    await apiPut(`/requests/${route.params.id}`, data)
    router.push('/requests')
  } catch (e) {
    if (e.status === 422) {
      errors.value = e.errors
      error.value = 'Tarkista lomakkeen tiedot.'
    } else {
      error.value = 'Pyynnön tallentaminen epäonnistui.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="form-page">
    <header class="page-header">
      <h1 class="page-title">Muokkaa pyyntöä</h1>
    </header>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body muted">Ladataan pyyntöä…</div>

    <div v-else-if="notFound" class="card card-body">
      <p>Pyyntöä ei löytynyt.</p>
      <RouterLink to="/requests" class="btn btn-secondary">Takaisin pyyntöihin</RouterLink>
    </div>

    <div v-else-if="request" class="card card-body">
      <RequestForm
        :request="request"
        :errors="errors"
        :saving="saving"
        @submit="update"
        @cancel="router.push('/requests')"
      />
    </div>
  </section>
</template>

<style scoped>
.form-page {
  max-width: 760px;
}
</style>
