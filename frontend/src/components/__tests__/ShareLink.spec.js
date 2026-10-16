import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ShareLink from '../ShareLink.vue'

const props = { url: 'https://jalaops.example/requests/1', title: 'Vaihda ilmansuodatin' }

describe('ShareLink', () => {
  afterEach(() => {
    delete window.navigator.share
    delete window.navigator.clipboard
  })

  it("opens the device's share menu", async () => {
    const share = vi.fn().mockRejectedValue(new DOMException('Cancelled', 'AbortError'))
    Object.defineProperty(window.navigator, 'share', { value: share, configurable: true })
    const wrapper = mount(ShareLink, { props })

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toBe('Jaa')
    expect(share).toHaveBeenCalledWith(props)
  })

  it('copies the link where there is no share menu', async () => {
    const writeText = vi.fn().mockResolvedValue()
    Object.defineProperty(window.navigator, 'clipboard', { value: { writeText }, configurable: true })
    const wrapper = mount(ShareLink, { props })

    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(writeText).toHaveBeenCalledWith(props.url)
    expect(wrapper.find('span:not(.sr-only)').text()).toBe('Linkki kopioitu')
    expect(wrapper.find('[role="status"]').text()).toBe('Linkki kopioitu')
  })

  it('shows nothing where the browser can neither share nor copy', () => {
    expect(mount(ShareLink, { props }).find('button').exists()).toBe(false)
  })
})
