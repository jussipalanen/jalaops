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
// The QR code's image shows the text it was made from, so tests can read it.
vi.mock('qrcode', () => ({ default: { toString: (text) => Promise.resolve(`<svg>${text}</svg>`) } }))

const qrText = (image) => decodeURIComponent(image.attributes('src')).replace(/^.*<svg>|<\/svg>$/g, '')
const phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const plain = (text) => text.replace(/\u00a0|\u202f/g, ' ')
const stubs = { global: { stubs: { RouterLink: RouterLinkStub } } }

const paymentQr = 'BCD\n002\n1\nSCT\nNDEAFIHH\nJalaOps Demo\nFI2112345600000785\nEUR133.76\n\nRF0810016'

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
  payment: { recipient: 'JalaOps Demo', iban: 'FI21 1234 5600 0007 85', bic: 'NDEAFIHH', qr: paymentQr },
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
  delete window.navigator.clipboard
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
    expect(wrapper.find('a[href="/api/invoices/5/export/pdf?inline=1"]').attributes('target')).toBe('_blank')
    expect(wrapper.find('a[href="/api/invoices/5/export/html"]').exists()).toBe(true)
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

  it('shows the payment details with copy buttons for a banking app, and the payment QR code', async () => {
    const writeText = vi.fn().mockResolvedValue()
    Object.defineProperty(window.navigator, 'clipboard', { value: { writeText }, configurable: true })
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { ...invoice, status: 'sent' } })))
    const wrapper = mount(InvoiceDetailView, stubs)
    await flushPromises()

    const payment = wrapper.find('[data-test="payment"]')
    expect(plain(payment.text())).toContain('SaajaJalaOps Demo')
    expect(payment.text()).toContain('FI21 1234 5600 0007 85 / NDEAFIHH')

    for (const [label, value] of [['Kopioi tilinumero', 'FI2112345600000785'], ['Kopioi viitenumero', '10016'], ['Kopioi summa', '133,76']]) {
      const button = payment.find(`button[aria-label="${label}"]`)
      await button.trigger('click')
      await flushPromises()
      expect(writeText).toHaveBeenLastCalledWith(value)
      expect(button.find('span:not(.sr-only)').text()).toBe('Kopioitu')
    }

    await vi.waitFor(() => expect(payment.find('img[alt="Maksun QR-koodi"]').exists()).toBe(true))
    expect(qrText(payment.find('img[alt="Maksun QR-koodi"]'))).toBe(paymentQr)
    expect(payment.text()).toContain('Maksa skannaamalla koodi pankkisovelluksella.')
  })

  it('opens the payment QR code on the whole screen on a phone, with the amount and reference to check', async () => {
    vi.spyOn(window.navigator, 'userAgent', 'get').mockReturnValue(phone)
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { ...invoice, status: 'sent' } })))
    const wrapper = mount(InvoiceDetailView, { ...stubs, attachTo: document.body })
    await flushPromises()

    const payment = wrapper.find('[data-test="payment"]')
    expect(payment.find('img[alt="Maksun QR-koodi"]').exists()).toBe(false)
    expect(wrapper.find('dialog').attributes('open')).toBeUndefined()

    await payment.findAll('button').find((button) => button.text() === 'Näytä maksukoodi').trigger('click')
    await flushPromises()
    const dialog = wrapper.find('dialog')
    expect(dialog.attributes('open')).toBeDefined()
    expect(dialog.find('h2').text()).toBe('Maksa pankkisovelluksella')
    expect(plain(dialog.text())).toContain('Summa133,76 €')
    expect(dialog.text()).toContain('Viitenumero10016')
    await vi.waitFor(() => expect(dialog.find('img[alt="Maksun QR-koodi"]').exists()).toBe(true))

    await dialog.findAll('button').find((button) => button.text() === 'Sulje').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog').attributes('open')).toBeUndefined()
    wrapper.unmount()
  })

  it('has no payment QR code once paid, or without a bank account', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { ...invoice, status: 'paid', payment: { recipient: 'JalaOps Demo', iban: null, bic: null, qr: null } } })))
    const wrapper = mount(InvoiceDetailView, stubs)
    await flushPromises()

    const payment = wrapper.find('[data-test="payment"]')
    expect(payment.text()).toContain('Lasku on maksettu.')
    expect(payment.text()).toContain('Tilinumero (IBAN)Ei asetettu')
    expect(payment.findAll('button').map((button) => button.text())).not.toContain('Näytä suurena')
    expect(wrapper.find('dialog').exists()).toBe(false)
  })
})
