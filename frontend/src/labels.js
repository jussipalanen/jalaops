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
