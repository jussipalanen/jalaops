import { ref } from 'vue'
import { apiGet } from '@/api/client'

// The customers to choose from in "Asiakas" selects, as { id, name }. Empty
// until loaded, and stays empty if loading fails (the select still works).
export function useCustomerOptions() {
  const customers = ref([])

  apiGet('/customers')
    .then((response) => {
      customers.value = Array.isArray(response?.data) ? response.data.map(({ id, name }) => ({ id, name })) : []
    })
    .catch(() => {})

  return customers
}
