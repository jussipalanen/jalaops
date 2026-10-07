import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import RequestCreateView from '../RequestCreateView.vue'
import RequestEditView from '../RequestEditView.vue'

const push = vi.fn()

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRouter: () => ({ push }),
  useRoute: () => ({ params: { id: '1' } }),
}))

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

function mountView(component) {
  return mount(component, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

describe('RequestCreateView', () => {
  beforeEach(() => push.mockClear())
  afterEach(() => vi.unstubAllGlobals())

  it('creates a request and returns to the list', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } }, 201)))

    const wrapper = mountView(RequestCreateView)
    await wrapper.find('#title').setValue('Korjaa vuotava hana')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const [url, options] = fetch.mock.calls[0]
    expect(url).toBe('/api/requests')
    expect(options.method).toBe('POST')
    expect(JSON.parse(options.body)).toMatchObject({ title: 'Korjaa vuotava hana' })
    expect(push).toHaveBeenCalledWith('/requests')
  })

  it('shows Finnish validation errors from the API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse(
          {
            message: 'Kenttä otsikko on pakollinen.',
            errors: { title: ['Kenttä otsikko on pakollinen.'] },
          },
          422,
        ),
      ),
    )

    const wrapper = mountView(RequestCreateView)
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Tarkista lomakkeen tiedot.')
    expect(wrapper.find('#title-error').text()).toBe('Kenttä otsikko on pakollinen.')
    expect(push).not.toHaveBeenCalled()
  })

  it('shows an error when saving fails', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({}, 500)))

    const wrapper = mountView(RequestCreateView)
    await wrapper.find('#title').setValue('Korjaa vuotava hana')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Pyynnön tallentaminen epäonnistui.')
  })
})

describe('RequestEditView', () => {
  beforeEach(() => push.mockClear())
  afterEach(() => vi.unstubAllGlobals())

  const existing = {
    id: 1,
    title: 'Vaihda ilmansuodatin',
    description: null,
    priority: 'high',
    status: 'open',
    due_date: '2026-10-15',
  }

  it('loads the request and saves a changed status', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ data: existing }))
      .mockResolvedValueOnce(jsonResponse({ data: { ...existing, status: 'completed' } }))
    vi.stubGlobal('fetch', fetchMock)

    const wrapper = mountView(RequestEditView)
    await flushPromises()

    expect(fetchMock.mock.calls[0][0]).toBe('/api/requests/1')
    expect(wrapper.find('#title').element.value).toBe('Vaihda ilmansuodatin')

    await wrapper.find('#status').setValue('completed')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    const [url, options] = fetchMock.mock.calls[1]
    expect(url).toBe('/api/requests/1')
    expect(options.method).toBe('PUT')
    expect(JSON.parse(options.body)).toEqual({
      title: 'Vaihda ilmansuodatin',
      description: null,
      priority: 'high',
      status: 'completed',
      due_date: '2026-10-15',
    })
    expect(push).toHaveBeenCalledWith('/requests')
  })

  it('shows a message when the request does not exist', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ message: 'Pyyntöä ei löytynyt.' }, 404)),
    )

    const wrapper = mountView(RequestEditView)
    await flushPromises()

    expect(wrapper.text()).toContain('Pyyntöä ei löytynyt.')
    expect(wrapper.find('form').exists()).toBe(false)
  })
})
