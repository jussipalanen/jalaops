// Finnish display labels for the API's priority and status values.
export const priorityLabels = {
  low: 'Matala',
  normal: 'Normaali',
  high: 'Korkea',
}

export const statusLabels = {
  open: 'Avoin',
  in_progress: 'Työn alla',
  completed: 'Valmis',
}

// Due date filter choices; "range" shows date fields for a custom period.
export const duePeriodLabels = {
  overdue: 'Myöhässä',
  today: 'Tänään',
  week: 'Seuraavat 7 päivää',
  month: 'Seuraavat 30 päivää',
  none: 'Ei määräpäivää',
  range: 'Aikaväli…',
}

// Formats an ISO date (YYYY-MM-DD) as a Finnish date, e.g. 15.10.2026.
export function formatDate(isoDate) {
  if (!isoDate) {
    return '–'
  }

  const [year, month, day] = isoDate.split('-').map(Number)

  return `${day}.${month}.${year}`
}

// "Hämeenkatu 10, 33100 Tampere", leaving out the parts that are missing.
export function formatAddress(request) {
  const city = [request.postal_code, request.city].filter(Boolean).join(' ')

  return [request.street_address, city].filter(Boolean).join(', ')
}

// Whether a request has map coordinates.
export function hasLocation(request) {
  return request.latitude != null && request.longitude != null
}

// Google Street View at a request's coordinates: a free Maps URL that needs no API key.
export function streetViewUrl(request) {
  return `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${request.latitude},${request.longitude}`
}

// A request is new for 7 days after it was created, until it's completed.
export const NEW_REQUEST_DAYS = 7

export function isNew(request, now = Date.now()) {
  if (!request.created_at || request.status === 'completed') {
    return false
  }

  return now - new Date(request.created_at).getTime() < NEW_REQUEST_DAYS * 24 * 60 * 60 * 1000
}

// Work and material rows on a request.
export const itemTypeLabels = {
  work: 'Työ',
  material: 'Tarvike',
}

const euroFormat = new Intl.NumberFormat('fi-FI', { style: 'currency', currency: 'EUR' })
const numberFormat = new Intl.NumberFormat('fi-FI', { maximumFractionDigits: 2 })

// 122.36 → "122,36 €"
export function formatEuro(amount) {
  return euroFormat.format(amount ?? 0)
}

// 1.5 → "1,5"
export function formatNumber(value) {
  return numberFormat.format(value ?? 0)
}

// "1,5" or "1.5" → 1.5 (Finnish users type a decimal comma); "" stays "".
export function parseDecimal(text) {
  const normalised = String(text ?? '').trim().replace(/\s/g, '').replace(',', '.')
  return normalised === '' ? '' : Number(normalised)
}
