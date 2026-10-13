import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import App from '../App.vue'

async function mountApp() {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve({ demo: false }) }))
  const page = { template: '<div />' }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: ['/', '/requests', '/map', '/customers', '/tools', '/about'].map((path) => ({ path, component: page })),
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
  })

  it('links to every page, including Työkalut', async () => {
    const { wrapper } = await mountApp()

    const desktop = wrapper.findAll('nav[aria-label="Päävalikko"]')[0]
    expect(desktop.findAll('a').map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Etusivu', '/'],
      ['Pyynnöt', '/requests'],
      ['Kartta', '/map'],
      ['Asiakkaat', '/customers'],
      ['Työkalut', '/tools'],
    ])
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
