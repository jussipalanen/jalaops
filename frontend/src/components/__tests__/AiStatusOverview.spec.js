import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AiStatusOverview from '../AiStatusOverview.vue'

const overview = {
  enabled: true,
  summary: 'Avoimia pyyntöjä on 8, joista 3 on korkean prioriteetin.',
  actions: ['Hoida myöhässä oleva rasvanerotin ensin.', 'Sovi trukin huolto.'],
  risks: ['Kaksi pyyntöä erääntyy huomenna.'],
  generated_at: '2026-10-09T12:05:00+00:00',
  provider: 'Gemini',
  model: 'gemini-3.1-flash-lite',
  stale: false,
}

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

describe('AiStatusOverview', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('stays hidden when the feature is off', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ enabled: false })))

    const wrapper = mount(AiStatusOverview)
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/dashboard/ai-overview', expect.any(Object))
    expect(wrapper.find('.ai-overview').exists()).toBe(false)
  })

  it('shows the summary, actions and risks', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(overview)))

    const wrapper = mount(AiStatusOverview)
    await flushPromises()

    expect(wrapper.text()).toContain('AI-tilannekatsaus')
    expect(wrapper.text()).toContain(overview.summary)
    expect(wrapper.findAll('ol li > span:last-child').map((item) => item.text())).toEqual(
      overview.actions,
    )
    expect(wrapper.text()).toContain('Kaksi pyyntöä erääntyy huomenna.')
    expect(wrapper.text()).toContain('Gemini (gemini-3.1-flash-lite)')
    expect(wrapper.text()).not.toContain('tuntiraja')
  })

  it('shows AI text as plain text, not HTML', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ ...overview, summary: '<img src=x onerror=alert(1)>' })),
    )

    const wrapper = mount(AiStatusOverview)
    await flushPromises()

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>')
  })

  it('tells when an earlier overview is shown', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ ...overview, stale: true })))

    const wrapper = mount(AiStatusOverview)
    await flushPromises()

    expect(wrapper.text()).toContain('Näytetään aiempi katsaus')
  })

  it('refreshes the overview', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(overview))
        .mockResolvedValueOnce(jsonResponse({ ...overview, summary: 'Uusi katsaus.' })),
    )

    const wrapper = mount(AiStatusOverview)
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith(
      '/api/dashboard/ai-overview/refresh',
      expect.objectContaining({ method: 'POST' }),
    )
    expect(wrapper.text()).toContain('Uusi katsaus.')
  })

  it('shows an error when the overview fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'virhe' }, 503)))

    const wrapper = mount(AiStatusOverview)
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe(
      'AI-tilannekatsauksen luominen epäonnistui. Yritä hetken kuluttua uudelleen.',
    )
  })

  it('tells when refreshing too often', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(overview))
        .mockResolvedValueOnce(jsonResponse({ message: 'Too Many Attempts.' }, 429)),
    )

    const wrapper = mount(AiStatusOverview)
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('päivitetty liian usein')
  })
})
