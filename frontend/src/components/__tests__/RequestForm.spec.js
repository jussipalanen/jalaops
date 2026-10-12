import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import RequestForm from '../RequestForm.vue'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

// jsdom has no PointerEvent, so the pad gets mouse events with pointer event names.
async function pointer(element, type, x = 0, y = 0) {
  element.element.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true, clientX: x, clientY: y }))
  await nextTick()
}

describe('RequestForm', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('starts with defaults for a new request', () => {
    const wrapper = mount(RequestForm)

    expect(wrapper.find('#title').element.value).toBe('')
    expect(wrapper.find('#priority').element.value).toBe('normal')
    expect(wrapper.find('#status').element.value).toBe('open')
    expect(wrapper.find('#priority').text()).toContain('Korkea')
    expect(wrapper.find('#status').text()).toContain('Työn alla')
  })

  it('fills in an existing request', () => {
    const wrapper = mount(RequestForm, {
      props: {
        request: {
          title: 'Vaihda ilmansuodatin',
          description: 'Varaston ilmanvaihtokone.',
          priority: 'high',
          status: 'in_progress',
          due_date: '2026-10-15',
        },
      },
    })

    expect(wrapper.find('#title').element.value).toBe('Vaihda ilmansuodatin')
    expect(wrapper.find('#description').element.value).toBe('Varaston ilmanvaihtokone.')
    expect(wrapper.find('#priority').element.value).toBe('high')
    expect(wrapper.find('#status').element.value).toBe('in_progress')
    expect(wrapper.find('#due_date').element.value).toBe('2026-10-15')
  })

  it('submits the entered values, sending empty optional fields as null', async () => {
    const wrapper = mount(RequestForm)

    await wrapper.find('#title').setValue('Korjaa vuotava hana')
    await wrapper.find('#priority').setValue('high')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      title: 'Korjaa vuotava hana',
      description: null,
      priority: 'high',
      status: 'open',
      due_date: null,
      street_address: null,
      postal_code: null,
      city: null,
      latitude: null,
      longitude: null,
    })
  })

  it('shows validation errors next to their fields', () => {
    const wrapper = mount(RequestForm, {
      props: { errors: { title: ['Kenttä otsikko on pakollinen.'] } },
    })

    expect(wrapper.find('#title-error').text()).toBe('Kenttä otsikko on pakollinen.')
    expect(wrapper.find('#title').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('#title').attributes('aria-describedby')).toBe('title-error')
  })

  it('emits cancel', async () => {
    const wrapper = mount(RequestForm)

    await wrapper.findAll('button').find((button) => button.text() === 'Peruuta').trigger('click')

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('submits the location', async () => {
    const wrapper = mount(RequestForm, { props: { request: { title: 'Huolla trukki' } } })

    await wrapper.find('#street_address').setValue(' Isokatu 32 ')
    await wrapper.find('#postal_code').setValue('90100')
    await wrapper.find('#city').setValue('Oulu')
    await wrapper.find('#latitude').setValue('65.0103')
    await wrapper.find('#longitude').setValue('25.4701')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).toMatchObject({
      street_address: 'Isokatu 32',
      postal_code: '90100',
      city: 'Oulu',
      latitude: 65.0103,
      longitude: 25.4701,
    })
  })

  it('finds the coordinates of the address', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({ latitude: 65.0103, longitude: 25.4701, display_name: 'Isokatu 32, Oulu, Suomi' }),
      ),
    )
    const wrapper = mount(RequestForm)

    const findButton = wrapper.findAll('button').find((button) => button.text() === 'Hae koordinaatit osoitteesta')
    expect(findButton.attributes('disabled')).toBeDefined()

    await wrapper.find('#street_address').setValue('Isokatu 32')
    await wrapper.find('#postal_code').setValue('90100')
    await wrapper.find('#city').setValue('Oulu')
    await findButton.trigger('click')
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith(
      `/api/geocode?q=${encodeURIComponent('Isokatu 32, 90100 Oulu')}`,
      expect.objectContaining({ method: 'GET' }),
    )
    expect(wrapper.find('#latitude').element.value).toBe('65.0103')
    expect(wrapper.find('#longitude').element.value).toBe('25.4701')
    expect(wrapper.find('[role="status"]').text()).toBe('Sijainti löytyi: Isokatu 32, Oulu, Suomi')
  })

  it('tells when the address is not found', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'Osoitetta ei löytynyt kartalta.' }, 404)))
    const wrapper = mount(RequestForm)

    await wrapper.find('#city').setValue('Olematon')
    await wrapper.findAll('button').find((button) => button.text() === 'Hae koordinaatit osoitteesta').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="status"]').text()).toContain('Osoitetta ei löytynyt kartalta.')
    expect(wrapper.find('#latitude').element.value).toBe('')
  })

  it('fills in the location from an address suggestion', async () => {
    vi.useFakeTimers()
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({
          data: [
            {
              label: 'Hämeenkatu 1, 33100 Tampere',
              street_address: 'Hämeenkatu 1',
              postal_code: '33100',
              city: 'Tampere',
              latitude: 61.4982,
              longitude: 23.7716,
            },
            {
              label: 'Hämeenkatu 1, 15110 Lahti',
              street_address: 'Hämeenkatu 1',
              postal_code: '15110',
              city: 'Lahti',
              latitude: 60.9837,
              longitude: 25.6531,
            },
          ],
        }),
      ),
    )
    const wrapper = mount(RequestForm, { attachTo: document.body })
    const input = wrapper.find('#street_address')

    await input.setValue('Hä')
    await vi.advanceTimersByTimeAsync(300)
    expect(fetch).not.toHaveBeenCalled()

    await input.setValue('Hämeenkatu 1')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(fetch).toHaveBeenCalledWith(
      `/api/geocode/suggest?q=${encodeURIComponent('Hämeenkatu 1')}`,
      expect.objectContaining({ method: 'GET' }),
    )
    expect(input.attributes('aria-expanded')).toBe('true')
    expect(wrapper.findAll('[role="option"]').map((option) => option.text())).toEqual([
      'Hämeenkatu 1, 33100 Tampere',
      'Hämeenkatu 1, 15110 Lahti',
    ])

    // Arrow down twice highlights the second address; Enter chooses it.
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(input.attributes('aria-activedescendant')).toBe('street_address-suggestions-1')
    await input.trigger('keydown', { key: 'Enter' })

    expect(input.element.value).toBe('Hämeenkatu 1')
    expect(wrapper.find('#postal_code').element.value).toBe('15110')
    expect(wrapper.find('#city').element.value).toBe('Lahti')
    expect(wrapper.find('#latitude').element.value).toBe('60.9837')
    expect(wrapper.find('#longitude').element.value).toBe('25.6531')
    expect(input.attributes('aria-expanded')).toBe('false')
    expect(wrapper.emitted('submit')).toBeUndefined()

    wrapper.unmount()
    vi.useRealTimers()
  })

  it('chooses a suggestion with a click or tap', async () => {
    vi.useFakeTimers()
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({
          data: [{ label: 'Isokatu 32, 90100 Oulu', street_address: 'Isokatu 32', postal_code: '90100', city: 'Oulu', latitude: 65.0103, longitude: 25.4701 }],
        }),
      ),
    )
    const wrapper = mount(RequestForm)

    await wrapper.find('#street_address').setValue('Isokatu 32')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    await wrapper.find('[role="option"]').trigger('mousedown')

    expect(wrapper.find('#city').element.value).toBe('Oulu')
    expect(wrapper.find('#latitude').element.value).toBe('65.0103')

    vi.useRealTimers()
  })

  it('signs off a completed request with a name and a drawn signature', async () => {
    const wrapper = mount(RequestForm, { props: { request: { title: 'Huolla trukki' } } })

    expect(wrapper.find('#signed_by').exists()).toBe(false)
    await wrapper.find('#status').setValue('completed')

    await wrapper.find('#signed_by').setValue('Maija Huoltaja')
    const pad = wrapper.find('canvas')
    await pointer(pad, 'pointerdown', 10, 10)
    await pointer(pad, 'pointerup')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).toMatchObject({
      status: 'completed',
      signed_by: 'Maija Huoltaja',
      completion_signature: expect.stringMatching(/^data:image\/png;base64,/),
    })
  })

  it('keeps a saved signature unless it is redrawn or removed', async () => {
    const request = { id: 7, title: 'Huolla trukki', status: 'completed', has_signature: true, signed_by: 'Maija', signed_at: '2026-10-12T15:30:00.000000Z' }
    const wrapper = mount(RequestForm, { props: { request } })

    expect(wrapper.find('img').attributes('src')).toBe(`/api/requests/7/signature?v=${encodeURIComponent(request.signed_at)}`)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('submit')[0][0]).not.toHaveProperty('completion_signature')
    expect(wrapper.emitted('submit')[0][0].signed_by).toBe('Maija')

    await wrapper.findAll('button').find((button) => button.text() === 'Poista allekirjoitus').trigger('click')
    expect(wrapper.find('canvas').exists()).toBe(true)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.emitted('submit')[1][0].completion_signature).toBeNull()
  })

  it('leaves the sign-off out for requests that are not completed', async () => {
    const wrapper = mount(RequestForm, { props: { request: { title: 'Huolla trukki', status: 'in_progress', signed_by: 'Maija' } } })

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')[0][0]).not.toHaveProperty('signed_by')
    expect(wrapper.emitted('submit')[0][0]).not.toHaveProperty('completion_signature')
  })
})
