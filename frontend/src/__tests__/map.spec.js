import { describe, expect, it, vi } from 'vitest'
import { requestPopup } from '@/map'

describe('requestPopup', () => {
  it('shows user text as text, never as HTML', () => {
    const popup = requestPopup(
      { id: 5, title: '<img src=x onerror=alert(1)>', description: '<b>kuvaus</b>', priority: 'low', status: 'open' },
      () => {},
    )

    expect(popup.querySelector('img')).toBeNull()
    expect(popup.querySelector('b')).toBeNull()
    expect(popup.textContent).toContain('<img src=x onerror=alert(1)>')
  })

  it('shortens long descriptions', () => {
    const popup = requestPopup(
      { id: 5, title: 'Pitkä', description: 'a'.repeat(300), priority: 'low', status: 'open' },
      () => {},
    )

    expect(popup.querySelector('.map-popup-description').textContent).toHaveLength(160)
  })

  it('leaves modifier clicks to the browser, so the details can open in a new tab', () => {
    const openDetails = vi.fn()
    const popup = requestPopup({ id: 5, title: 'Uusi välilehti', priority: 'low', status: 'open' }, openDetails)

    const click = new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true })
    popup.querySelector('a').dispatchEvent(click)

    expect(openDetails).not.toHaveBeenCalled()
    expect(click.defaultPrevented).toBe(false)
  })
})
