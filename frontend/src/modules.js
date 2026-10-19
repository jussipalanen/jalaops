import { ApiError, apiPost } from '@/api/client'

// Python modules on the backend (backend/modules).

// Runs a module with an input object and returns its output.
export async function runModule(id, input) {
  return (await apiPost(`/modules/${encodeURIComponent(id)}/run`, input)).output
}

// A Finnish message for a failed run: the API's message and the module's own reason.
export function moduleErrorMessage(exception) {
  if (!(exception instanceof ApiError)) {
    return 'Moduulin suoritus epäonnistui. Yritä hetken kuluttua uudelleen.'
  }

  if (exception.status === 429) {
    return 'Moduulia on käytetty liian usein. Yritä hetken kuluttua uudelleen.'
  }

  const { message, reason } = exception.data

  return [message, reason].filter(Boolean).join(' ') || 'Moduulin suoritus epäonnistui.'
}

// A module page's address parameters (?news=3) as a plain object of short
// texts with simple names, for the module's view and back from it.
export function moduleParams(query) {
  const params = {}

  if (!query || typeof query !== 'object') {
    return params
  }

  for (const [name, value] of Object.entries(query)) {
    if (/^[a-z][a-z0-9_]*$/.test(name) && (typeof value === 'string' || typeof value === 'number')) {
      params[name] = String(value).slice(0, 200)
    }
  }

  return params
}
