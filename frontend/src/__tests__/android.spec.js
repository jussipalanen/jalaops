import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

// The Android app (a Trusted Web Activity) depends on these files matching.
// Vitest runs from frontend/.
const read = (path) => readFileSync(resolve(process.cwd(), path), 'utf8')
const manifest = JSON.parse(read('public/site.webmanifest'))
const twa = JSON.parse(read('../android/twa-manifest.json'))
const assetLinks = JSON.parse(read('public/.well-known/assetlinks.json'))

describe('Android app files', () => {
  it('lets the Android package open the site without an address bar', () => {
    const [link] = assetLinks

    expect(link.relation).toContain('delegate_permission/common.handle_all_urls')
    expect(link.target.namespace).toBe('android_app')
    expect(link.target.package_name).toBe(twa.packageId)
    expect(link.target.sha256_cert_fingerprints.length).toBeGreaterThan(0)
    for (const fingerprint of link.target.sha256_cert_fingerprints) {
      expect(fingerprint).toMatch(/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/)
    }
  })

  it('has an installable web app manifest with a maskable icon and shortcuts', () => {
    expect(manifest.start_url).toBe('/')
    expect(manifest.display).toBe('standalone')
    expect(manifest.icons).toContainEqual(expect.objectContaining({ src: '/icon-maskable-512.png', purpose: 'maskable' }))
    expect(manifest.shortcuts.map((shortcut) => shortcut.url)).toEqual(['/requests/new', '/requests', '/map'])
    expect(twa.maskableIconUrl).toBe(`https://${twa.host}/icon-maskable-512.png`)
  })

  it('shows a Finnish offline page when a page cannot load', () => {
    const sw = read('public/sw.js')

    expect(sw).toContain("event.request.mode === 'navigate'")
    expect(sw).toContain("const OFFLINE_PAGE = '/offline.html'")
    expect(read('public/offline.html')).toContain('<h1>Ei verkkoyhteyttä</h1>')
  })
})
