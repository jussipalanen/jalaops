import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import MobileAppsView from '../MobileAppsView.vue'
import assetLinks from '../../../public/.well-known/assetlinks.json'
import { version } from '../../../package.json'

const apkUrl = 'https://github.com/jussipalanen/jalaops/releases/latest/download/jalaops.apk'
const digest = 'beb86b9a0c91fe3152bab51cfb88e916a5015b0e6a7f2b4f70ef53917dc05c3e'

function mockRelease(response) {
  const fetch = vi.fn().mockResolvedValue(response)
  vi.stubGlobal('fetch', fetch)
  return fetch
}

const release = {
  ok: true,
  json: () =>
    Promise.resolve({
      tag_name: 'v1.4.1',
      published_at: '2026-10-14T20:42:11Z',
      assets: [
        { name: 'jalaops.aab', size: 2143904, digest: `sha256:${'0'.repeat(64)}` },
        { name: 'jalaops.apk', size: 2067036, digest: `sha256:${digest}` },
      ],
    }),
}

async function mountView() {
  const wrapper = mount(MobileAppsView)
  await flushPromises()
  return wrapper
}

describe('MobileAppsView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('downloads the newest Android app and shows its release details', async () => {
    const fetch = mockRelease(release)
    const wrapper = await mountView()

    expect(fetch).toHaveBeenCalledWith('https://api.github.com/repos/jussipalanen/jalaops/releases/latest', expect.anything())
    const android = wrapper.find('[data-test="android"]')
    expect(android.find(`a[href="${apkUrl}"]`).text()).toContain('Lataa Android-sovellus')
    expect(android.text()).toContain('Versio 1.4.1 · 2,1 Mt · julkaistu 14.10.2026')
    expect(wrapper.find('[data-test="checksum"]').text()).toBe(digest)
  })

  it('tells how to add the app to an iPhone home screen', async () => {
    mockRelease(release)
    const ios = (await mountView()).find('[data-test="ios"]')

    expect(ios.text()).toContain('Versio 1.4.1 · verkkosovellus')
    expect(ios.text()).toContain('Lisää Koti-valikkoon')
  })

  it("shows the signing key's fingerprint from assetlinks.json", async () => {
    mockRelease(release)
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="fingerprint"]').text()).toBe(assetLinks[0].target.sha256_cert_fingerprints[0])
    expect(wrapper.find('[data-test="security"]').text()).toContain('Ei käyttöoikeuksia')
  })

  it("falls back to the web app's version when GitHub doesn't answer", async () => {
    mockRelease({ ok: false, status: 403 })
    const wrapper = await mountView()

    expect(wrapper.find('[data-test="android"]').text()).toContain(`Versio ${version}`)
    expect(wrapper.find('[data-test="checksum"]').exists()).toBe(false)
    expect(wrapper.find('[data-test="technical"]').text()).toContain('Näkyy julkaisusivulla')
  })

  it('ignores a checksum in an unexpected format', async () => {
    mockRelease({
      ok: true,
      json: () => Promise.resolve({ tag_name: 'v1.4.1', published_at: '2026-10-14T20:42:11Z', assets: [{ name: 'jalaops.apk', size: 1, digest: '<b>x</b>' }] }),
    })

    expect((await mountView()).find('[data-test="checksum"]').exists()).toBe(false)
  })

  it('keeps the checksum and fingerprint under Tekniset tiedot', async () => {
    mockRelease(release)
    const wrapper = await mountView()

    const technical = wrapper.find('[data-test="technical"]')
    expect(technical.element.tagName).toBe('DETAILS')
    expect(technical.find('[data-test="checksum"]').exists()).toBe(true)
    expect(technical.find('[data-test="fingerprint"]').exists()).toBe(true)
  })

  it('says the Android app updates itself', async () => {
    mockRelease(release)
    const android = (await mountView()).find('[data-test="android"]')

    expect(android.text()).toContain('se päivittyy itsestään')
    expect(android.text()).toContain('tästä lähteestä')
  })

  it("shows a QR code for opening the page on a phone when it's opened on a computer", async () => {
    mockRelease(release)
    const wrapper = await mountView()
    await vi.waitFor(() => expect(wrapper.find('[data-test="qr"] img').exists()).toBe(true))

    expect(wrapper.find('[data-test="qr"] img').attributes('src')).toMatch(/^data:image\/svg\+xml;charset=utf-8,/)
    expect(wrapper.find('[data-test="qr"]').text()).toContain('Avaa puhelimella')
    expect(wrapper.find('.badge').exists()).toBe(false)
  })

  it.each([
    ['Android', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/141.0 Mobile Safari/537.36', 'android'],
    ['iPhone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', 'ios'],
  ])('highlights the %s card on that phone, without a QR code', async (name, userAgent, card) => {
    vi.spyOn(window.navigator, 'userAgent', 'get').mockReturnValue(userAgent)
    mockRelease(release)
    const wrapper = await mountView()

    const own = wrapper.find(`[data-test="${card}"]`)
    expect(own.find('.badge').text()).toBe('Tämä laite')
    expect(own.classes()).toContain('ring-2')
    expect(wrapper.findAll('.badge')).toHaveLength(1)
    expect(wrapper.find('[data-test="qr"]').exists()).toBe(false)
  })

  it('puts the iPhone card first on an iPhone', async () => {
    vi.spyOn(window.navigator, 'userAgent', 'get').mockReturnValue('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)')
    mockRelease(release)

    expect((await mountView()).find('[data-test="ios"]').classes()).toContain('order-first')
  })

  it('hides the download inside the installed app', async () => {
    mockRelease(release)
    vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }))
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Käytät jo JalaOpsia asennettuna sovelluksena.')
    expect(wrapper.find(`a[href="${apkUrl}"]`).exists()).toBe(false)
  })
})
