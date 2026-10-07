import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeView from '../HomeView.vue'

function mockFetch(response) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response))
}

describe('HomeView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('shows that the API connection works', async () => {
    mockFetch({ ok: true, json: () => Promise.resolve({ status: 'ok' }) })

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/health', expect.any(Object))
    expect(wrapper.text()).toContain('API-yhteys toimii.')
  })

  it('shows an error when the API cannot be reached', async () => {
    mockFetch({ ok: false, status: 500 })

    const wrapper = mount(HomeView)
    await flushPromises()

    expect(wrapper.text()).toContain('API-yhteys ei toimi.')
  })
})
