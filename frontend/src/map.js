// Shared Leaflet setup for the request maps: a free OpenFreeMap basemap (built
// on OpenStreetMap data, with Google Maps-like colours), priority-coloured pins
// and request popups.
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { maplibreGL } from '@maplibre/maplibre-gl-leaflet'
import { setWorkerUrl } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
// MapLibre finds its worker relative to its own file, which Vite's bundling
// breaks, so Vite builds the worker and MapLibre is told where it is.
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'
import { watch } from 'vue'
import { formatAddress, formatDate, priorityLabels, statusLabels, streetViewUrl } from '@/labels'
import { isDark } from '@/theme'

// Most requests are in Finland, so a map without pins starts there.
export const FINLAND_BOUNDS = [
  [59.7, 19.5],
  [70.1, 31.6],
]

// Zoom level for a single request: close enough to see the street.
export const STREET_ZOOM = 15

// OpenFreeMap's vector styles: "bright" looks like Google Maps (cream land,
// yellow roads, blue water) and "dark" suits the dark theme. Free, with no key
// or usage limits; MapLibre GL draws them inside Leaflet.
const STYLES = {
  light: 'https://tiles.openfreemap.org/styles/bright',
  dark: 'https://tiles.openfreemap.org/styles/dark',
}

// Without WebGL, MapLibre can't draw, so the map falls back to OpenStreetMap's own tiles.
const FALLBACK_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

const ATTRIBUTION =
  '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/" target="_blank" rel="noopener">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>'

function supportsWebGL() {
  if (typeof WebGLRenderingContext === 'undefined') {
    return false
  }

  try {
    const canvas = document.createElement('canvas')
    return Boolean(canvas.getContext('webgl2') || canvas.getContext('webgl'))
  } catch {
    return false
  }
}

// The basemap layer, restyled when the light/dark theme changes. Returns a function that stops following the theme.
function addBasemap(map) {
  if (!supportsWebGL()) {
    L.tileLayer(FALLBACK_TILES, {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map)

    return () => {}
  }

  setWorkerUrl(maplibreWorkerUrl)

  const basemap = maplibreGL({
    style: isDark.value ? STYLES.dark : STYLES.light,
    attribution: ATTRIBUTION,
  }).addTo(map)

  return watch(isDark, (dark) => basemap.getMaplibreMap().setStyle(dark ? STYLES.dark : STYLES.light))
}

// Creates a map with Finnish zoom buttons and tiles that follow the light/dark theme.
// Returns the map and a function that removes it (call it when the component unmounts).
export function createMap(element, { zoomPosition = 'topleft', ...options } = {}) {
  const map = L.map(element, { zoomControl: false, ...options })

  L.control.zoom({ position: zoomPosition, zoomInTitle: 'Lähennä', zoomOutTitle: 'Loitonna' }).addTo(map)

  const stopWatchingTheme = addBasemap(map)

  return {
    map,
    destroy() {
      stopWatchingTheme()
      map.remove()
    },
  }
}

// A teardrop pin. Leaflet's default marker image doesn't survive Vite's asset
// hashing, so pins are drawn with CSS (see .map-pin in main.css).
function pin(classes) {
  return L.divIcon({
    className: ['map-pin', ...classes].join(' '),
    // Leaflet positions the icon element with a transform, so the shape is drawn by an inner element.
    html: '<span class="map-pin-shape"></span>',
    iconSize: [30, 30],
    iconAnchor: [15, 30],
    popupAnchor: [0, -28],
  })
}

// A request's pin, coloured by priority; completed requests are faded.
export function requestPin(request) {
  return pin([`map-pin-${request.priority}`, ...(request.status === 'completed' ? ['map-pin-completed'] : [])])
}

// The pin of the location being chosen in the request form.
export function locationPin() {
  return pin(['map-pin-picker'])
}

function element(tag, className, text) {
  const node = document.createElement(tag)
  node.className = className

  if (text !== undefined) {
    // textContent, never innerHTML: titles and descriptions are user input.
    node.textContent = text
  }

  return node
}

function truncate(text, length) {
  return text.length > length ? `${text.slice(0, length - 1).trimEnd()}…` : text
}

// Popup content for a request: title, badges, description, address, due date,
// a "Näytä tiedot" link and a Street View link. `openDetails` is called instead of following the
// link, so the app navigates without a page reload (modifier clicks still open a new tab).
export function requestPopup(request, openDetails) {
  const popup = element('div', 'map-popup')

  popup.append(element('p', 'map-popup-title', request.title))

  const badges = element('p', 'map-popup-badges')
  badges.append(
    element('span', `badge priority-${request.priority}`, priorityLabels[request.priority]),
    element('span', `badge status-${request.status}`, statusLabels[request.status]),
  )
  popup.append(badges)

  if (request.description) {
    popup.append(element('p', 'map-popup-description', truncate(request.description, 160)))
  }

  const address = formatAddress(request)

  if (address) {
    popup.append(element('p', 'map-popup-meta', address))
  }

  if (request.due_date) {
    popup.append(element('p', 'map-popup-meta', `Määräpäivä ${formatDate(request.due_date)}`))
  }

  const links = element('p', 'map-popup-links')

  const link = element('a', 'map-popup-link', 'Näytä tiedot →')
  link.href = `/requests/${request.id}`
  link.addEventListener('click', (event) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
      return
    }

    event.preventDefault()
    openDetails(request)
  })

  const streetView = element('a', 'map-popup-link', 'Street View ↗')
  streetView.href = streetViewUrl(request)
  streetView.target = '_blank'
  streetView.rel = 'noopener'
  streetView.title = 'Avaa Google Street View uudessa välilehdessä'

  links.append(link, streetView)
  popup.append(links)

  return popup
}
