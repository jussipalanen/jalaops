import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import RequestListView from '../RequestListView.vue'

const requests = [
  {
    id: 1,
    title: 'Vaihda ilmansuodatin',
    priority: 'high',
    status: 'open',
    due_date: '2026-10-15',
    street_address: 'Tikkurilantie 44',
    postal_code: '01300',
    city: 'Vantaa',
    latitude: 60.2891,
    longitude: 25.0371,
  },
  {
    id: 2,
    title: 'Huolla trukki',
    priority: 'normal',
    status: 'in_progress',
    due_date: null,
  },
]

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

async function mountView(url = '/requests') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/requests', component: RequestListView },
      { path: '/requests/:id', component: { template: '<div />' } },
      { path: '/requests/:id/edit', component: { template: '<div />' } },
      { path: '/map', component: { template: '<div />' } },
    ],
  })
  await router.push(url)

  const wrapper = mount(RequestListView, { global: { plugins: [router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('RequestListView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('shows requests from the API with Finnish labels', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { wrapper } = await mountView()

    expect(fetch).toHaveBeenCalledWith('/api/requests', expect.objectContaining({ method: 'GET' }))

    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('Vaihda ilmansuodatin')
    expect(rows[0].text()).toContain('Korkea')
    expect(rows[0].text()).toContain('Avoin')
    expect(rows[0].text()).toContain('15.10.2026')
    expect(rows[1].text()).toContain('Normaali')
    expect(rows[1].text()).toContain('Työn alla')
    expect(rows[1].text()).toContain('–')

    expect(rows[0].find('a').attributes('href')).toBe('/requests/1')
    expect(rows[0].find('a[href="/requests/1/edit"]').text()).toBe('Muokkaa')
    // Only requests with coordinates link to the map.
    expect(rows[0].find('a[href="/map?request=1"]').exists()).toBe(true)
    expect(rows[0].text()).toContain('Tikkurilantie 44, 01300 Vantaa')
    expect(rows[1].find('a[href^="/map"]').exists()).toBe(false)
  })

  it('shows a message when there are no requests', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Ei pyyntöjä.')
    expect(wrapper.find('table').exists()).toBe(false)
  })

  it('shows an error when loading fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 500)))

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Pyyntöjen lataaminen epäonnistui.')
  })

  it('deletes a request after confirmation', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: requests }))
      .mockResolvedValueOnce({ ok: true, status: 204 })
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(true)

    const { wrapper } = await mountView()

    await wrapper.findAll('tbody tr')[0].find('button.btn-danger').trigger('click')
    await flushPromises()

    expect(window.confirm).toHaveBeenCalledWith('Poistetaanko pyyntö "Vaihda ilmansuodatin"?')
    expect(fetchMock).toHaveBeenLastCalledWith(
      '/api/requests/1',
      expect.objectContaining({ method: 'DELETE' }),
    )
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.text()).not.toContain('Vaihda ilmansuodatin')
  })

  it('keeps the request when deletion is cancelled', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: requests }))
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(false)

    const { wrapper } = await mountView()

    await wrapper.findAll('tbody tr')[0].find('button.btn-danger').trigger('click')
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })

  it('filters by status and priority and keeps the filters in the URL', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: requests }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper, router } = await mountView()
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests', expect.anything())

    await wrapper.find('#filter-status').setValue('open')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ status: 'open' })
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?status=open', expect.anything())

    await wrapper.find('#filter-priority').setValue('high')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ status: 'open', priority: 'high' })
    expect(fetchMock).toHaveBeenLastCalledWith(
      '/api/requests?status=open&priority=high',
      expect.anything(),
    )

    await wrapper.find('[role="search"] .btn').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({})
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests', expect.anything())
  })

  it('applies filters from the URL when opened', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountView('/requests?priority=high&status=bogus')

    expect(fetchMock).toHaveBeenCalledWith('/api/requests?priority=high', expect.anything())
    expect(wrapper.find('#filter-priority').element.value).toBe('high')
    expect(wrapper.find('#filter-status').element.value).toBe('')
    expect(wrapper.text()).toContain('Ei suodattimia vastaavia pyyntöjä.')
  })

  it('searches by address after a short pause and keeps the search in the URL', async () => {
    vi.useFakeTimers()
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [requests[0]] }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper, router } = await mountView()

    await wrapper.find('#filter-search').setValue('Vant')
    await wrapper.find('#filter-search').setValue('Vantaa ')
    expect(router.currentRoute.value.query).toEqual({})

    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    // Only the finished search is sent, trimmed.
    expect(router.currentRoute.value.query).toEqual({ search: 'Vantaa' })
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?search=Vantaa', expect.anything())
    expect(fetchMock.mock.calls.filter(([url]) => url.includes('search'))).toHaveLength(1)

    await wrapper.find('[role="search"] .btn').trigger('click')
    await flushPromises()
    expect(wrapper.find('#filter-search').element.value).toBe('')

    vi.useRealTimers()
  })

  it('applies the search from the URL when opened', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper } = await mountView('/requests?search=33100&status=open')

    expect(fetch).toHaveBeenCalledWith('/api/requests?status=open&search=33100', expect.anything())
    expect(wrapper.find('#filter-search').element.value).toBe('33100')
    expect(wrapper.text()).toContain('Ei suodattimia vastaavia pyyntöjä.')
  })

  it('filters by a due date period', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper, router } = await mountView()

    expect(wrapper.find('#filter-due').text()).toContain('Myöhässä')
    await wrapper.find('#filter-due').setValue('overdue')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ due: 'overdue' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?due=overdue', expect.anything())
    expect(wrapper.find('#filter-due-from').exists()).toBe(false)
  })

  it('filters by a custom due date range', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper, router } = await mountView()

    await wrapper.find('#filter-due').setValue('range')
    await flushPromises()
    // Choosing the range only shows the date fields; nothing is filtered yet.
    expect(fetch).toHaveBeenLastCalledWith('/api/requests', expect.anything())

    await wrapper.find('#filter-due-from').setValue('2026-10-01')
    await flushPromises()
    await wrapper.find('#filter-due-to').setValue('2026-10-31')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ due: 'range', due_from: '2026-10-01', due_to: '2026-10-31' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?due_from=2026-10-01&due_to=2026-10-31', expect.anything())
    expect(wrapper.find('#filter-due-to').attributes('min')).toBe('2026-10-01')

    // Switching to a period drops the dates.
    await wrapper.find('#filter-due').setValue('week')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ due: 'week' })
  })

  it('ignores invalid due date values in the URL', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    await mountView('/requests?due=yesterday&due_from=2026-10-01')

    expect(fetch).toHaveBeenCalledWith('/api/requests', expect.anything())
  })

  it("shows the API's message for an invalid range", async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ errors: { due_to: ['Kentän asti on oltava sama tai myöhempi päivä kuin alkaen.'] } }, 422),
      ),
    )

    const { wrapper } = await mountView('/requests?due=range&due_from=2026-10-10&due_to=2026-10-01')

    expect(wrapper.find('[role="alert"]').text()).toBe('Kentän asti on oltava sama tai myöhempi päivä kuin alkaen.')
  })
})
