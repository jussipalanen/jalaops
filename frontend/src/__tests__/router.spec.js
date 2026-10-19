import { afterEach, describe, expect, it, vi } from 'vitest'
import router from '@/router'

describe('router', () => {
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
})
