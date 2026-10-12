import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import SignaturePad from '../SignaturePad.vue'

// jsdom has no PointerEvent, so the pad gets mouse events with pointer event names.
async function pointer(element, type, x = 0, y = 0) {
  element.element.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y }))
  await nextTick()
}

describe('SignaturePad', () => {
  it('emits the drawing as a PNG after a stroke and null when cleared', async () => {
    const wrapper = mount(SignaturePad, { props: { id: 'signature' } })
    const canvas = wrapper.find('canvas')
    const clear = wrapper.find('button')

    expect(clear.attributes('disabled')).toBeDefined()

    await pointer(canvas, 'pointerdown', 10, 10)
    await pointer(canvas, 'pointermove', 40, 20)
    await pointer(canvas, 'pointerup')

    expect(wrapper.emitted('change')[0][0]).toMatch(/^data:image\/png;base64,/)
    expect(clear.attributes('disabled')).toBeUndefined()

    await clear.trigger('click')
    expect(wrapper.emitted('change')[1][0]).toBeNull()
  })

  it('ignores pointer moves without a press', async () => {
    const wrapper = mount(SignaturePad, { props: { id: 'signature' } })

    await pointer(wrapper.find('canvas'), 'pointermove', 40, 20)
    await pointer(wrapper.find('canvas'), 'pointerup')

    expect(wrapper.emitted('change')).toBeUndefined()
  })
})
