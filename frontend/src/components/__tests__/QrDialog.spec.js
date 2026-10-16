import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import QrDialog from '../QrDialog.vue'

vi.mock('qrcode', () => ({ default: { toString: (text) => Promise.resolve(`<svg>${text}</svg>`) } }))

function mountDialog(open = true) {
  return mount(QrDialog, {
    props: { open, value: 'https://jalaops.example/requests/1', title: 'Avaa puhelimella', alt: 'QR-koodi', 'onUpdate:open': (value) => wrapper.setProps({ open: value }) },
    attachTo: document.body,
  })
}

let wrapper

describe('QrDialog', () => {
  afterEach(() => {
    wrapper?.unmount()
    vi.restoreAllMocks()
    delete window.navigator.wakeLock
  })

  it('keeps the screen on while the code is shown', async () => {
    const release = vi.fn().mockResolvedValue()
    const request = vi.fn().mockResolvedValue({ release })
    Object.defineProperty(window.navigator, 'wakeLock', { value: { request }, configurable: true })

    wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.find('dialog').element.open).toBe(true)
    expect(request).toHaveBeenCalledWith('screen')

    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog').element.open).toBe(false)
    expect(release).toHaveBeenCalled()
  })

  it('closes with a tap outside the code, or when the browser closes it (Escape)', async () => {
    wrapper = mountDialog()
    await flushPromises()

    await wrapper.find('dialog').trigger('click')
    expect(wrapper.props('open')).toBe(false)

    await wrapper.setProps({ open: true })
    await flushPromises()
    await wrapper.find('dialog > div').trigger('click')
    expect(wrapper.props('open')).toBe(true)

    await wrapper.find('dialog').trigger('close')
    expect(wrapper.props('open')).toBe(false)
  })

  it('shows the details to check in large text', async () => {
    wrapper = mount(QrDialog, { props: { open: true, value: 'x', title: 'Maksa', alt: 'QR', details: [['Summa', '122,36 €']] }, attachTo: document.body })
    await flushPromises()

    expect(wrapper.find('dl').text()).toBe('Summa122,36 €')
  })
})
