import { computed, onScopeDispose } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { priorityLabels, statusLabels } from '@/labels'

// Status and priority filters and the address search, kept in the URL
// (?status=open&priority=high&search=tampere), so a filtered page survives a
// reload and can be shared. Unknown filter values are ignored.
export function useRequestFilters() {
  const route = useRoute()
  const router = useRouter()

  const filters = computed(() => ({
    status: route.query.status in statusLabels ? route.query.status : '',
    priority: route.query.priority in priorityLabels ? route.query.priority : '',
    search: typeof route.query.search === 'string' ? route.query.search.trim() : '',
  }))

  const hasFilters = computed(() => Object.values(filters.value).some(Boolean))

  // "/requests?status=open" for the API, leaving empty filters out.
  const apiPath = computed(() => {
    const params = new URLSearchParams(
      Object.entries(filters.value).filter(([, filterValue]) => filterValue),
    ).toString()

    return params ? `/requests?${params}` : '/requests'
  })

  function setFilter(name, value) {
    const query = { ...filters.value, [name]: value.trim() }

    // Leave empty filters out of the URL.
    router.replace({
      query: Object.fromEntries(Object.entries(query).filter(([, filterValue]) => filterValue)),
    })
  }

  // Typing in the search box updates the URL (and so the results) after a short pause.
  let searchTimer = null

  function setSearch(value) {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => setFilter('search', value), 300)
  }

  // A search still waiting when the page is left must not change the next page's URL.
  onScopeDispose(() => clearTimeout(searchTimer))

  function clearFilters() {
    clearTimeout(searchTimer)
    router.replace({ query: {} })
  }

  return { filters, hasFilters, apiPath, setFilter, setSearch, clearFilters }
}
