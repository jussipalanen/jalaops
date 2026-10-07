import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RequestForm from '../RequestForm.vue'

describe('RequestForm', () => {
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

    await wrapper.find('button[type="button"]').trigger('click')

    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })
})
