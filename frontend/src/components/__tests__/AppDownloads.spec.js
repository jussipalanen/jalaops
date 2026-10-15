import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount, RouterLinkStub } from '@vue/test-utils'
import AppDownloads from '../AppDownloads.vue'
import { version } from '../../../package.json'

const apkUrl = 'https://github.com/jussipalanen/jalaops/releases/latest/download/jalaops.apk'

function mountDownloads(props = {}) {
  return mount(AppDownloads, { props, global: { stubs: { RouterLink: RouterLinkStub } } })
}

function stubDisplayMode(standalone) {
  vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: standalone }))
}

describe('AppDownloads', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('links to the newest Android app and shows the version', () => {
    const wrapper = mountDownloads()

    const link = wrapper.find(`a[href="${apkUrl}"]`)
    expect(link.text()).toContain('Lataa Android-sovellus')
    expect(link.text()).toContain(`Versio ${version}`)
  })

  it('tells how to add the app to an iPhone home screen', () => {
    const wrapper = mountDownloads()

    const ios = wrapper.find('details')
    expect(ios.find('summary').text()).toContain('Asenna iPhoneen tai iPadiin')
    expect(ios.find('summary').text()).toContain(`Versio ${version}`)
    expect(ios.find('ol').text()).toContain('Lisää Koti-valikkoon')
  })

  it('has compact links for the footer', () => {
    const wrapper = mountDownloads({ variant: 'links' })

    expect(wrapper.find(`a[href="${apkUrl}"]`).text()).toBe('Android-sovellus')
    const ios = wrapper.findComponent(RouterLinkStub)
    expect(ios.props('to')).toBe('/about')
    expect(ios.text()).toBe('iPhone-sovellus')
    expect(wrapper.find('details').exists()).toBe(false)
  })

  it('is hidden inside the installed app', () => {
    stubDisplayMode(true)

    expect(mountDownloads().find('a').exists()).toBe(false)
    expect(mountDownloads({ variant: 'links' }).find('a').exists()).toBe(false)
  })

  it('shows up in the browser', () => {
    stubDisplayMode(false)

    expect(mountDownloads().find(`a[href="${apkUrl}"]`).exists()).toBe(true)
  })
})
