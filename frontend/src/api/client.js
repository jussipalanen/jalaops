// Small wrapper around fetch for calling the Laravel API.
async function apiRequest(method, path) {
  const response = await fetch(`/api${path}`, {
    method,
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`API request failed with status ${response.status}`)
  }

  // 204 No Content has no body to parse.
  return response.status === 204 ? null : response.json()
}

export function apiGet(path) {
  return apiRequest('GET', path)
}

export function apiDelete(path) {
  return apiRequest('DELETE', path)
}
