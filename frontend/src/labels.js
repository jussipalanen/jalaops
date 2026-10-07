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

// Formats an ISO date (YYYY-MM-DD) as a Finnish date, e.g. 15.10.2026.
export function formatDate(isoDate) {
  if (!isoDate) {
    return '–'
  }

  const [year, month, day] = isoDate.split('-').map(Number)

  return `${day}.${month}.${year}`
}
