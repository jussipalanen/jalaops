import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import DashboardCounts from '../DashboardCounts.vue'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

function mountCounts() {
  return mount(DashboardCounts, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

describe('DashboardCounts', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('shows the counts, each linking to the matching list', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ open: 8, in_progress: 2, completed: 7, overdue: 5, total: 17, invoices: { unpaid: 3, unpaid_total: 512.4, overdue: 1 } })),
    )

    const wrapper = mountCounts()
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith('/api/dashboard', expect.objectContaining({ method: 'GET' }))
    const cards = wrapper.findAllComponents(RouterLinkStub).slice(0, 4)
    expect(cards.map((card) => [card.attributes('aria-label'), card.props('to')])).toEqual([
      ['Avoimet: 8', '/requests?status=open'],
      ['Työn alla: 2', '/requests?status=in_progress'],
      ['Valmiit: 7', '/requests?status=completed'],
      ['Myöhässä: 5', '/requests?due=overdue'],
    ])
    // Overdue requests are highlighted.
    expect(cards[3].classes()).toContain('bg-red-50')
    expect(cards[0].classes()).not.toContain('bg-red-50')

    // Invoicing: unpaid with their sum, and overdue invoices highlighted.
    const invoiceCards = wrapper.findAllComponents(RouterLinkStub).slice(4)
    expect(invoiceCards.map((card) => [card.attributes('aria-label').replace(/\u00a0|\u202f/g, ' '), card.props('to')])).toEqual([
      ['Maksamatta · 512,40 €: 3', '/invoices?status=sent'],
      ['Erääntyneet laskut: 1', '/invoices?status=overdue'],
    ])
    expect(invoiceCards[1].classes()).toContain('bg-red-50')
  })

  it('does not highlight when nothing is overdue', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ open: 1, in_progress: 0, completed: 0, overdue: 0, total: 1 })))

    const wrapper = mountCounts()
    await flushPromises()

    expect(wrapper.findAllComponents(RouterLinkStub)[3].classes()).not.toContain('bg-red-50')
  })

  it('tells when the counts cannot be loaded', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 500)))

    const wrapper = mountCounts()
    await flushPromises()

    expect(wrapper.find('.alert-error').text()).toBe('Lukumäärien lataaminen epäonnistui.')
  })
})
