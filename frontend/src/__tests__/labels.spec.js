import { describe, expect, it } from 'vitest'
import { formatDate } from '../labels'

describe('formatDate', () => {
  it('formats an ISO date as a Finnish date', () => {
    expect(formatDate('2026-10-05')).toBe('5.10.2026')
  })

  it('shows a dash when there is no date', () => {
    expect(formatDate(null)).toBe('–')
  })
})
