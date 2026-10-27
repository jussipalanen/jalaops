import { afterEach, describe, expect, it, vi } from 'vitest'
import { apiGet, apiPost, onUnauthorized } from '@/api/client'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

describe('API client', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    onUnauthorized(() => {})
  })

  it('fetches a new CSRF token and tries once more after a 419', async () => {
    const fetch = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ message: 'Istunto on vanhentunut.' }, 419))
      .mockResolvedValueOnce(jsonResponse({ demo_login: true }))
      .mockResolvedValueOnce(jsonResponse({ data: { id: 1 } }, 201))
    vi.stubGlobal('fetch', fetch)

    await expect(apiPost('/customers', { name: 'Asiakas' })).resolves.toEqual({ data: { id: 1 } })
    expect(fetch.mock.calls.map(([path, options]) => [path, options.method ?? 'GET'])).toEqual([
      ['/api/customers', 'POST'],
      ['/api/login', 'GET'],
      ['/api/customers', 'POST'],
    ])
  })

  it('tells auth.js about a 401', async () => {
    const handler = vi.fn()
    onUnauthorized(handler)
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'Kirjaudu ensin sisään.' }, 401)))

    await expect(apiGet('/requests')).rejects.toMatchObject({ status: 401 })
    expect(handler).toHaveBeenCalledOnce()
  })
})
