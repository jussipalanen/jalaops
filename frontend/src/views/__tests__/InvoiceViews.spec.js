import { ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import InvoiceCreateView from '../InvoiceCreateView.vue'
import InvoiceDetailView from '../InvoiceDetailView.vue'
import InvoicesView from '../InvoicesView.vue'

const push = vi.fn()
const replace = vi.fn()
const route = { params: { id: '5' }, query: {} }

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRouter: () => ({ push, replace }),
  useRoute: () => route,
}))

const customerOptions = ref([{ id: 3, name: 'Esimerkki Kiinteistöt Oy' }])
vi.mock('@/composables/useCustomerOptions', () => ({ useCustomerOptions: () => customerOptions }))

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const plain = (text) => text.replace(/\u00a0|\u202f/g, ' ')
const stubs = { global: { stubs: { RouterLink: RouterLinkStub } } }

const invoice = {
  id: 5,
  number: 1001,
  reference_number: '10016',
  customer: { id: 3, name: 'Esimerkki Kiinteistöt Oy' },
  customer_business_id: '1234567-1',
  billing_street_address: 'Mannerheimintie 12',
  billing_postal_code: '00100',
  billing_city: 'Helsinki',
  issue_date: '2026-10-14',
  due_date: '2026-10-28',
  status: 'draft',
  overdue: false,
  paid_at: null,
  notes: null,
  totals: { net: 107.5, vat: 26.26, total: 133.76 },
  vat_breakdown: [{ rate: 25.5, net: 97.5, vat: 24.86 }, { rate: 14, net: 10, vat: 1.4 }],
  lines: [
    { id: 1, service_request_id: 7, request_title: 'Vaihda ilmansuodatin', delivered_on: '2026-10-12', type: 'work', description: 'Asennustyö', quantity: 1.5, unit: 'h', unit_price: 65, vat_rate: 25.5, total: 122.36 },
    { id: 2, service_request_id: 8, request_title: 'Kokous', delivered_on: null, type: 'material', description: 'Kahvi', quantity: 2, unit: 'kpl', unit_price: 5, vat_rate: 14, total: 11.4 },
  ],
}

beforeEach(() => {
  push.mockClear()
  replace.mockClear()
  route.query = {}
})
afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('InvoicesView', () => {
  it('lists invoices with their status, dates and totals, and exports for accounting', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [{ ...invoice, status: 'sent', overdue: true }] })))
    route.query = { status: 'overdue' }

    const wrapper = mount(InvoicesView, stubs)
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/invoices?status=overdue', expect.anything())
    const card = wrapper.find('li')
    expect(card.text()).toContain('Lasku 1001')
    expect(card.text()).toContain('Erääntynyt')
    expect(card.text()).toContain('28.10.2026')
    expect(plain(card.text())).toContain('133,76 €')
    expect(wrapper.findAll('a[href^="/api/invoices/export"]').map((link) => link.attributes('href'))).toEqual([
      '/api/invoices/export?format=csv&status=overdue',
      '/api/invoices/export?format=json&status=overdue',
    ])
  })
})

describe('InvoiceCreateView', () => {
  const billable = [
    { id: 7, title: 'Vaihda ilmansuodatin', due_date: null, items: [{}, {}], totals: { total: 144.7 } },
    { id: 8, title: 'Testaa paloilmoitin', due_date: '2026-10-01', items: [{}], totals: { total: 180.72 } },
  ]

  it("invoices the request chosen from its 'Luo lasku'", async () => {
    route.query = { customer: '3', request: '8' }
    const fetchMock = vi.fn().mockResolvedValueOnce(jsonResponse({ data: billable })).mockResolvedValueOnce(jsonResponse({ data: { id: 9 } }, 201))
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mount(InvoiceCreateView, stubs)
    await flushPromises()

    expect(fetchMock.mock.calls[0][0]).toBe('/api/customers/3/billable-requests')
    const boxes = wrapper.findAll('input[type="checkbox"]')
    expect(boxes.map((box) => box.element.checked)).toEqual([false, true])
    expect(plain(wrapper.text())).toContain('Yhteensä (sis. ALV)180,72 €')

    await boxes[0].setValue(true)
    expect(plain(wrapper.text())).toContain('325,42 €')

    await wrapper.findAll('button').find((button) => button.text() === 'Luo laskuluonnos').trigger('click')
    await flushPromises()

    expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({ customer_id: 3, request_ids: [8, 7], notes: null })
    expect(push).toHaveBeenCalledWith('/invoices/9')
  })

  it('explains when the customer has nothing to invoice', async () => {
    route.query = { customer: '3' }
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: [] })))

    const wrapper = mount(InvoiceCreateView, stubs)
    await flushPromises()

    expect(wrapper.text()).toContain('Asiakkaalla ei ole laskutettavia pyyntöjä.')
    expect(wrapper.findAll('button').find((button) => button.text() === 'Luo laskuluonnos').attributes('disabled')).toBeDefined()
  })
})

describe('InvoiceDetailView', () => {
  it('shows the invoice with rows by request and VAT by rate', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: invoice })))

    const wrapper = mount(InvoiceDetailView, stubs)
    await flushPromises()

    expect(wrapper.find('h1').text()).toBe('Lasku 1001')
    expect(wrapper.text()).toContain('Luonnos')
    expect(wrapper.text()).toContain('Mannerheimintie 12, 00100 Helsinki')
    expect(wrapper.text()).toContain('Viitenumero10016')
    expect(wrapper.findAll('h3').map((heading) => heading.text())).toEqual(['Vaihda ilmansuodatin · toimitettu 12.10.2026', 'Kokous'])
    const vat = plain(wrapper.find('[aria-label="ALV-erittely"]').text())
    expect(vat).toContain('25,5 %97,50 €24,86 €122,36 €')
    expect(vat).toContain('14 %10,00 €1,40 €11,40 €')
    expect(plain(wrapper.text())).toContain('Maksettava yhteensä133,76 €')
    expect(wrapper.find('a[href="/api/invoices/5/pdf"]').exists()).toBe(true)
  })

  it('marks a draft as sent after confirmation, then as paid', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: invoice }))
      .mockResolvedValueOnce(jsonResponse({ data: { ...invoice, status: 'sent' } }))
      .mockResolvedValueOnce(jsonResponse({ data: { ...invoice, status: 'paid', paid_at: '2026-10-20T10:00:00Z' } }))
    vi.stubGlobal('fetch', fetchMock)
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(InvoiceDetailView, stubs)
    await flushPromises()

    await wrapper.findAll('button').find((button) => button.text() === 'Merkitse lähetetyksi').trigger('click')
    await flushPromises()
    expect(fetchMock.mock.calls[1]).toEqual(['/api/invoices/5/status', expect.objectContaining({ method: 'PATCH', body: JSON.stringify({ status: 'sent' }) })])
    expect(wrapper.findAll('button').some((button) => button.text() === 'Poista luonnos')).toBe(false)

    await wrapper.findAll('button').find((button) => button.text() === 'Merkitse maksetuksi').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Maksettu')
    expect(wrapper.findAll('button').some((button) => button.text() === 'Palauta maksamattomaksi')).toBe(true)
  })

  it('deletes a draft and returns to the list', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse({ data: invoice })).mockResolvedValueOnce({ ok: true, status: 204 }))
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mount(InvoiceDetailView, stubs)
    await flushPromises()

    await wrapper.findAll('button').find((button) => button.text() === 'Poista luonnos').trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenLastCalledWith('/api/invoices/5', expect.objectContaining({ method: 'DELETE' }))
    expect(push).toHaveBeenCalledWith('/invoices')
  })
})
