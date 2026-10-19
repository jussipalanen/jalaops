import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
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

    const wrapper = mount(HomeView, { global: { stubs: { RouterLink: RouterLinkStub } } })
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/health', expect.any(Object))
    expect(wrapper.text()).toContain('API-yhteys toimii.')
  })

  it('shows an error when the API cannot be reached', async () => {
    mockFetch({ ok: false, status: 500 })

    const wrapper = mount(HomeView, { global: { stubs: { RouterLink: RouterLinkStub } } })
    await flushPromises()

    expect(wrapper.text()).toContain('API-yhteys ei toimi.')
  })
})

describe('HomeView module widgets', () => {
  const hooks = [
    { id: 'dashboard.top', label: 'Etusivun yläosa' },
    { id: 'dashboard.after_counts', label: 'Tilanteen jälkeen' },
    { id: 'dashboard.after_overview', label: 'Tilannekatsauksen jälkeen' },
    { id: 'dashboard.bottom', label: 'Etusivun alaosa' },
  ]
  const news = {
    id: 'news.latest',
    module: 'news',
    module_name: 'Uutiset',
    routes: [{ path: '/news', params: {} }, { path: '/news/:news', params: {} }],
    title: 'Uusimmat uutiset',
    type: 'feed',
    hook: 'dashboard.after_counts',
    items: [
      {
        title: 'Syyshuollot alkavat',
        text: 'Suodattimet tarkistetaan.',
        date: '2026-10-19T12:00:00Z',
        image: 'data:image/png;base64,iVBORw0KGgo=',
        tags: ['huolto'],
        params: { news: '3' },
      },
      { title: 'Ilman kuvaa', text: null, date: null, image: null, tags: [], params: { news: '2' } },
    ],
    error: null,
  }

  // Each API path gets its own answer; the widgets come from /api/dashboard/widgets.
  function mountHome(widgets = [news]) {
    vi.stubGlobal(
      'fetch',
      vi.fn((url) =>
        Promise.resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve(url === '/api/dashboard/widgets' ? { hooks, data: widgets } : { status: 'ok' }),
        }),
      ),
    )

    return mount(HomeView, { global: { stubs: { RouterLink: RouterLinkStub, DashboardCounts: true, AiStatusOverview: true } } })
  }

  afterEach(() => {
    vi.unstubAllGlobals()
    localStorage.clear()
  })

  it("shows a widget in its module's hook, with links to the module's page", async () => {
    const wrapper = mountHome()
    await flushPromises()

    const hook = wrapper.find('[data-hook="dashboard.after_counts"]')
    expect(hook.text()).toContain('Uusimmat uutiset')
    expect(hook.text()).toContain('Syyshuollot alkavat')
    expect(hook.text()).toContain('#huolto')
    expect(hook.find('img').attributes('src')).toBe('data:image/png;base64,iVBORw0KGgo=')

    const links = hook.findAllComponents(RouterLinkStub).map((link) => link.props('to'))
    expect(links).toEqual([
      { path: '/news', query: {} },
      { path: '/news/3', query: {} },
      { path: '/news/2', query: {} },
    ])
    expect(wrapper.find('[data-hook="dashboard.top"]').exists()).toBe(false)
  })

  it('lets the user move and hide a widget in this browser, and go back to the default', async () => {
    const wrapper = mountHome()
    await flushPromises()

    await wrapper.findAll('button').find((button) => button.text() === 'Muokkaa etusivua').trigger('click')
    const select = wrapper.find('#place-news\\.latest')
    expect(select.element.value).toBe('dashboard.after_counts')
    expect(select.text()).toContain('Tilanteen jälkeen (oletus)')

    await select.setValue('dashboard.bottom')
    expect(wrapper.find('[data-hook="dashboard.bottom"]').text()).toContain('Uusimmat uutiset')
    expect(wrapper.find('[data-hook="dashboard.after_counts"]').exists()).toBe(false)
    expect(JSON.parse(localStorage.getItem('dashboardWidgets'))).toEqual({ 'news.latest': 'dashboard.bottom' })

    await select.setValue('hidden')
    expect(wrapper.find('[data-hook]').exists()).toBe(false)

    await wrapper.findAll('button').find((button) => button.text() === 'Palauta oletukset').trigger('click')
    expect(wrapper.find('[data-hook="dashboard.after_counts"]').exists()).toBe(true)
    expect(JSON.parse(localStorage.getItem('dashboardWidgets'))).toEqual({})
  })

  it("keeps the user's layout, but not a place that no longer exists", async () => {
    localStorage.setItem('dashboardWidgets', JSON.stringify({ 'news.latest': 'dashboard.top', 'other.widget': 'hidden' }))
    let wrapper = mountHome()
    await flushPromises()
    expect(wrapper.find('[data-hook="dashboard.top"]').text()).toContain('Uusimmat uutiset')

    localStorage.setItem('dashboardWidgets', JSON.stringify({ 'news.latest': 'sidebar' }))
    wrapper = mountHome()
    await flushPromises()
    expect(wrapper.find('[data-hook="dashboard.after_counts"]').exists()).toBe(true)
  })

  it("shows a widget's error and has no editor without widgets", async () => {
    let wrapper = mountHome([{ ...news, items: [], error: 'Sisällön lataaminen epäonnistui.' }])
    await flushPromises()
    expect(wrapper.find('[role="alert"]').text()).toBe('Sisällön lataaminen epäonnistui.')

    wrapper = mountHome([])
    await flushPromises()
    expect(wrapper.text()).not.toContain('Muokkaa etusivua')
  })
})
