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
      { path: '/requests/:id/edit', component: { template: '<div />' } },
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

    expect(rows[0].find('a').attributes('href')).toBe('/requests/1/edit')
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

    await wrapper.find('.filters .btn').trigger('click')
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
})
