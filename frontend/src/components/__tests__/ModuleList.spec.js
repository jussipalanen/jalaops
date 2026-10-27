import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { user } from '@/auth'
import { syncModuleRoutes } from '@/router/moduleRoutes'
import ModuleList from '../ModuleList.vue'

const router = {}

vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal()), useRouter: () => router }))
vi.mock('@/router/moduleRoutes', () => ({ syncModuleRoutes: vi.fn() }))

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const modules = [
  { id: 'hello', name: 'Hei maailma', description: 'Tervehtii ja laskee.', version: '1.0.0', enabled: true, routes: [] },
  {
    id: 'route',
    name: 'Päivän reitti',
    description: 'Järjestää käynnit.',
    version: '0.1.0',
    enabled: false,
    routes: [{ path: '/route', params: {} }],
  },
]

function mountList(props = {}) {
  return mount(ModuleList, { props, global: { stubs: { RouterLink: RouterLinkStub } } })
}

describe('ModuleList', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    user.value = null
  })

  it('lists the modules and whether they are on', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: modules })))

    const wrapper = mountList()
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/modules', expect.any(Object))
    const rows = wrapper.findAll('.module-row')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('Hei maailma')
    expect(rows[0].text()).toContain('1.0.0')
    expect(rows[0].text()).toContain('Käytössä')
    expect(rows[1].text()).toContain('Pois käytöstä')
  })

  it('links only the modules that are on to their page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: modules })))

    const wrapper = mountList()
    await flushPromises()

    const links = wrapper.findAllComponents(RouterLinkStub)
    expect(links).toHaveLength(1)
    expect(links[0].props('to')).toEqual({ path: '/tools/modules/hello', query: {} })
  })

  it('says when there are no modules', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.text()).toContain('Moduuleja ei ole.')
  })

  it('shows an error when the modules cannot be fetched', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 500)))

    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('Moduulien haku epäonnistui.')
  })

  it('shows users whether each module is on, without the switches', async () => {
    user.value = { name: 'Demokäyttäjä', role: 'user' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: modules })))

    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('[role="switch"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Vain ylläpitäjä voi ottaa moduuleja käyttöön')
    expect(wrapper.findAll('.module-row .badge').map((badge) => badge.text())).toEqual(['Käytössä', 'Pois käytöstä'])

    // Not even on the settings page, which the router keeps from them anyway.
    const settings = mountList({ editable: true })
    await flushPromises()
    expect(settings.find('[role="switch"]').exists()).toBe(false)
  })

  it('links admins from Työkalut to the settings, where the switches are', async () => {
    user.value = { name: 'Demoylläpitäjä', role: 'admin' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: modules })))

    const wrapper = mountList()
    await flushPromises()

    expect(wrapper.find('[role="switch"]').exists()).toBe(false)
    expect(wrapper.findAllComponents(RouterLinkStub)[0].props('to')).toBe('/settings')
  })

  it('lets admins turn a module on and off for everyone in the settings', async () => {
    user.value = { name: 'Demoylläpitäjä', role: 'admin' }
    const fetch = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: modules.map((module) => ({ ...module })) }))
      .mockResolvedValueOnce(jsonResponse({ data: { ...modules[1], enabled: true } }))
    vi.stubGlobal('fetch', fetch)

    const wrapper = mountList({ editable: true })
    await flushPromises()

    const toggle = wrapper.findAll('[role="switch"]')[1]
    expect(toggle.attributes('aria-checked')).toBe('false')
    await toggle.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/modules/route', expect.objectContaining({ method: 'PATCH' }))
    expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ enabled: true })
    expect(toggle.attributes('aria-checked')).toBe('true')
    // The module's own address opens it, and works now that it's on.
    expect(wrapper.findAllComponents(RouterLinkStub)[1].props('to')).toEqual({ path: '/route', query: {} })
    expect(syncModuleRoutes).toHaveBeenCalledWith(router, [{ ...modules[1], enabled: true }])
  })

  it('says when switching a module fails', async () => {
    user.value = { name: 'Demoylläpitäjä', role: 'admin' }
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(jsonResponse({ data: modules.map((module) => ({ ...module })) })).mockResolvedValueOnce(jsonResponse({}, 500)),
    )

    const wrapper = mountList({ editable: true })
    await flushPromises()
    await wrapper.findAll('[role="switch"]')[0].trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('Moduulin Hei maailma poistaminen käytöstä epäonnistui.')
    expect(wrapper.findAll('[role="switch"]')[0].attributes('aria-checked')).toBe('true')
  })
})
