import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

// The loading screen lives in index.html, inside #app, so Vue replaces it on mount.
// Vitest runs from frontend/, where index.html is.
const html = readFileSync(resolve(process.cwd(), 'index.html'), 'utf8')
const doc = new DOMParser().parseFromString(html, 'text/html')

describe('loading screen', () => {
  it('shows a Finnish status message inside #app until the app mounts', () => {
    const status = doc.querySelector('#app > .preloader[role="status"]')

    expect(status).not.toBeNull()
    expect(status.textContent.trim()).toContain('Ladataan JalaOpsia…')
    expect(status.querySelector('.preloader-spinner').getAttribute('aria-hidden')).toBe('true')
  })

  it('keeps still for users who prefer reduced motion', () => {
    expect(html).toMatch(/@media \(prefers-reduced-motion: reduce\)[^}]*\.preloader \{ animation: none/)
  })

  it('tells in Finnish when JavaScript is off', () => {
    expect(doc.querySelector('noscript').textContent).toContain('JalaOps tarvitsee toimiakseen JavaScriptin.')
  })
})
