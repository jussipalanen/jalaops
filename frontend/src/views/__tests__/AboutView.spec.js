import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AboutView from '../AboutView.vue'
import { version } from '../../../package.json'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const system = {
  app: {
    name: 'JalaOps',
    api_version: '0.19.0',
    environment: 'production',
    demo: true,
    timezone: 'UTC',
    locale: 'fi',
    server_time: '2026-10-12T14:38:18+00:00',
  },
  health: { database: { status: 'ok', response_ms: 0.5 }, cache: { status: 'ok' } },
  details: null,
}

describe('AboutView', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('shows the versions, environment and health', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(system)))

    const wrapper = mount(AboutView)
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/system', expect.objectContaining({ method: 'GET' }))
    expect(wrapper.text()).toContain(`Frontendin versio${version}`)
    expect(wrapper.text()).toContain('API:n versio0.19.0')
    expect(wrapper.text()).toContain('YmpäristöTuotanto')
    expect(wrapper.text()).toContain('Käytössä: tiedot palautuvat käynnistyksessä')
    expect(wrapper.findAll('li .badge').map((badge) => badge.text())).toEqual(['Toimii', 'Toimii · 0.5 ms', 'Toimii'])
    // Versions are hidden on this server.
    expect(wrapper.text()).toContain('Tarkemmat versiotiedot on piilotettu')
    expect(wrapper.text()).not.toContain('PHP')
  })

  it('shows the server versions when the backend allows it', async () => {
    const details = { php: '8.3.35', laravel: '13.35.0', database: { driver: 'mariadb', version: '11.8.3' }, cache_driver: 'database', queue_driver: 'sync' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ ...system, details })))

    const wrapper = mount(AboutView)
    await flushPromises()

    expect(wrapper.text()).toContain('PHP8.3.35')
    expect(wrapper.text()).toContain('Laravel13.35.0')
    expect(wrapper.text()).toContain('Tietokantamariadb 11.8.3')
  })

  it('tells when the API does not answer', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    const wrapper = mount(AboutView)
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('API ei vastaa')
    expect(wrapper.findAll('li .badge').map((badge) => badge.text())).toEqual(['Ei vastaa', 'Ei vastaa', 'Ei vastaa'])
  })
})
