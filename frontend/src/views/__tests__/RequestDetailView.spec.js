import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import { created } from '@/test/leaflet'
import RequestDetailView from '../RequestDetailView.vue'

const push = vi.fn()

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRouter: () => ({ push }),
  useRoute: () => ({ params: { id: '1' } }),
}))

const request = {
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
}

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

async function mountView(data) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data })))
  const wrapper = mount(RequestDetailView, { global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()
  return wrapper
}

describe('RequestDetailView', () => {
  beforeEach(() => push.mockClear())
  afterEach(() => vi.unstubAllGlobals())

  it('shows the request with its address on a map', async () => {
    const wrapper = await mountView(request)

    expect(wrapper.find('h1').text()).toBe('Vaihda ilmansuodatin')
    expect(wrapper.text()).toContain('Korkea')
    expect(wrapper.text()).toContain('Avoin')
    expect(wrapper.text()).toContain('Vaihda varaston ilmanvaihtokoneen ilmansuodatin.')
    expect(wrapper.text()).toContain('15.10.2026')
    expect(wrapper.text()).toContain('Tikkurilantie 44, 01300 Vantaa')

    expect(created.maps[0].view).toEqual({ center: [60.2891, 25.0371], zoom: 15 })
    expect(created.markers[0].latlng).toEqual([60.2891, 25.0371])

    const links = wrapper.findAllComponents(RouterLinkStub).map((link) => link.props('to'))
    expect(links).toContain('/requests/1/edit')
    expect(links).toContain('/map?request=1')
  })

  it('has no map for a request without a location', async () => {
    const wrapper = await mountView({ ...request, street_address: null, postal_code: null, city: null, latitude: null, longitude: null })

    expect(created.maps).toHaveLength(0)
    expect(wrapper.text()).toContain('Osoite–')
  })

  it('deletes the request after confirmation', async () => {
    const wrapper = await mountView(request)
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    fetch.mockResolvedValue({ ok: true, status: 204 })

    await wrapper.find('.btn-danger').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/requests/1', expect.objectContaining({ method: 'DELETE' }))
    expect(push).toHaveBeenCalledWith('/requests')
  })

  it('shows a message when the request does not exist', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 404)))
    const wrapper = mount(RequestDetailView, { global: { stubs: { RouterLink: RouterLinkStub } } })
    await flushPromises()

    expect(wrapper.text()).toContain('Pyyntöä ei löytynyt.')
  })
})
