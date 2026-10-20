import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PrivacyView from '../PrivacyView.vue'

describe('PrivacyView', () => {
  it('tells what data the app handles and where it goes', () => {
    const wrapper = mount(PrivacyView)

    expect(wrapper.find('h1').text()).toBe('Tietosuoja')
    expect(wrapper.text()).toContain('Älä tallenna sovellukseen oikeita henkilötietoja.')
    expect(wrapper.findAll('dt').map((term) => term.text())).toEqual([
      'Karttapohja: OpenFreeMap ↗',
      'Osoitehaku: OpenStreetMap Nominatim ja Photon ↗',
      'AI-tilannekatsaus: Google Gemini ↗',
    ])
    expect(wrapper.text()).toContain('Asiakkaita, osoitteita, kuvauksia tai allekirjoituksia ei lähetetä.')
  })
})
