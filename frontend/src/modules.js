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
