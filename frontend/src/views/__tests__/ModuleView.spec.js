import { afterEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils'
import ModuleView from '../ModuleView.vue'

// The page's address: /tools/modules/hello, or a module's own route.
const route = reactive({ params: { id: 'hello' }, query: {}, meta: {}, fullPath: '/tools/modules/hello' })

function goTo({ path, query = {} }) {
  route.query = query
  route.fullPath = `${path}?${new URLSearchParams(query)}`
}

const router = {
  push: vi.fn(async (location) => goTo(location)),
  replace: vi.fn(async (location) => goTo(location)),
}

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal()),
  useRoute: () => route,
  useRouter: () => router,
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
    Object.assign(route, { params: { id: 'hello' }, query: {}, meta: {}, fullPath: '/tools/modules/hello' })
    router.push.mockClear()
    router.replace.mockClear()
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
      // Popups only for links, which open in a new tab outside the sandbox.
      expect(frame.getAttribute('sandbox')).toBe('allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox')
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

    it("gives the view the page address's parameters, safely escaped", async () => {
      route.query = { news: '3', tag: '</script><script>alert(1)', 'Bad-Key': 'x', list: ['a', 'b'] }
      const wrapper = await mountView(withView)

      const srcdoc = frameOf(wrapper).getAttribute('srcdoc')
      expect(srcdoc).toContain(
        '<script type="application/json" id="jalaops-params">{"news":"3","tag":"\\u003c/script>\\u003cscript>alert(1)"}</script>',
      )
      expect(srcdoc.indexOf('jalaops-params')).toBeLessThan(srcdoc.indexOf('window.jalaops'))
    })

    it('moves to another page of the view and tells the view', async () => {
      const wrapper = await mountView(withView)
      const postMessage = vi.spyOn(frameOf(wrapper).contentWindow, 'postMessage')
      const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})

      await fromFrame(wrapper, { type: 'jalaops:navigate', params: { news: 3, evil: { a: 1 } } })

      expect(router.push).toHaveBeenCalledWith({ path: '/tools/modules/hello', query: { news: '3' } })
      expect(scrollTo).toHaveBeenCalledWith({ top: 0 })
      expect(postMessage).toHaveBeenLastCalledWith({ type: 'jalaops:params', params: { news: '3' } }, '*')
      // The frame isn't drawn again.
      expect(frameOf(wrapper).getAttribute('srcdoc')).toContain('{}</script>')
    })

    describe('with routes of its own', () => {
      const news = {
        ...withView,
        id: 'news',
        routes: [
          { path: '/news', params: {} },
          { path: '/news/new', params: { view: 'new' } },
          { path: '/news/:news', params: {} },
          { path: '/news/:news/edit', params: { view: 'edit' } },
        ],
      }

      it("gives the view the path's and the route's parameters", async () => {
        Object.assign(route, {
          params: { news: 'syyshuollot' },
          query: { tag: 'huolto' },
          meta: { module: 'news', moduleParams: { view: 'edit' } },
          fullPath: '/news/syyshuollot/edit?tag=huolto',
        })
        const wrapper = await mountView(news)

        expect(fetch).toHaveBeenCalledWith('/api/modules/news', expect.any(Object))
        expect(frameOf(wrapper).getAttribute('srcdoc')).toContain('{"tag":"huolto","view":"edit","news":"syyshuollot"}</script>')
        expect(router.replace).not.toHaveBeenCalled()
      })

      it("moves to the route that fits the parameters", async () => {
        Object.assign(route, { params: {}, meta: { module: 'news', moduleParams: {} }, fullPath: '/news' })
        const wrapper = await mountView(news)

        for (const [params, location] of [
          [{ news: 'syyshuollot' }, { path: '/news/syyshuollot', query: {} }],
          [{ news: '3', view: 'edit' }, { path: '/news/3/edit', query: {} }],
          [{ view: 'new' }, { path: '/news/new', query: {} }],
          [{ tag: 'huolto' }, { path: '/news', query: { tag: 'huolto' } }],
          [{ news: 'a b/c', page: '2' }, { path: '/news/a%20b%2Fc', query: { page: '2' } }],
        ]) {
          await fromFrame(wrapper, { type: 'jalaops:navigate', params })
          expect(router.push).toHaveBeenLastCalledWith(location)
        }
      })

      it('can change the address without a new history entry', async () => {
        Object.assign(route, { params: { news: '3' }, meta: { module: 'news', moduleParams: {} }, fullPath: '/news/3' })
        const wrapper = await mountView(news)
        const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
        scrollTo.mockClear()

        await fromFrame(wrapper, { type: 'jalaops:navigate', params: { news: 'syyshuollot' }, replace: true })

        expect(router.replace).toHaveBeenCalledWith({ path: '/news/syyshuollot', query: {} })
        expect(router.push).not.toHaveBeenCalled()
        expect(scrollTo).not.toHaveBeenCalled()
      })

      it('sends an old address to the module\'s own', async () => {
        Object.assign(route, { params: { id: 'news' }, query: { news: '3' }, fullPath: '/tools/modules/news?news=3' })
        const wrapper = await mountView(news)

        expect(router.replace).toHaveBeenCalledWith({ path: '/news/3', query: {} })
        expect(wrapper.find('iframe').exists()).toBe(true)
      })

      it('stays at the old address while the module is off', async () => {
        Object.assign(route, { params: { id: 'news' } })
        await mountView({ ...news, enabled: false })

        expect(router.replace).not.toHaveBeenCalled()
      })
    })

    it('gives the page the title and description the view asks for, until the address changes', async () => {
      const meta = document.createElement('meta')
      meta.name = 'description'
      meta.content = 'Sovelluksen kuvaus'
      document.head.append(meta)
      const wrapper = await mountView(withView)
      expect(document.title).toBe('Hei maailma – JalaOps')

      await fromFrame(wrapper, { type: 'jalaops:page', title: ' Syyshuollot\n alkavat ', description: 'Suodattimet vaihdetaan.' })
      expect(document.title).toBe('Syyshuollot alkavat – JalaOps')
      expect(meta.content).toBe('Suodattimet vaihdetaan.')

      await fromFrame(wrapper, { type: 'jalaops:page', title: { evil: 1 }, description: 'x'.repeat(500) })
      expect(document.title).toBe('Hei maailma – JalaOps')
      expect(meta.content).toHaveLength(300)

      await fromFrame(wrapper, { type: 'jalaops:navigate', params: {} })
      expect(meta.content).toBe('Sovelluksen kuvaus')

      await fromFrame(wrapper, { type: 'jalaops:page', description: 'Taas' })
      wrapper.unmount()
      expect(meta.content).toBe('Sovelluksen kuvaus')
      meta.remove()
    })

    it('fits its height to the content, within limits', async () => {
      const wrapper = await mountView(withView)

      await fromFrame(wrapper, { type: 'jalaops:height', height: 512.4 })
      expect(frameOf(wrapper).style.height).toBe('513px')

      await fromFrame(wrapper, { type: 'jalaops:height', height: 99999 })
      expect(frameOf(wrapper).style.height).toBe('20000px')
    })
  })
})
