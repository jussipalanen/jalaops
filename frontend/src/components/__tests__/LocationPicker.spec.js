import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { created } from '@/test/leaflet'
import LocationPicker from '../LocationPicker.vue'

describe('LocationPicker', () => {
  it('starts on Finland without coordinates', () => {
    mount(LocationPicker)

    expect(created.maps[0].bounds).toEqual([
      [59.7, 19.5],
      [70.1, 31.6],
    ])
    expect(created.markers).toHaveLength(0)
  })

  it('shows a draggable pin at the given coordinates', () => {
    mount(LocationPicker, { props: { latitude: 61.498, longitude: 23.766 } })

    expect(created.maps[0].view).toEqual({ center: [61.498, 23.766], zoom: 15 })
    expect(created.markers[0].latlng).toEqual([61.498, 23.766])
    expect(created.markers[0].options.draggable).toBe(true)
  })

  it('picks the clicked point, rounded to seven decimals', () => {
    const wrapper = mount(LocationPicker)

    created.maps[0].fire('click', { latlng: { lat: 61.49801234567, lng: 23.76601234567 } })

    expect(wrapper.emitted('pick')[0][0]).toEqual({ latitude: 61.4980123, longitude: 23.7660123 })
  })

  it('picks the point the pin is dragged to', () => {
    const wrapper = mount(LocationPicker, { props: { latitude: 61.498, longitude: 23.766 } })

    created.markers[0].setLatLng([61.5, 23.8]).fire('dragend')

    expect(wrapper.emitted('pick')[0][0]).toEqual({ latitude: 61.5, longitude: 23.8 })
  })

  it('moves the pin and zooms to the street when the coordinates change', async () => {
    const wrapper = mount(LocationPicker)

    await wrapper.setProps({ latitude: 65.0103, longitude: 25.4701 })
    expect(created.markers[0].latlng).toEqual([65.0103, 25.4701])
    expect(created.maps[0].view).toEqual({ center: [65.0103, 25.4701], zoom: 15 })

    await wrapper.setProps({ latitude: '', longitude: '' })
    expect(created.markers[0].removed).toBe(true)
  })

  it('keeps the view when the point is picked on the map', async () => {
    const wrapper = mount(LocationPicker, { props: { latitude: 61.498, longitude: 23.766 } })
    const map = created.maps[0]
    map.setView([61.498, 23.766], 11)

    map.fire('click', { latlng: { lat: 61.5, lng: 23.8 } })
    await wrapper.setProps(wrapper.emitted('pick')[0][0])

    expect(created.markers[0].latlng).toEqual([61.5, 23.8])
    expect(map.view.zoom).toBe(11)
  })
})
