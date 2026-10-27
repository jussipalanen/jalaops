import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { apiGet } from '@/api/client'
import { demoLogin, logout, user } from '@/auth'
import router from '@/router'

function stubFetch(status, body) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: status < 400, status, json: () => Promise.resolve(body) }))
}

async function logIn() {
  stubFetch(200, { data: { name: 'Demokäyttäjä', role: 'user' } })
  await demoLogin('user')
  vi.unstubAllGlobals()
}

describe('router', () => {
  beforeEach(logIn)

  it('gives each page its own browser tab title', async () => {
    await router.push('/map')
    expect(document.title).toBe('Kartta – JalaOps')

    await router.push('/requests')
    expect(document.title).toBe('Pyynnöt – JalaOps')

    await router.push('/')
    expect(document.title).toBe('JalaOps – huoltopyyntöjen hallinta')
  })

  // VITE_MOBILE_APPS_PAGE is not set in the tests, so the page is switched off.
  it('opens the home page from the switched-off Mobiilisovellukset page', async () => {
    await router.push('/map')
    await router.push('/mobile-apps')
    expect(router.currentRoute.value.path).toBe('/')
  })

  describe('module routes', () => {
    afterEach(() => vi.unstubAllGlobals())

    const modules = [
      {
        id: 'notes',
        name: 'Muistiinpanot',
        enabled: true,
        routes: [
          { path: '/notes', params: {} },
          { path: '/notes/:note/edit', params: { view: 'edit' } },
          // The app's own address wins.
          { path: '/requests/:id', params: {} },
        ],
      },
      { id: 'off', name: 'Pois', enabled: false, routes: [{ path: '/off', params: {} }] },
    ]

    it("opens a module's own address after fetching the modules once", async () => {
      vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ data: modules }) }))

      await router.push('/notes/3/edit?tag=a')
      const route = router.currentRoute.value
      expect(fetch).toHaveBeenCalledWith('/api/modules', expect.any(Object))
      expect(route.meta).toMatchObject({ module: 'notes', moduleParams: { view: 'edit' }, title: 'Muistiinpanot' })
      expect(route.params).toEqual({ note: '3' })
      expect(document.title).toBe('Muistiinpanot – JalaOps')

      await router.push('/requests/12')
      expect(router.currentRoute.value.name).toBe('request-details')

      // A module that's off has no addresses, and unknown ones show a page of their own.
      await router.push('/off')
      expect(router.currentRoute.value.name).toBe('not-found')
      expect(document.title).toBe('Sivua ei löytynyt – JalaOps')
      expect(fetch).toHaveBeenCalledTimes(1)
    })
  })

  describe('login', () => {
    afterEach(() => vi.unstubAllGlobals())

    async function logOut() {
      stubFetch(204, null)
      await logout()
    }

    it('opens the login page without a login, and returns to the page asked for', async () => {
      await logOut()

      await router.push('/requests/12?tab=items')
      expect(router.currentRoute.value.name).toBe('login')
      expect(router.currentRoute.value.query.redirect).toBe('/requests/12?tab=items')
      expect(document.title).toBe('Kirjaudu – JalaOps')

      await router.push('/')
      expect(router.currentRoute.value.fullPath).toBe('/login')
    })

    it('keeps the About and Privacy pages public', async () => {
      await logOut()

      await router.push('/about')
      expect(router.currentRoute.value.name).toBe('about')
      await router.push('/privacy')
      expect(router.currentRoute.value.name).toBe('privacy')
    })

    it('opens the settings only for admins', async () => {
      await router.push('/settings')
      expect(router.currentRoute.value.name).toBe('home')

      stubFetch(200, { data: { name: 'Demoylläpitäjä', role: 'admin' } })
      await demoLogin('admin')
      await router.push('/settings')
      expect(router.currentRoute.value.name).toBe('settings')
      expect(document.title).toBe('Asetukset – JalaOps')
    })

    it('sends someone already logged in from the login page to the app', async () => {
      await router.push('/login')
      expect(router.currentRoute.value.path).toBe('/')
    })

    it('opens the login page when the session expires', async () => {
      await router.push('/customers')
      expect(user.value).not.toBeNull()

      // Any API call that answers 401 ends the login.
      stubFetch(401, { message: 'Kirjaudu ensin sisään.' })
      await expect(apiGet('/customers')).rejects.toThrow()
      await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('login'))

      expect(user.value).toBeNull()
      expect(router.currentRoute.value.query.redirect).toBe('/customers')
    })
  })
})
