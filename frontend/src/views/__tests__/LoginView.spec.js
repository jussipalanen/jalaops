import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { user } from '@/auth'
import LoginView from '../LoginView.vue'

const route = { query: {} }
const router = { replace: vi.fn() }

vi.mock('vue-router', async (importOriginal) => ({ ...(await importOriginal()), useRoute: () => route, useRouter: () => router }))

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const demoUser = { id: 1, name: 'Demokäyttäjä', email: 'demo.kayttaja@example.com', role: 'user', role_label: 'Käyttäjä', demo: true }

async function mountLogin(options = { demo_login: false }, ...answers) {
  const fetch = vi.fn().mockResolvedValueOnce(jsonResponse(options))
  answers.forEach((answer) => fetch.mockResolvedValueOnce(answer))
  vi.stubGlobal('fetch', fetch)

  const wrapper = mount(LoginView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  return { wrapper, fetch }
}

describe('LoginView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    router.replace.mockClear()
    route.query = {}
    user.value = null
  })

  it('logs in with an email and password and goes to the home page', async () => {
    const { wrapper, fetch } = await mountLogin(undefined, jsonResponse({ data: demoUser }))

    await wrapper.find('#email').setValue('teknikko@example.com')
    await wrapper.find('#password').setValue('salasana')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/login', expect.objectContaining({ method: 'POST' }))
    expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ email: 'teknikko@example.com', password: 'salasana' })
    expect(user.value).toEqual(demoUser)
    expect(router.replace).toHaveBeenCalledWith('/')
  })

  it('returns to the page that was asked for, but only within the app', async () => {
    route.query = { redirect: '/requests/12' }
    const { wrapper } = await mountLogin(undefined, jsonResponse({ data: demoUser }))
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(router.replace).toHaveBeenCalledWith('/requests/12')

    route.query = { redirect: '//evil.example/' }
    const other = await mountLogin(undefined, jsonResponse({ data: demoUser }))
    await other.wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(router.replace).toHaveBeenLastCalledWith('/')
  })

  it('shows the errors from the API', async () => {
    const { wrapper } = await mountLogin(
      undefined,
      jsonResponse({ errors: { email: ['Sähköposti tai salasana on väärin.'] } }, 422),
      jsonResponse({ message: 'Too Many Attempts.' }, 429),
    )

    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('#email-error').text()).toBe('Sähköposti tai salasana on väärin.')
    expect(wrapper.find('#email').attributes('aria-invalid')).toBe('true')
    expect(router.replace).not.toHaveBeenCalled()

    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toContain('Liian monta kirjautumisyritystä')
  })

  it('links to the public About and Privacy pages', async () => {
    const { wrapper } = await mountLogin()
    expect(wrapper.findAllComponents(RouterLinkStub).map((link) => link.props('to'))).toEqual(['/about', '/privacy'])
  })

  it('shows the demo buttons only when they are on', async () => {
    const { wrapper } = await mountLogin({ demo_login: false })
    expect(wrapper.text()).not.toContain('Kokeile demoa')

    const demo = await mountLogin({ demo_login: true })
    const buttons = demo.wrapper.findAll('button[type="button"]').map((button) => button.text())
    expect(buttons).toEqual(['Kokeile käyttäjänä', 'Kokeile ylläpitäjänä'])
  })

  it('signs in as the demo admin with one tap', async () => {
    const admin = { ...demoUser, name: 'Demoylläpitäjä', role: 'admin', role_label: 'Ylläpitäjä' }
    const { wrapper, fetch } = await mountLogin({ demo_login: true }, jsonResponse({ data: admin }))

    await wrapper.findAll('button[type="button"]')[1].trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/login/demo', expect.objectContaining({ method: 'POST' }))
    expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ role: 'admin' })
    expect(user.value.role).toBe('admin')
    expect(router.replace).toHaveBeenCalledWith('/')
  })

  it('sends the CSRF token from the cookie', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D123'
    const { wrapper, fetch } = await mountLogin(undefined, jsonResponse({ data: demoUser }))

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(fetch.mock.calls[1][1].headers['X-XSRF-TOKEN']).toBe('abc=123')
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
  })
})
