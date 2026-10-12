import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { created } from '@/test/leaflet'
import RequestMapView from '../RequestMapView.vue'

const requests = [
  {
    id: 1,
    title: 'Vaihda ilmansuodatin',
    description: 'Vaihda varaston ilmanvaihtokoneen ilmansuodatin.',
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
    description: null,
    priority: 'normal',
    status: 'completed',
    due_date: null,
    street_address: 'Isokatu 32',
    postal_code: '90100',
    city: 'Oulu',
    latitude: 65.0103,
    longitude: 25.4701,
  },
  {
    id: 3,
    title: 'Säädä toimiston lämmitys',
    description: null,
    priority: 'low',
    status: 'open',
    due_date: null,
    street_address: null,
    postal_code: null,
    city: null,
    latitude: null,
    longitude: null,
  },
]

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

async function mountView(url = '/map') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/map', component: RequestMapView },
      { path: '/requests', component: { template: '<div />' } },
      { path: '/requests/:id', component: { template: '<div />' } },
    ],
  })
  await router.push(url)

  const wrapper = mount(RequestMapView, { global: { plugins: [router] }, attachTo: document.body })
  await flushPromises()

  return { wrapper, router, map: created.maps[0] }
}

describe('RequestMapView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('shows a pin for each request with a location and zooms to fit them', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { wrapper, map } = await mountView()

    const pins = created.markers
    expect(pins.map((pin) => pin.latlng)).toEqual([
      [60.2891, 25.0371],
      [65.0103, 25.4701],
    ])
    expect(pins[0].options.icon.className).toBe('map-pin map-pin-high')
    expect(pins[1].options.icon.className).toBe('map-pin map-pin-normal map-pin-completed')
    expect(map.bounds.points).toHaveLength(2)

    expect(wrapper.text()).toContain('2 pyyntöä')
    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.text()).toContain('Tikkurilantie 44, 01300 Vantaa')
    expect(wrapper.text()).toContain('1 pyyntö ilman sijaintia.')
  })

  it('marks new requests with a circle, a badge and a count', async () => {
    const created_at = new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString()
    const fresh = { ...requests[0], created_at }
    const old = { ...requests[1], status: 'open', created_at: '2026-01-01T00:00:00Z' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [fresh, old] })))

    const { wrapper } = await mountView()

    expect(created.markers[0].options.icon.className).toContain('map-pin-new')
    expect(created.markers[1].options.icon.className).not.toContain('map-pin-new')
    expect(wrapper.text()).toContain('2 pyyntöä, 1 uusi')
    expect(wrapper.findAll('li')[0].find('.badge-new').text()).toBe('Uusi')
    expect(wrapper.findAll('li')[1].find('.badge-new').exists()).toBe(false)
    expect(created.markers[0].openPopup().popupElement.querySelector('.badge-new').textContent).toBe('Uusi')
    expect(wrapper.find('[aria-label="Merkkien selitys"]').text()).toContain('Uusi (7 pv)')
  })

  it('opens a popup with the description and a link to the details', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { router } = await mountView()

    const popup = created.markers[0].openPopup().popupElement
    expect(popup.textContent).toContain('Vaihda ilmansuodatin')
    expect(popup.textContent).toContain('Korkea')
    expect(popup.textContent).toContain('Avoin')
    expect(popup.textContent).toContain('Vaihda varaston ilmanvaihtokoneen ilmansuodatin.')
    expect(popup.textContent).toContain('Määräpäivä 15.10.2026')

    const [link, streetView] = popup.querySelectorAll('a')
    expect(link.getAttribute('href')).toBe('/requests/1')
    expect(streetView.getAttribute('href')).toBe(
      'https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=60.2891,25.0371',
    )
    expect(streetView.getAttribute('target')).toBe('_blank')

    link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }))
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/requests/1')
  })

  it('flies to a request picked from the list', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { wrapper, map } = await mountView()

    await wrapper.findAll('li button')[1].trigger('click')
    map.fire('moveend')

    expect(map.view.center).toEqual({ lat: 65.0103, lng: 25.4701 })
    expect(created.markers[1].popupOpen).toBe(true)
  })

  it('focuses the request given in the address', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: requests })))

    const { map } = await mountView('/map?request=2')

    expect(map.view).toEqual({ center: { lat: 65.0103, lng: 25.4701 }, zoom: 15 })
    expect(created.markers[1].popupOpen).toBe(true)
  })

  it('filters the pins with the address bar filters', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [requests[0]] })))

    const { wrapper, router } = await mountView('/map?priority=high')

    expect(fetch).toHaveBeenCalledWith('/api/requests?priority=high', expect.anything())
    expect(wrapper.find('#map-filter-priority').element.value).toBe('high')

    await wrapper.find('#map-filter-status').setValue('open')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ status: 'open', priority: 'high' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?status=open&priority=high', expect.anything())
  })

  it('searches the pins by address', async () => {
    vi.useFakeTimers()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [requests[1]] })))

    const { wrapper, router } = await mountView()

    await wrapper.find('#map-filter-search').setValue('Oulu')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ search: 'Oulu' })
    expect(fetch).toHaveBeenLastCalledWith('/api/requests?search=Oulu', expect.anything())
    expect(created.markers.at(-1).latlng).toEqual([65.0103, 25.4701])

    vi.useRealTimers()
  })

  it('shows an error when loading fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 500)))

    const { wrapper } = await mountView()

    expect(wrapper.find('[role="alert"]').text()).toBe('Pyyntöjen lataaminen epäonnistui.')
    expect(created.markers).toHaveLength(0)
  })

  it('removes the map when leaving the page', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const { wrapper, map } = await mountView()
    wrapper.unmount()

    expect(map.removed).toBe(true)
  })
})
