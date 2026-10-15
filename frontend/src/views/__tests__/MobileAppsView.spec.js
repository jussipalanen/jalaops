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
  afterEach(() => vi.unstubAllGlobals())

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
    expect(wrapper.find('[data-test="security"]').text()).toContain('näkyy julkaisusivulla')
  })

  it('ignores a checksum in an unexpected format', async () => {
    mockRelease({
      ok: true,
      json: () => Promise.resolve({ tag_name: 'v1.4.1', published_at: '2026-10-14T20:42:11Z', assets: [{ name: 'jalaops.apk', size: 1, digest: '<b>x</b>' }] }),
    })

    expect((await mountView()).find('[data-test="checksum"]').exists()).toBe(false)
  })

  it('hides the download inside the installed app', async () => {
    mockRelease(release)
    vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }))
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Käytät jo JalaOpsia asennettuna sovelluksena.')
    expect(wrapper.find(`a[href="${apkUrl}"]`).exists()).toBe(false)
  })
})
