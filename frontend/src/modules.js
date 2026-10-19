import { ApiError, apiPost } from '@/api/client'

// Python modules on the backend (backend/modules).

// Runs a module with an input object and returns its output.
export async function runModule(id, input) {
  return (await apiPost(`/modules/${encodeURIComponent(id)}/run`, input)).output
}

// A Finnish message for a failed run: the API's message and the module's own reason.
export function moduleErrorMessage(exception) {
  if (!(exception instanceof ApiError)) {
    return 'Moduulin suoritus epäonnistui. Yritä hetken kuluttua uudelleen.'
  }

  if (exception.status === 429) {
    return 'Moduulia on käytetty liian usein. Yritä hetken kuluttua uudelleen.'
  }

  const { message, reason } = exception.data

  return [message, reason].filter(Boolean).join(' ') || 'Moduulin suoritus epäonnistui.'
}

// A module page's address parameters (?news=3) as a plain object of short
// texts with simple names, for the module's view and back from it.
export function moduleParams(query) {
  const params = {}

  if (!query || typeof query !== 'object') {
    return params
  }

  for (const [name, value] of Object.entries(query)) {
    if (/^[a-z][a-z0-9_]*$/.test(name) && (typeof value === 'string' || typeof value === 'number')) {
      params[name] = String(value).slice(0, 200)
    }
  }

  return params
}

// The parameter names in a route's path: /news/:news/edit → ['news'].
function pathNames(path) {
  return [...path.matchAll(/:([a-z][a-z0-9_]*)/g)].map((match) => match[1])
}

// The address of a module's page for its view's parameters. A module's
// routes (module.json) give its pages addresses of their own: the route
// whose fixed params match and whose path takes the most of the rest wins,
// and what's left goes in the query ({ news: '3', view: 'edit' } →
// /news/3/edit, { tag: 'huolto' } → /news?tag=huolto). Without a route that
// fits, the page is /tools/modules/<id> with everything in the query.
export function moduleLocation(module, params = {}) {
  const values = moduleParams(params)
  let best = null

  for (const route of module.routes ?? []) {
    const fixed = Object.entries(route.params ?? {})
    const names = pathNames(route.path)

    if (fixed.every(([name, value]) => values[name] === value) && names.every((name) => values[name])) {
      const score = fixed.length + names.length
      if (!best || score > best.score) best = { route, names, score }
    }
  }

  if (!best) {
    return { path: `/tools/modules/${encodeURIComponent(module.id)}`, query: values }
  }

  const used = [...Object.keys(best.route.params ?? {}), ...best.names]
  const query = Object.fromEntries(Object.entries(values).filter(([name]) => !used.includes(name)))
  const path = best.route.path.replace(/:([a-z][a-z0-9_]*)/g, (match, name) => encodeURIComponent(values[name]))

  return { path, query }
}

// A module page's parameters for its view: the query's, and on a module's
// own route also the route's fixed params and the path's (/news/3/edit →
// { news: '3', view: 'edit' }).
export function pageParams(route) {
  if (!route.meta?.module) {
    return moduleParams(route.query)
  }

  return { ...moduleParams(route.query), ...moduleParams(route.meta.moduleParams), ...moduleParams(route.params) }
}
