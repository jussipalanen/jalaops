// Small wrapper around fetch for calling the Laravel API.
export async function apiGet(path) {
  const response = await fetch(`/api${path}`, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`API request failed with status ${response.status}`)
  }

  return response.json()
}
