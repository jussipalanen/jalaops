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

    expect(fetch).toHaveBeenCalledWith('/api/requests?per_page=10', expect.objectContaining({ method: 'GET' }))

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
      // The list reloads after deleting.
      .mockResolvedValueOnce(jsonResponse({ data: [requests[1]] }))
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(true)

    const { wrapper } = await mountView()

    await wrapper.findAll('tbody tr')[0].find('button.btn-danger').trigger('click')
    await flushPromises()

    expect(window.confirm).toHaveBeenCalledWith('Poistetaanko pyyntö "Vaihda ilmansuodatin"?')
    expect(fetchMock).toHaveBeenNthCalledWith(2, '/api/requests/1', expect.objectContaining({ method: 'DELETE' }))
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?per_page=10', expect.anything())
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
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?per_page=10', expect.anything())

    await wrapper.find('#filter-status').setValue('open')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ status: 'open' })
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?status=open&per_page=10', expect.anything())

    await wrapper.find('#filter-priority').setValue('high')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ status: 'open', priority: 'high' })
    expect(fetchMock).toHaveBeenLastCalledWith(
      '/api/requests?status=open&priority=high&per_page=10',
      expect.anything(),
    )

    await wrapper.find('[role="search"] .btn').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({})
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?per_page=10', expect.anything())
  })

  it('applies filters from the URL when opened', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper } = await mountView('/requests?priority=high&status=bogus')

    expect(fetchMock).toHaveBeenCalledWith('/api/requests?priority=high&per_page=10', expect.anything())
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
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?search=Vantaa&per_page=10', expect.anything())
    expect(fetchMock.mock.calls.filter(([url]) => url.includes('search'))).toHaveLength(1)

    await wrapper.find('[role="search"] .btn').trigger('click')
    await flushPromises()
    expect(wrapper.find('#filter-search').element.value).toBe('')

    vi.useRealTimers()
  })

  it('applies the search from the URL when opened', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper } = await mountView('/requests?search=33100&status=open')

    expect(fetch).toHaveBeenCalledWith('/api/requests?status=open&search=33100&per_page=10', expect.anything())
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
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?due=overdue&per_page=10', expect.anything())
    expect(wrapper.find('#filter-due-from').exists()).toBe(false)
  })

  it('filters by a custom due date range', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper, router } = await mountView()

    await wrapper.find('#filter-due').setValue('range')
    await flushPromises()
    // Choosing the range only shows the date fields; nothing is filtered yet.
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?per_page=10', expect.anything())

    await wrapper.find('#filter-due-from').setValue('2026-10-01')
    await flushPromises()
    await wrapper.find('#filter-due-to').setValue('2026-10-31')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ due: 'range', due_from: '2026-10-01', due_to: '2026-10-31' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?due_from=2026-10-01&due_to=2026-10-31&per_page=10', expect.anything())
    expect(wrapper.find('#filter-due-to').attributes('min')).toBe('2026-10-01')

    // Switching to a period drops the dates.
    await wrapper.find('#filter-due').setValue('week')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ due: 'week' })
  })

  it('ignores invalid due date values in the URL', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    await mountView('/requests?due=yesterday&due_from=2026-10-01')

    expect(fetch).toHaveBeenCalledWith('/api/requests?per_page=10', expect.anything())
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

  it('shows a badge on new requests', async () => {
    const created_at = new Date().toISOString()
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ data: [{ ...requests[0], created_at }, { ...requests[1], created_at: '2026-01-01T00:00:00Z' }] })),
    )

    const { wrapper } = await mountView()

    const rows = wrapper.findAll('tbody tr')
    expect(rows[0].find('.badge-new').text()).toBe('Uusi')
    expect(rows[1].find('.badge-new').exists()).toBe(false)
  })

  it('sorts by a column header, reversing on a second click', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { wrapper, router } = await mountView('/requests?page=3')
    const header = (name) => wrapper.findAll('th').find((th) => th.text().startsWith(name))

    // Priority sorts high first on the first click, and the page resets.
    await header('Prioriteetti').find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: '-priority' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?sort=-priority&per_page=10', expect.anything())
    expect(header('Prioriteetti').attributes('aria-sort')).toBe('descending')

    await header('Prioriteetti').find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'priority' })
    expect(header('Prioriteetti').attributes('aria-sort')).toBe('ascending')

    await header('Otsikko').find('button').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'title' })
    expect(header('Prioriteetti').attributes('aria-sort')).toBeUndefined()

    // The phone select offers the same orders.
    await wrapper.find('#list-sort').setValue('-created_at')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: '-created_at' })
  })

  it('pages through the requests and changes the page size', async () => {
    const page = (current, from, to) =>
      jsonResponse({ data: requests, meta: { current_page: current, last_page: 2, per_page: 10, from, to, total: 12 } })
    const fetchMock = vi.fn().mockResolvedValueOnce(page(1, 1, 10)).mockResolvedValue(page(2, 11, 12))
    vi.stubGlobal('fetch', fetchMock)

    const { wrapper, router } = await mountView('/requests?sort=title')

    expect(wrapper.find('nav[aria-label="Sivutus"]').text()).toContain('Näytetään 1–10 / 12')
    expect(wrapper.text()).toContain('12 pyyntöä')
    expect(wrapper.find('button[aria-label="Edellinen sivu"]').attributes('disabled')).toBeDefined()

    await wrapper.find('button[aria-label="Seuraava sivu"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'title', page: '2' })
    expect(fetchMock).toHaveBeenLastCalledWith('/api/requests?sort=title&per_page=10&page=2', expect.anything())
    expect(wrapper.text()).toContain('Näytetään 11–12 / 12')

    await wrapper.find('#per-page').setValue('25')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'title', per_page: '25' })

    // Filters keep the sort and page size, and go back to the first page.
    await router.replace({ query: { sort: 'title', per_page: '25', page: '2' } })
    await wrapper.find('#filter-status').setValue('open')
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'title', per_page: '25', status: 'open' })
  })

  it('moves to the last page when the page is past the end', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { current_page: 5, last_page: 2, per_page: 10, from: null, to: null, total: 12 } }))
      .mockResolvedValue(jsonResponse({ data: requests, meta: { current_page: 2, last_page: 2, per_page: 10, from: 11, to: 12, total: 12 } }))
    vi.stubGlobal('fetch', fetchMock)

    const { router } = await mountView('/requests?page=5')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ page: '2' })
  })

  it('exports the selected requests, or else all matching the filters', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests, meta: { current_page: 1, last_page: 1, per_page: 10, from: 1, to: 2, total: 2 } })))

    const { wrapper, router } = await mountView('/requests?status=open&sort=title')
    const exportLinks = () => wrapper.findAll('a[href^="/api/requests/export"]').map((link) => link.attributes('href'))

    expect(wrapper.text()).toContain('Vie kaikki (2)')
    expect(exportLinks()).toEqual([
      '/api/requests/export?format=csv&status=open&sort=title',
      '/api/requests/export?format=json&status=open&sort=title',
    ])

    await wrapper.findAll('tbody input[type="checkbox"]')[1].setValue(true)
    expect(wrapper.text()).toContain('Vie valitut (1)')
    expect(exportLinks()[0]).toBe('/api/requests/export?format=csv&ids=2')

    // The header box selects the whole page.
    await wrapper.find('thead input[type="checkbox"]').setValue(true)
    expect(exportLinks()[0]).toBe('/api/requests/export?format=csv&ids=2,1')

    // A new filter starts a new selection.
    await router.replace({ query: { status: 'completed' } })
    await flushPromises()
    expect(wrapper.text()).toContain('Vie kaikki')
    expect(wrapper.find('a[href="/tools#tuo"]').text()).toBe('Tuo')
  })
})
