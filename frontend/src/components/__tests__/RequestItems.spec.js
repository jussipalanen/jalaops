import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RequestItems from '../RequestItems.vue'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const listing = {
  data: [
    { id: 1, type: 'work', description: 'Loisteputkien vaihto', quantity: 1.5, unit: 'h', unit_price: 65, vat_rate: 25.5, net: 97.5, vat: 24.86, total: 122.36 },
    { id: 2, type: 'material', description: 'Loisteputki T8', quantity: 2, unit: 'kpl', unit_price: 8.9, vat_rate: 25.5, net: 17.8, vat: 4.54, total: 22.34 },
  ],
  totals: { net: 115.3, vat: 29.4, total: 144.7 },
  defaults: { vat_rate: 25.5, hourly_rate: 65 },
}

const empty = { data: [], totals: { net: 0, vat: 0, total: 0 }, defaults: { vat_rate: 25.5, hourly_rate: 72 } }

// Text with no-break spaces turned into plain spaces, as Intl formats euros "122,36 €".
const text = (wrapper) => wrapper.text().replace(/\u00a0|\u202f/g, ' ')

describe('RequestItems', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('lists the rows and totals in Finnish formats', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(listing)))

    const wrapper = mount(RequestItems, { props: { requestId: 7 } })
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/requests/7/items', expect.objectContaining({ method: 'GET' }))
    const table = wrapper.find('table')
    expect(table.findAll('tbody tr')).toHaveLength(2)
    expect(text(table)).toContain('1,5 h')
    expect(text(table)).toContain('65,00 €')
    expect(text(table)).toContain('25,5 %')
    expect(text(table)).toContain('122,36 €')
    const totals = text(wrapper.find('[aria-label="Summat"]'))
    expect(totals).toContain('Veroton yhteensä115,30 €')
    expect(totals).toContain('ALV yhteensä29,40 €')
    expect(totals).toContain('Yhteensä144,70 €')
  })

  it('adds a work row prefilled with the hourly rate, typed with decimal commas', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(jsonResponse(empty)).mockResolvedValueOnce(jsonResponse(listing, 201))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mount(RequestItems, { props: { requestId: 7 } })
    await flushPromises()

    expect(wrapper.text()).toContain('Ei vielä töitä tai tarvikkeita.')
    await wrapper.findAll('button').find((b) => b.text() === '+ Lisää rivi').trigger('click')

    // Work at the default hourly rate; switching to material clears the price and uses pieces.
    expect(wrapper.find('#item-unit').element.value).toBe('h')
    expect(wrapper.find('#item-unit_price').element.value).toBe('72')
    expect(wrapper.find('#item-vat_rate').element.value).toBe('25,5')
    await wrapper.find('#item-type').setValue('material')
    expect(wrapper.find('#item-unit').element.value).toBe('kpl')
    expect(wrapper.find('#item-unit_price').element.value).toBe('')
    await wrapper.find('#item-type').setValue('work')

    await wrapper.find('#item-description').setValue('Loisteputkien vaihto')
    await wrapper.find('#item-quantity').setValue('1,5')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const [url, options] = fetchMock.mock.calls[1]
    expect(url).toBe('/api/requests/7/items')
    expect(options.method).toBe('POST')
    expect(JSON.parse(options.body)).toEqual({ type: 'work', description: 'Loisteputkien vaihto', quantity: 1.5, unit: 'h', unit_price: 72, vat_rate: 25.5 })
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })

  it('edits a row without re-filling its price', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(jsonResponse(listing)).mockResolvedValueOnce(jsonResponse(listing))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mount(RequestItems, { props: { requestId: 7 } })
    await flushPromises()

    await wrapper.find('table button[aria-label="Muokkaa: Loisteputki T8"]').trigger('click')
    expect(wrapper.find('#item-unit_price').element.value).toBe('8,9')
    await wrapper.find('#item-type').setValue('work')
    expect(wrapper.find('#item-unit_price').element.value).toBe('8,9')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(fetchMock.mock.calls[1][0]).toBe('/api/requests/7/items/2')
    expect(fetchMock.mock.calls[1][1].method).toBe('PUT')
  })

  it('shows the API errors next to their fields', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(empty)).mockResolvedValueOnce(jsonResponse({ errors: { quantity: ['Kentän määrä on oltava suurempi kuin 0.'] } }, 422)))
    const wrapper = mount(RequestItems, { props: { requestId: 7 } })
    await flushPromises()

    await wrapper.findAll('button').find((b) => b.text() === '+ Lisää rivi').trigger('click')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('#item-quantity-error').text()).toBe('Kentän määrä on oltava suurempi kuin 0.')
    expect(wrapper.find('#item-quantity').attributes('aria-invalid')).toBe('true')
  })

  it('deletes a row after confirmation and reloads the totals', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse(listing))
      .mockResolvedValueOnce({ ok: true, status: 204 })
      .mockResolvedValueOnce(jsonResponse({ ...listing, data: listing.data.slice(0, 1), totals: { net: 97.5, vat: 24.86, total: 122.36 } }))
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(RequestItems, { props: { requestId: 7 } })
    await flushPromises()

    await wrapper.find('table button[aria-label="Poista: Loisteputki T8"]').trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls[1]).toEqual(['/api/requests/7/items/2', expect.objectContaining({ method: 'DELETE' })])
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(text(wrapper.find('[aria-label="Summat"]'))).toContain('Yhteensä122,36 €')
  })
})
