import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DemoBanner from '../DemoBanner.vue'

function mockHealth(body) {
  vi.stubGlobal(
    'fetch',
    vi.fn().mockResolvedValue({ ok: true, status: 200, json: () => Promise.resolve(body) }),
  )
}

describe('DemoBanner', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('shows a notice in demo mode', async () => {
    mockHealth({ status: 'ok', database: 'ok', demo: true })

    const wrapper = mount(DemoBanner)
    await flushPromises()

    expect(wrapper.text()).toContain('Demotila')
  })

  it('stays hidden when demo mode is off', async () => {
    mockHealth({ status: 'ok', database: 'ok', demo: false })

    const wrapper = mount(DemoBanner)
    await flushPromises()

    expect(wrapper.find('.demo-banner').exists()).toBe(false)
  })
})
