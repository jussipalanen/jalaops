import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RequestDownloads from '../RequestDownloads.vue'

describe('RequestDownloads', () => {
  it('links to the PDF and HTML downloads', () => {
    const wrapper = mount(RequestDownloads, { props: { requestId: 12 } })

    const links = wrapper.findAll('a')
    expect(links.map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Lataa PDF', '/api/requests/12/export/pdf'],
      ['Lataa HTML', '/api/requests/12/export/html'],
    ])
    expect(links.every((link) => link.attributes('download') !== undefined)).toBe(true)
  })

  it('shows the formats in a menu that closes on Escape, outside clicks and a choice', async () => {
    const wrapper = mount(RequestDownloads, {
      props: { requestId: 12, title: 'Huolla trukki', variant: 'menu' },
      attachTo: document.body,
    })
    const button = wrapper.find('button')
    const menu = () => wrapper.find('div.absolute')

    expect(button.attributes('aria-label')).toBe('Lataa: Huolla trukki')
    expect(menu().isVisible()).toBe(false)

    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(menu().isVisible()).toBe(true)
    expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual([
      '/api/requests/12/export/pdf',
      '/api/requests/12/export/html',
    ])

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()
    expect(menu().isVisible()).toBe(false)

    await button.trigger('click')
    document.body.click()
    await wrapper.vm.$nextTick()
    expect(menu().isVisible()).toBe(false)

    await button.trigger('click')
    await wrapper.find('a').trigger('click')
    expect(menu().isVisible()).toBe(false)

    wrapper.unmount()
  })
})
