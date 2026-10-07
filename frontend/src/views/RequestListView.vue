<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiDelete, apiGet } from '@/api/client'
import { formatDate, priorityLabels, statusLabels } from '@/labels'

const requests = ref([])
const loading = ref(true)
const error = ref('')

async function loadRequests() {
  loading.value = true
  error.value = ''

  try {
    const response = await apiGet('/requests')
    requests.value = response.data
  } catch {
    error.value = 'Pyyntöjen lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
}

async function deleteRequest(request) {
  if (!window.confirm(`Poistetaanko pyyntö "${request.title}"?`)) {
    return
  }

  try {
    await apiDelete(`/requests/${request.id}`)
    requests.value = requests.value.filter((item) => item.id !== request.id)
  } catch {
    error.value = 'Pyynnön poistaminen epäonnistui.'
  }
}

onMounted(loadRequests)
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Pyynnöt</h1>
        <p v-if="!loading && requests.length > 0" class="page-subtitle">
          {{ requests.length }} {{ requests.length === 1 ? 'pyyntö' : 'pyyntöä' }}
        </p>
      </div>
      <RouterLink to="/requests/new" class="btn btn-primary new-button">+ Uusi pyyntö</RouterLink>
    </header>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body muted">Ladataan pyyntöjä…</div>

    <div v-else-if="requests.length === 0 && !error" class="card card-body muted">Ei pyyntöjä.</div>

    <div v-else-if="requests.length > 0" class="card table-card">
      <table class="request-table">
        <thead>
          <tr>
            <th>Otsikko</th>
            <th>Prioriteetti</th>
            <th>Tila</th>
            <th>Määräpäivä</th>
            <th><span class="visually-hidden">Toiminnot</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in requests" :key="request.id">
            <td class="cell-title">{{ request.title }}</td>
            <td data-label="Prioriteetti">
              <span class="badge" :class="`priority-${request.priority}`">
                {{ priorityLabels[request.priority] }}
              </span>
            </td>
            <td data-label="Tila">
              <span class="badge" :class="`status-${request.status}`">
                {{ statusLabels[request.status] }}
              </span>
            </td>
            <td data-label="Määräpäivä">{{ formatDate(request.due_date) }}</td>
            <td class="cell-actions">
              <div class="actions">
                <RouterLink :to="`/requests/${request.id}/edit`" class="btn btn-secondary">
                  Muokkaa
                </RouterLink>
                <button type="button" class="btn btn-danger" @click="deleteRequest(request)">
                  Poista
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.table-card {
  overflow: hidden;
}

.request-table {
  width: 100%;
  border-collapse: collapse;
}

.request-table th {
  padding: 0.75rem 1rem;
  background: var(--color-primary-softer);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.request-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
}

.request-table tbody tr:last-child td {
  border-bottom: 0;
}

.request-table tbody tr:hover {
  background: var(--color-primary-softer);
}

.cell-title {
  font-weight: 600;
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.priority-low {
  background: var(--color-neutral-soft);
  color: var(--color-neutral);
}

.priority-normal {
  background: var(--color-primary-soft);
  color: var(--color-primary-hover);
}

.priority-high {
  background: var(--color-danger-soft);
  color: #b91c1c;
}

.status-open {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.status-in_progress {
  background: var(--color-primary-soft);
  color: var(--color-primary-hover);
}

.status-completed {
  background: var(--color-success-soft);
  color: var(--color-success);
}

/* Tablet: tighter cells so all columns fit. */
@media (max-width: 1023px) {
  .request-table th,
  .request-table td {
    padding: 0.625rem 0.75rem;
  }
}

/* Phone: each request becomes its own card with labelled fields. */
@media (max-width: 639px) {
  .new-button {
    width: 100%;
  }

  .table-card {
    background: transparent;
    border: 0;
    box-shadow: none;
  }

  .request-table thead {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
  }

  .request-table,
  .request-table tbody,
  .request-table tr,
  .request-table td {
    display: block;
  }

  .request-table tbody tr {
    margin-bottom: 0.75rem;
    padding: 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
  }

  .request-table tbody tr:hover {
    background: var(--color-surface);
  }

  .request-table td,
  .request-table tbody tr:last-child td {
    padding: 0.375rem 0;
    border-bottom: 0;
  }

  .request-table td[data-label] {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }

  .request-table td[data-label]::before {
    content: attr(data-label);
    color: var(--color-muted);
    font-size: 0.875rem;
  }

  .cell-title {
    margin-bottom: 0.25rem;
    font-size: 1.0625rem;
  }

  .cell-actions {
    margin-top: 0.5rem;
  }

  .actions {
    justify-content: stretch;
  }

  .actions > * {
    flex: 1;
  }
}
</style>
