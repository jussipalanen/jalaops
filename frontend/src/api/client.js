// Small wrapper around fetch for calling the Laravel API.

// Thrown for failed requests. For validation failures (422), `errors` holds
// the field errors from Laravel, e.g. { title: ['Kenttä otsikko on pakollinen.'] }.
export class ApiError extends Error {
  constructor(status, data = {}) {
    super(`API request failed with status ${status}`)
    this.status = status
    this.errors = data.errors ?? {}
  }
}

async function apiRequest(method, path, body) {
  const headers = { Accept: 'application/json' }
  const options = { method, headers }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }

  const response = await fetch(`/api${path}`, options)

  if (!response.ok) {
    const data = await response.json().catch(() => ({}))
    throw new ApiError(response.status, data)
  }

  // 204 No Content has no body to parse.
  return response.status === 204 ? null : response.json()
}

export function apiGet(path) {
  return apiRequest('GET', path)
}

export function apiPost(path, body) {
  return apiRequest('POST', path, body)
}

export function apiPut(path, body) {
  return apiRequest('PUT', path, body)
}

export function apiDelete(path) {
  return apiRequest('DELETE', path)
}
