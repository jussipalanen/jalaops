import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import ModuleView from '../ModuleView.vue'

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRoute: () => ({ params: { id: 'hello' } }),
}))

function jsonResponse(body, status = 200) {
  return { ok: status < 400, status, json: () => Promise.resolve(body) }
}

const hello = {
  id: 'hello',
  name: 'Hei maailma',
  description: 'Tervehtii ja laskee.',
  version: '1.0.0',
  enabled: true,
  form: [
    { name: 'name', label: 'Nimi', type: 'text', default: 'Maailma' },
    { name: 'numbers', label: 'Luvut', type: 'numbers', default: [3, 5, 10] },
  ],
  results: [
    { name: 'message', label: 'Tervehdys' },
    { name: 'average', label: 'Keskiarvo' },
    { name: 'sum', label: 'Summa', decimals: 2 },
  ],
  view: null,
}

async function mountView(module, ...runResponses) {
  const fetch = vi.fn().mockResolvedValueOnce(jsonResponse({ data: module }))
  runResponses.forEach((response) => fetch.mockResolvedValueOnce(response))
  vi.stubGlobal('fetch', fetch)

  const wrapper = mount(ModuleView, { attachTo: document.body, global: { stubs: { RouterLink: RouterLinkStub } } })
  await flushPromises()

  return wrapper
}

function sentBody(call = 1) {
  return JSON.parse(fetch.mock.calls[call][1].body)
}

describe('ModuleView', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    document.body.innerHTML = ''
  })

  it('draws a form from the module and shows the results', async () => {
    const wrapper = await mountView(
      hello,
      jsonResponse({ output: { message: 'Hei, Jussi!', average: 2.5, sum: 7.5, python: '3.13.5' } }),
    )

    expect(fetch).toHaveBeenCalledWith('/api/modules/hello', expect.any(Object))
    expect(wrapper.find('h1').text()).toBe('Hei maailma')
    expect(wrapper.find('#field-name').element.value).toBe('Maailma')
    expect(wrapper.find('#field-numbers').element.value).toBe('3, 5, 10')

    await wrapper.find('#field-name').setValue('Jussi')
    await wrapper.find('#field-numbers').setValue('1, 2.5 4')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(fetch.mock.calls[1][0]).toBe('/api/modules/hello/run')
    expect(sentBody()).toEqual({ name: 'Jussi', numbers: [1, 2.5, 4] })
    // Only the results listed in module.json, numbers in Finnish format.
    expect(wrapper.findAll('dt').map((term) => term.text())).toEqual(['Tervehdys', 'Keskiarvo', 'Summa'])
    expect(wrapper.findAll('dd').map((value) => value.text())).toEqual(['Hei, Jussi!', '2,5', '7,50'])
  })

  it('leaves out empty fields and checks numbers before sending', async () => {
    const wrapper = await mountView(hello, jsonResponse({ output: { message: 'Hei, Maailma!' } }))

    await wrapper.find('#field-numbers').setValue('1, kaksi')
    await wrapper.find('form').trigger('submit')

    expect(fetch).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[role="alert"]').text()).toContain('Luvut: anna luvut pilkulla erotettuina')

    await wrapper.find('#field-name').setValue('')
    await wrapper.find('#field-numbers').setValue('')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(sentBody()).toEqual({})
  })

  it("shows the module's reason when it fails", async () => {
    const wrapper = await mountView(
      hello,
      jsonResponse({ message: 'Moduulin suoritus epäonnistui.', reason: 'Lukujen pitää olla lista numeroita.' }, 422),
    )

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe(
      'Moduulin suoritus epäonnistui. Lukujen pitää olla lista numeroita.',
    )
  })

  it('says when the module is off', async () => {
    const wrapper = await mountView({ ...hello, enabled: false })

    expect(wrapper.find('[role="alert"]').text()).toBe('Moduuli ei ole käytössä.')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('says when the module does not exist', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({ message: 'Moduulia ei löytynyt.' }, 404)))

    const wrapper = mount(ModuleView, { global: { stubs: { RouterLink: RouterLinkStub } } })
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toBe('Moduulia ei löytynyt.')
  })

  describe("with the module's own view", () => {
    const withView = { ...hello, view: '<p>Oma näkymä</p>' }

    function frameOf(wrapper) {
      return wrapper.find('iframe').element
    }

    // A message from the frame, as its bridge would send it.
    async function fromFrame(wrapper, data) {
      window.dispatchEvent(new MessageEvent('message', { data, source: frameOf(wrapper).contentWindow }))
      await flushPromises()
    }

    it('shows the view in a sandboxed frame that blocks the network', async () => {
      const wrapper = await mountView(withView)

      const frame = frameOf(wrapper)
      expect(frame.getAttribute('sandbox')).toBe('allow-scripts allow-forms')
      expect(frame.getAttribute('srcdoc')).toContain('<body><p>Oma näkymä</p></body>')
      expect(frame.getAttribute('srcdoc')).toContain("default-src 'none'")
      expect(frame.getAttribute('srcdoc')).toContain('window.jalaops')
      expect(wrapper.find('form').exists()).toBe(false)
    })

    it("puts the module's CSS and JS files in inline, so a file can't end its tag early", async () => {
      const wrapper = await mountView({
        ...withView,
        assets: [
          { type: 'style', content: '.bar { fill: red }' },
          { type: 'script', content: 'window.lib = "</script><script>alert(1)</script>"' },
        ],
      })

      const srcdoc = frameOf(wrapper).getAttribute('srcdoc')
      expect(srcdoc).toContain('<style>.bar { fill: red }</style>')
      expect(srcdoc).toContain('<script>window.lib = "<\\/script><script>alert(1)<\\/script>"</script>')
      // The files come before the view, so its script can use them.
      expect(srcdoc.indexOf('window.lib')).toBeLessThan(srcdoc.indexOf('<body>'))
    })

    it('runs the module for the frame and sends back the output', async () => {
      const wrapper = await mountView(withView, jsonResponse({ output: { message: 'Hei, Jussi!' } }))
      const postMessage = vi.spyOn(frameOf(wrapper).contentWindow, 'postMessage')

      await fromFrame(wrapper, { type: 'jalaops:run', id: 1, input: { name: 'Jussi' } })

      expect(sentBody()).toEqual({ name: 'Jussi' })
      expect(postMessage).toHaveBeenCalledWith(
        { type: 'jalaops:result', id: 1, output: { message: 'Hei, Jussi!' } },
        '*',
      )
    })

    it('sends errors back to the frame', async () => {
      const wrapper = await mountView(withView, jsonResponse({ message: 'Moduuli ei ole käytössä.' }, 403))
      const postMessage = vi.spyOn(frameOf(wrapper).contentWindow, 'postMessage')

      await fromFrame(wrapper, { type: 'jalaops:run', id: 2, input: [] })
      expect(postMessage).toHaveBeenLastCalledWith(
        { type: 'jalaops:error', id: 2, message: 'Syötteen pitää olla objekti.' },
        '*',
      )

      await fromFrame(wrapper, { type: 'jalaops:run', id: 3, input: {} })
      expect(postMessage).toHaveBeenLastCalledWith(
        { type: 'jalaops:error', id: 3, message: 'Moduuli ei ole käytössä.' },
        '*',
      )
    })

    it('ignores messages from anywhere else', async () => {
      await mountView(withView)

      window.dispatchEvent(new MessageEvent('message', { data: { type: 'jalaops:run', id: 1, input: {} }, source: window }))
      await flushPromises()

      expect(fetch).toHaveBeenCalledTimes(1)
    })

    it('fits its height to the content, within limits', async () => {
      const wrapper = await mountView(withView)

      await fromFrame(wrapper, { type: 'jalaops:height', height: 512.4 })
      expect(frameOf(wrapper).style.height).toBe('513px')

      await fromFrame(wrapper, { type: 'jalaops:height', height: 99999 })
      expect(frameOf(wrapper).style.height).toBe('4000px')
    })
  })
})
