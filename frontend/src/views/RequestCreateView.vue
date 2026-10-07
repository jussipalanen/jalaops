<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiPost } from '@/api/client'
import RequestForm from '@/components/RequestForm.vue'

const router = useRouter()

const errors = ref({})
const error = ref('')
const saving = ref(false)

async function create(data) {
  saving.value = true
  errors.value = {}
  error.value = ''

  try {
    await apiPost('/requests', data)
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
      <h1 class="page-title">Uusi pyyntö</h1>
    </header>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

    <div class="card card-body">
      <RequestForm
        :errors="errors"
        :saving="saving"
        submit-label="Luo pyyntö"
        @submit="create"
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
