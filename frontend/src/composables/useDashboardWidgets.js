import { computed, ref } from 'vue'
import { apiGet } from '@/api/client'

// The modules' widgets on the dashboard. The dashboard has places for them
// (hooks, such as "dashboard.after_counts"); each widget's module picks one,
// and the server can move or hide it for everyone (modules:place). On top of
// that, users move or hide widgets for themselves: that layout is kept in
// this browser's localStorage, as { widgetId: hook or 'hidden' }.

const STORAGE_KEY = 'dashboardWidgets'
export const HIDDEN = 'hidden'

function storedLayout() {
  try {
    const layout = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}')
    return layout && typeof layout === 'object' && !Array.isArray(layout) ? layout : {}
  } catch {
    // Storage can be unavailable or hold something else.
    return {}
  }
}

export function useDashboardWidgets() {
  const hooks = ref([])
  const widgets = ref([])
  const loaded = ref(false)
  const failed = ref(false)
  const layout = ref(storedLayout())

  async function load() {
    try {
      const response = await apiGet('/dashboard/widgets')
      hooks.value = response.hooks ?? []
      widgets.value = response.data ?? []
    } catch {
      failed.value = true
    } finally {
      loaded.value = true
    }
  }

  // Where each widget is for this user: their own choice if it's still a
  // place the dashboard has, otherwise the server's.
  const placed = computed(() =>
    widgets.value.map((widget) => {
      const own = layout.value[widget.id]
      const known = own === HIDDEN || hooks.value.some((hook) => hook.id === own)
      return { ...widget, place: known ? own : widget.hook }
    }),
  )

  function inHook(hook) {
    return placed.value.filter((widget) => widget.place === hook)
  }

  // Moves a widget for this user, or hides it ('hidden'). Back in the
  // server's place, the user's own choice is forgotten.
  function place(widget, hook) {
    const next = { ...layout.value }
    if (hook === widget.hook) delete next[widget.id]
    else next[widget.id] = hook
    save(next)
  }

  function reset() {
    save({})
  }

  function save(next) {
    layout.value = next
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
    } catch {
      // Without storage the layout lasts until the page is reloaded.
    }
  }

  const customized = computed(() => widgets.value.some((widget) => layout.value[widget.id] !== undefined))

  return { hooks, widgets: placed, loaded, failed, customized, load, inHook, place, reset }
}
