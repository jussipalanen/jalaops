import { computed, onScopeDispose } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { duePeriodLabels, priorityLabels, statusLabels } from '@/labels'

const DATE = /^\d{4}-\d{2}-\d{2}$/

// Status, priority and due date filters and the search, kept in the URL
// (?status=open&priority=high&due=overdue&search=tampere), so a filtered page
// survives a reload and can be shared. Unknown filter values are ignored.
// A custom due date range is `due=range&due_from=2026-10-01&due_to=2026-10-31`.
export function useRequestFilters() {
  const route = useRoute()
  const router = useRouter()

  const filters = computed(() => ({
    status: route.query.status in statusLabels ? route.query.status : '',
    priority: route.query.priority in priorityLabels ? route.query.priority : '',
    search: typeof route.query.search === 'string' ? route.query.search.trim() : '',
    due: route.query.due in duePeriodLabels ? route.query.due : '',
    due_from: route.query.due === 'range' && DATE.test(route.query.due_from) ? route.query.due_from : '',
    due_to: route.query.due === 'range' && DATE.test(route.query.due_to) ? route.query.due_to : '',
  }))

  const hasFilters = computed(() => Object.values(filters.value).some(Boolean))

  // "/requests?status=open" for the API, leaving empty filters out. "range"
  // only tells the page to show the date fields; the API gets the dates.
  const apiPath = computed(() => {
    const params = new URLSearchParams(
      Object.entries(filters.value).filter(([name, filterValue]) => filterValue && !(name === 'due' && filterValue === 'range')),
    ).toString()

    return params ? `/requests?${params}` : '/requests'
  })

  function setFilter(name, value) {
    const query = { ...filters.value, [name]: value.trim() }

    // The dates belong to the custom range only.
    if (query.due !== 'range') {
      query.due_from = ''
      query.due_to = ''
    }

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
