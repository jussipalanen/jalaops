// Small wrapper around fetch for calling the Laravel API.

// Thrown for failed requests. For validation failures (422), `errors` holds
// the field errors from Laravel, e.g. { title: ['Kenttä otsikko on pakollinen.'] }.
export class ApiError extends Error {
  constructor(status, data = {}) {
    super(`API request failed with status ${status}`)
    this.status = status
    this.errors = data.errors ?? {}
    // The whole response body, e.g. the import report.
    this.data = data
  }
}

// Called when the API answers 401 (not logged in, or the session expired);
// set by auth.js, which opens the login page.
let unauthorizedHandler = () => {}

export function onUnauthorized(handler) {
  unauthorizedHandler = handler
}

// Laravel's CSRF token from its XSRF-TOKEN cookie, sent back in a header with
// every request that changes something.
function xsrfToken() {
  const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='))
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null
}

async function apiRequest(method, path, body, retried = false) {
  const headers = { Accept: 'application/json' }
  const options = { method, headers }
  const token = xsrfToken()

  if (token && method !== 'GET') {
    headers['X-XSRF-TOKEN'] = token
  }

  if (body instanceof FormData) {
    // The browser sets the multipart Content-Type with its boundary.
    options.body = body
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }

  const response = await fetch(`/api${path}`, options)

  // 419: the CSRF token is missing or old (e.g. after a server restart). Any
  // answer from the API brings a new one, so fetch one and try once more.
  if (response.status === 419 && !retried) {
    await fetch('/api/login', { headers: { Accept: 'application/json' } })
    return apiRequest(method, path, body, true)
  }

  if (!response.ok) {
    const data = await response.json().catch(() => ({}))

    if (response.status === 401) {
      unauthorizedHandler()
    }

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

// Any other method, e.g. PATCH.
export function apiRequestMethod(method, path, body) {
  return apiRequest(method, path, body)
}

export function apiDelete(path) {
  return apiRequest('DELETE', path)
}

// Uploads a file (multipart form data), e.g. an import file.
export function apiUpload(path, formData) {
  return apiRequest('POST', path, formData)
}
