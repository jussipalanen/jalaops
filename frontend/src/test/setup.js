import { beforeEach, vi } from 'vitest'
import { resetLeaflet } from './leaflet'

// Every test gets the Leaflet stand-in instead of the real library. jsdom has
// no WebGL, so the maps use the plain tile fallback and MapLibre isn't needed.
vi.mock('leaflet', () => import('./leaflet'))
vi.mock('@maplibre/maplibre-gl-leaflet', () => ({ maplibreGL: vi.fn() }))
vi.mock('maplibre-gl', () => ({ setWorkerUrl: vi.fn() }))
vi.mock('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url', () => ({ default: '/maplibre-worker.js' }))

beforeEach(resetLeaflet)
