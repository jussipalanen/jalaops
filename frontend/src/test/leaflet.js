// A small stand-in for Leaflet in tests: jsdom has no layout, so real maps
// can't render. It records what the app does (views, pins, popups) so tests can
// check it. src/test/setup.js swaps it in for every test.

export const created = { maps: [], markers: [] }

export function resetLeaflet() {
  created.maps.length = 0
  created.markers.length = 0
}

function events(target) {
  target.handlers = {}
  target.on = (name, handler) => {
    ;(target.handlers[name] ??= []).push(handler)
    return target
  }
  target.once = (name, handler) => {
    const wrapper = (event) => {
      target.handlers[name] = target.handlers[name].filter((item) => item !== wrapper)
      handler(event)
    }
    return target.on(name, wrapper)
  }
  // Tests call fire() to simulate clicks, drags and finished animations.
  target.fire = (name, event = {}) => {
    for (const handler of target.handlers[name] ?? []) {
      handler(event)
    }
    return target
  }
  return target
}

function map(element, options) {
  const instance = events({
    element,
    options,
    layers: [],
    view: null,
    bounds: null,
    removed: false,
    attributionControl: { setPrefix() {} },
    setView(center, zoom) {
      instance.view = { center, zoom }
      return instance
    },
    fitBounds(bounds) {
      instance.bounds = bounds
      return instance
    },
    flyTo(center, zoom) {
      instance.view = { center, zoom }
      return instance
    },
    getZoom: () => instance.view?.zoom ?? 5,
    getBounds: () => ({ contains: () => false }),
    addLayer(layer) {
      instance.layers.push(layer)
      return instance
    },
    remove() {
      instance.removed = true
    },
  })
  created.maps.push(instance)
  return instance
}

function marker(latlng, options = {}) {
  const instance = events({
    latlng,
    options,
    popup: null,
    popupOpen: false,
    removed: false,
    addTo(target) {
      target.addLayer(instance)
      return instance
    },
    bindPopup(content) {
      instance.popup = content
      return instance
    },
    // Renders the popup content, as Leaflet does when a popup opens.
    openPopup() {
      instance.popupOpen = true
      instance.popupElement = typeof instance.popup === 'function' ? instance.popup(instance) : instance.popup
      return instance
    },
    getLatLng: () => ({ lat: instance.latlng[0], lng: instance.latlng[1] }),
    setLatLng(point) {
      instance.latlng = point
      return instance
    },
    remove() {
      instance.removed = true
    },
  })
  created.markers.push(instance)
  return instance
}

function layerGroup() {
  const instance = {
    layers: [],
    addTo: () => instance,
    addLayer(layer) {
      instance.layers.push(layer)
      return instance
    },
    clearLayers() {
      instance.layers = []
      return instance
    },
  }
  return instance
}

const L = {
  map,
  marker,
  layerGroup,
  tileLayer: (url, options) => {
    const layer = { url, options, addTo: () => layer, setUrl: (next) => (layer.url = next) }
    return layer
  },
  control: { zoom: (options) => ({ options, addTo() {} }) },
  divIcon: (options) => options,
  latLngBounds: (points) => ({ points }),
  // Runs at once, so a map removed in the next frame is removed when the component unmounts.
  Util: { requestAnimFrame: (callback) => callback() },
}

export default L
