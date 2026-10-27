import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { user } from '@/auth'
import SettingsView from '../SettingsView.vue'

vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal()), useRouter: () => ({}) }))
vi.mock('@/router/moduleRoutes', () => ({ syncModuleRoutes: vi.fn() }))

describe('SettingsView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    user.value = null
  })

  it('has the switches that turn the modules on and off', async () => {
    user.value = { name: 'Demoylläpitäjä', role: 'admin' }
    const modules = [{ id: 'hello', name: 'Hei maailma', description: '', version: '1.0.0', enabled: true, routes: [] }]
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ data: modules }) }))

    const wrapper = mount(SettingsView, { global: { stubs: { RouterLink: RouterLinkStub } } })
    await flushPromises()

    expect(wrapper.find('h1').text()).toBe('Asetukset')
    expect(wrapper.find('[role="switch"]').attributes('aria-label')).toBe('Hei maailma: käytössä')
  })
})
