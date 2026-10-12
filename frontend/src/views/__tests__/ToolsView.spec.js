import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import ToolsView from '../ToolsView.vue'

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const validReport = {
  dry_run: true,
  total: 2,
  valid: 2,
  invalid: 0,
  imported: 0,
  errors: [],
  preview: [
    { title: 'Huolla trukki', priority: 'high', status: 'open', due_date: '2026-10-15', city: 'Oulu' },
    { title: 'Korjaa hana', priority: 'normal', status: 'open', due_date: null, city: null },
  ],
}

function mountView() {
  return mount(ToolsView, { global: { stubs: { RouterLink: RouterLinkStub } } })
}

async function chooseFile(wrapper) {
  const input = wrapper.find('#import-file')
  const file = new File(['title;priority;status\n'], 'pyynnot.csv', { type: 'text/csv' })
  Object.defineProperty(input.element, 'files', { value: [file] })
  await input.trigger('change')
}

describe('ToolsView', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('exports all requests or a filtered set', async () => {
    const wrapper = mountView()
    const csvLink = () => wrapper.find('a[href^="/api/requests/export?format=csv"]').attributes('href')

    expect(csvLink()).toBe('/api/requests/export?format=csv')
    await wrapper.find('#export-status').setValue('open')
    await wrapper.find('#export-priority').setValue('high')
    expect(csvLink()).toBe('/api/requests/export?format=csv&status=open&priority=high')
  })

  it('checks the file first and then imports it', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse(validReport))
      .mockResolvedValueOnce(jsonResponse({ ...validReport, dry_run: false, imported: 2 }, 201))
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mountView()
    const importButton = () => wrapper.findAll('button').find((button) => button.text().startsWith('Tuo'))

    expect(importButton().attributes('disabled')).toBeDefined()
    await chooseFile(wrapper)
    await wrapper.findAll('button').find((button) => button.text() === 'Tarkista tiedosto').trigger('click')
    await flushPromises()

    const [url, options] = fetchMock.mock.calls[0]
    expect(url).toBe('/api/requests/import')
    expect(options.body.get('dry_run')).toBe('1')
    expect(options.body.get('file').name).toBe('pyynnot.csv')
    expect(wrapper.text()).toContain('2 kunnossa')
    expect(wrapper.find('tbody').text()).toContain('Huolla trukki')
    expect(importButton().text()).toBe('Tuo 2 pyyntöä')

    await importButton().trigger('click')
    await flushPromises()

    expect(fetchMock.mock.calls[1][1].body.get('dry_run')).toBe('0')
    expect(wrapper.find('[role="status"]').text()).toContain('2 pyyntöä tuotiin.')
  })

  it('lists invalid rows and does not offer the import', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse({
          ...validReport,
          valid: 1,
          invalid: 1,
          errors: [{ row: 3, title: null, messages: ['Kenttä otsikko on pakollinen.'] }],
          preview: validReport.preview.slice(0, 1),
        }),
      ),
    )
    const wrapper = mountView()

    await chooseFile(wrapper)
    await wrapper.findAll('button').find((button) => button.text() === 'Tarkista tiedosto').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('1 virheellistä')
    expect(wrapper.text()).toContain('Rivi 3: Kenttä otsikko on pakollinen.')
    expect(wrapper.findAll('button').find((button) => button.text().startsWith('Tuo')).attributes('disabled')).toBeDefined()
  })

  it('shows why a file cannot be read', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse({ message: 'Tiedostossa ei ole yhtään pyyntöä.', errors: { file: ['Tiedostossa ei ole yhtään pyyntöä.'] } }, 422)),
    )
    const wrapper = mountView()

    await chooseFile(wrapper)
    await wrapper.findAll('button').find((button) => button.text() === 'Tarkista tiedosto').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Tiedostossa ei ole yhtään pyyntöä.')
  })
})
