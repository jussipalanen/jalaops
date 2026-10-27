import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { user } from '@/auth'
import App from '../App.vue'

const demoAdmin = { name: 'Demo Ylläpitäjä', email: 'demo.yllapitaja@example.com', role: 'admin', role_label: 'Ylläpitäjä' }

async function mountApp({ loggedIn = true } = {}) {
  user.value = loggedIn ? { ...demoAdmin } : null
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ demo: false }) }))
  const page = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/requests', '/map', '/customers', '/invoices', '/tools', '/about', '/login'].map((path) => ({
      path,
      name: path === '/login' ? 'login' : undefined,
      meta: path === '/login' ? { bare: true } : {},
      component: page,
    })),
  })
  await router.push('/')
  const wrapper = mount(App, { global: { plugins: [router] }, attachTo: document.body })
  await flushPromises()
  return { wrapper, router }
}

describe('App navigation', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
    user.value = null
  })

  it('shows only the logo, the theme and the footer before logging in', async () => {
    const { wrapper } = await mountApp({ loggedIn: false })

    expect(wrapper.find('nav[aria-label="Päävalikko"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label="Valikko"]').exists()).toBe(false)
    expect(wrapper.find('button[aria-label^="Käyttäjä"]').exists()).toBe(false)
    expect(wrapper.find('a[href="/privacy"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('leaves out the header and footer on the login page', async () => {
    const { wrapper, router } = await mountApp({ loggedIn: false })
    await router.push('/login')
    await flushPromises()

    expect(wrapper.find('header').exists()).toBe(false)
    expect(wrapper.find('footer').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows the user, their role and logging out in the user menu', async () => {
    const { wrapper, router } = await mountApp()
    const button = wrapper.find('button[aria-label="Käyttäjä: Demo Ylläpitäjä"]')

    expect(button.text()).toBe('DY')
    await button.trigger('click')
    const menu = wrapper.find('.user-menu')
    expect(menu.isVisible()).toBe(true)
    expect(menu.text()).toContain('demo.yllapitaja@example.com')
    expect(menu.text()).toContain('Ylläpitäjä')

    fetch.mockResolvedValue({ ok: true, status: 204, json: () => Promise.resolve(null) })
    await menu.find('button').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/logout', expect.objectContaining({ method: 'POST' }))
    expect(user.value).toBeNull()
    expect(router.currentRoute.value.path).toBe('/login')
    wrapper.unmount()
  })

  it('links to every page, including Työkalut', async () => {
    const { wrapper } = await mountApp()

    const desktop = wrapper.findAll('nav[aria-label="Päävalikko"]')[0]
    expect(desktop.findAll('a').map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Etusivu', '/'],
      ['Pyynnöt', '/requests'],
      ['Kartta', '/map'],
      ['Asiakkaat', '/customers'],
      ['Laskut', '/invoices'],
      ['Työkalut', '/tools'],
    ])
    wrapper.unmount()
  })

  it('hides the Mobiilisovellukset link while the page is switched off', async () => {
    const { wrapper } = await mountApp()

    expect(wrapper.find('a[href="/mobile-apps"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens the phone and tablet menu, and closes it on navigation and on Escape', async () => {
    const { wrapper, router } = await mountApp()
    const button = wrapper.find('button[aria-label="Valikko"]')
    const menu = wrapper.find('#mobile-nav')

    expect(button.attributes('aria-controls')).toBe('mobile-nav')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(menu.isVisible()).toBe(false)

    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(menu.isVisible()).toBe(true)

    await router.push('/tools')
    await flushPromises()
    expect(menu.isVisible()).toBe(false)

    // Tapping the link of the page that's already open closes it too.
    await button.trigger('click')
    await menu.find('a[href="/tools"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/tools')
    expect(menu.isVisible()).toBe(false)

    // Escape closes the menu and returns focus to its button.
    await button.trigger('click')
    menu.find('a').element.focus()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()
    expect(menu.isVisible()).toBe(false)
    expect(document.activeElement).toBe(button.element)

    wrapper.unmount()
  })

  it('closes the menu when keyboard focus leaves it', async () => {
    const { wrapper } = await mountApp()
    const button = wrapper.find('button[aria-label="Valikko"]')
    await button.trigger('click')

    const lastLink = wrapper.findAll('#mobile-nav a').at(-1)
    lastLink.element.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: document.body }))
    await flushPromises()

    expect(wrapper.find('#mobile-nav').isVisible()).toBe(false)
    wrapper.unmount()
  })
})
