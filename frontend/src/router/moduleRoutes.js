import { apiGet } from '@/api/client'

// Module routes: addresses of their own for the pages of modules that are on
// (module.json's "routes", e.g. /news/:news). The app's pages don't wait for
// them: the first address that matches no page fetches the modules once,
// adds their routes and tries the address again. Turning a module on or off
// in Työkalut adds or removes its routes.

const ModuleView = () => import('../views/ModuleView.vue')

// The names of each module's routes in the router, by module id.
const added = new Map()

let loaded = false
let loading = null

// Adds the routes of the modules that are on, and removes those of the
// modules that are off. A route whose address the app already has is
// skipped (the backend also keeps modules off the app's own addresses).
export function syncModuleRoutes(router, modules) {
  for (const module of modules) {
    for (const name of added.get(module.id) ?? []) {
      router.removeRoute(name)
    }
    added.delete(module.id)

    if (!module.enabled || !module.routes?.length) {
      continue
    }

    const names = []

    for (const route of module.routes) {
      const example = route.path.replace(/:[a-z0-9_]+/g, 'x')

      if (router.resolve(example).name !== 'not-found') {
        continue
      }

      const name = `module:${module.id}:${route.path}`
      router.addRoute({
        path: route.path,
        name,
        component: ModuleView,
        meta: { title: module.name, module: module.id, moduleParams: route.params ?? {} },
      })
      names.push(name)
    }

    added.set(module.id, names)
  }
}

// Fetches the modules and adds their routes, once. A failed fetch is tried
// again on the next unknown address.
export function loadModuleRoutes(router) {
  loading ??= apiGet('/modules')
    .then(({ data }) => {
      syncModuleRoutes(router, data ?? [])
      loaded = true
    })
    .catch(() => {
      loading = null
    })

  return loading
}

// An address that matches no page may be a module's: the routes are fetched
// and the address tried again.
export function useModuleRoutes(router) {
  router.beforeEach(async (to) => {
    if (to.name !== 'not-found' || loaded) {
      return
    }

    await loadModuleRoutes(router)

    if (router.resolve(to.fullPath).name !== 'not-found') {
      return to.fullPath
    }
  })
}
