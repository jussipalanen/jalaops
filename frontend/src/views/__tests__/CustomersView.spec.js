import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import CustomersView from '../CustomersView.vue'
import CustomerForm from '../../components/CustomerForm.vue'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const customers = [
  { id: 1, name: 'Esimerkki Kiinteistöt Oy', business_id: '1234567-1', contact_person: 'Maija Mallikas', email: 'laskutus@esimerkki.example.com', billing_street_address: 'Mannerheimintie 12', billing_postal_code: '00100', billing_city: 'Helsinki', payment_terms_days: 14, requests_count: 4 },
  { id: 2, name: 'Pirkan Demohalli Oy', business_id: null, contact_person: null, email: null, billing_city: null, payment_terms_days: 30, requests_count: 0 },
]

function mountList() {
  return mount(CustomersView, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

describe('CustomersView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('lists customers with their details and a link to their requests', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: customers })))

    const wrapper = mountList()
    await flushPromises()

    const cards = wrapper.findAll('li')
    expect(wrapper.text()).toContain('2 asiakasta')
    expect(cards[0].text()).toContain('Y-tunnus 1234567-1')
    expect(cards[0].text()).toContain('Mannerheimintie 12, 00100 Helsinki')
    expect(cards[0].text()).toContain('14 pv')
    const links = cards[0].findAllComponents(RouterLinkStub)
    expect(links.map((link) => [link.text(), link.props('to')])).toEqual([
      ['Pyynnöt (4)', '/requests?customer=1'],
      ['Muokkaa', '/customers/1/edit'],
    ])
  })

  it('searches after a short pause', async () => {
    vi.useFakeTimers()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: customers })))
    const wrapper = mountList()
    await flushPromises()

    await wrapper.find('#customer-search').setValue('helsinki')
    await vi.advanceTimersByTimeAsync(300)

    expect(fetch).toHaveBeenLastCalledWith('/api/customers?search=helsinki', expect.anything())
    vi.useRealTimers()
  })

  it("shows why a customer with requests can't be deleted", async () => {
    const message = 'Asiakasta ei voi poistaa, koska sillä on pyyntöjä. Vaihda pyyntöjen asiakas tai poista pyynnöt ensin.'
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse({ data: customers })).mockResolvedValueOnce(jsonResponse({ message }, 409)))
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const wrapper = mountList()
    await flushPromises()

    await wrapper.find('button[aria-label="Poista: Esimerkki Kiinteistöt Oy"]').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe(message)
    expect(wrapper.findAll('li')).toHaveLength(2)
  })
})

describe('CustomerForm', () => {
  it('submits the fields, with empty optional fields as null', async () => {
    const wrapper = mount(CustomerForm)

    await wrapper.find('#name').setValue('Uusi Asiakas Oy')
    await wrapper.find('#business_id').setValue('1234567-1')
    await wrapper.find('#payment_terms_days').setValue('30')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      name: 'Uusi Asiakas Oy',
      business_id: '1234567-1',
      contact_person: null,
      email: null,
      phone: null,
      billing_street_address: null,
      billing_postal_code: null,
      billing_city: null,
      payment_terms_days: 30,
      notes: null,
    })
  })

  it('shows the API errors next to their fields', () => {
    const wrapper = mount(CustomerForm, { props: { errors: { business_id: ['Y-tunnuksen tarkistusmerkki ei täsmää.'] } } })

    expect(wrapper.find('#business_id-error').text()).toBe('Y-tunnuksen tarkistusmerkki ei täsmää.')
    expect(wrapper.find('#business_id').attributes('aria-invalid')).toBe('true')
  })
})
