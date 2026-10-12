<script setup>
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue'
import L from 'leaflet'
import { createMap, FINLAND_BOUNDS, locationPin, STREET_ZOOM } from '@/map'

// Small map for choosing a request's location: click the map or drag the pin.
const props = defineProps({
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
})

const emit = defineEmits(['pick'])

const container = useTemplateRef('container')
let map = null
let destroy = null
let marker = null
// The last point chosen on this map, so the view only jumps for coordinates set elsewhere.
let picked = null

function coordinates() {
  const latitude = Number.parseFloat(props.latitude)
  const longitude = Number.parseFloat(props.longitude)

  return Number.isFinite(latitude) && Number.isFinite(longitude) ? [latitude, longitude] : null
}

function pick({ lat, lng }) {
  // Seven decimals (about 1 cm) is what the API stores.
  picked = [Number(lat.toFixed(7)), Number(lng.toFixed(7))]
  emit('pick', { latitude: picked[0], longitude: picked[1] })
}

// Moves the pin to the current coordinates, or removes it when there are none.
function showPin() {
  const point = coordinates()

  if (!point) {
    marker?.remove()
    marker = null
    return
  }

  if (!marker) {
    marker = L.marker(point, { icon: locationPin(), draggable: true, title: 'Pyynnön sijainti', autoPan: true }).addTo(map)
    marker.on('dragend', () => pick(marker.getLatLng()))
  } else {
    marker.setLatLng(point)
  }

  // Coordinates from an address suggestion, the address lookup or typed in by
  // hand zoom the map to the street; a click or drag here keeps the view.
  const pickedHere = picked && picked[0] === point[0] && picked[1] === point[1]

  if (!pickedHere) {
    map.setView(point, Math.max(map.getZoom(), STREET_ZOOM))
  }
}

onMounted(() => {
  ;({ map, destroy } = createMap(container.value, { scrollWheelZoom: false }))

  const point = coordinates()

  if (point) {
    map.setView(point, STREET_ZOOM)
  } else {
    map.fitBounds(FINLAND_BOUNDS)
  }

  showPin()
  map.on('click', (event) => pick(event.latlng))
})

watch(() => [props.latitude, props.longitude], showPin)

onBeforeUnmount(() => destroy?.())
</script>

<template>
  <div>
    <div
      ref="container"
      class="h-64 overflow-hidden rounded-lg border border-slate-300 sm:h-72 dark:border-slate-700"
      role="application"
      aria-label="Sijaintikartta"
    ></div>
    <p class="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
      Napsauta karttaa tai vedä merkkiä asettaaksesi sijainnin.
    </p>
  </div>
</template>
