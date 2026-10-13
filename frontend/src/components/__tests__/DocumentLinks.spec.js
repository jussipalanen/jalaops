import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DocumentLinks from '../DocumentLinks.vue'

const base = '/api/requests/12/export'

describe('DocumentLinks', () => {
  it('opens previews in a new tab and downloads the files', () => {
    const wrapper = mount(DocumentLinks, { props: { base } })

    const links = wrapper.findAll('a')
    expect(links.map((link) => [link.text(), link.attributes('href')])).toEqual([
      ['Esikatsele PDF\u00a0↗(avautuu uuteen välilehteen)', '/api/requests/12/export/pdf?inline=1'],
      ['Esikatsele HTML\u00a0↗(avautuu uuteen välilehteen)', '/api/requests/12/export/html?inline=1'],
      ['Lataa PDF', '/api/requests/12/export/pdf'],
      ['Lataa HTML', '/api/requests/12/export/html'],
    ])
    for (const preview of links.slice(0, 2)) {
      expect(preview.attributes('target')).toBe('_blank')
      expect(preview.attributes('rel')).toBe('noopener')
      expect(preview.attributes('download')).toBeUndefined()
    }
    for (const download of links.slice(2)) {
      expect(download.attributes('download')).toBe('')
      expect(download.attributes('target')).toBeUndefined()
    }
  })

  it('shows the links in a menu that closes on Escape, outside clicks and a choice', async () => {
    const wrapper = mount(DocumentLinks, {
      props: { base, title: 'Huolla trukki', variant: 'menu' },
      attachTo: document.body,
    })
    const button = wrapper.find('button')
    const menu = () => wrapper.find('div.absolute')

    expect(button.attributes('aria-label')).toBe('Tiedosto: Huolla trukki')
    expect(menu().isVisible()).toBe(false)

    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(menu().isVisible()).toBe(true)
    expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual([
      '/api/requests/12/export/pdf?inline=1',
      '/api/requests/12/export/html?inline=1',
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
