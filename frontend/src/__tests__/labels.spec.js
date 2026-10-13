import { describe, expect, it } from 'vitest'
import { formatDate, formatEuro, formatNumber, isNew, parseDecimal } from '../labels'

describe('formatDate', () => {
  it('formats an ISO date as a Finnish date', () => {
    expect(formatDate('2026-10-05')).toBe('5.10.2026')
  })

  it('shows a dash when there is no date', () => {
    expect(formatDate(null)).toBe('–')
  })
})

describe('isNew', () => {
  const now = new Date('2026-10-12T12:00:00Z').getTime()

  it('is new for 7 days after creation until completed', () => {
    expect(isNew({ status: 'open', created_at: '2026-10-12T08:00:00Z' }, now)).toBe(true)
    expect(isNew({ status: 'in_progress', created_at: '2026-10-05T13:00:00Z' }, now)).toBe(true)
    expect(isNew({ status: 'open', created_at: '2026-10-05T11:00:00Z' }, now)).toBe(false)
    expect(isNew({ status: 'completed', created_at: '2026-10-12T08:00:00Z' }, now)).toBe(false)
    expect(isNew({ status: 'open' }, now)).toBe(false)
  })
})

describe('money and numbers', () => {
  const plain = (value) => value.replace(/\u00a0|\u202f/g, ' ')

  it('formats euros and numbers the Finnish way', () => {
    expect(plain(formatEuro(1234.5))).toBe('1 234,50 €')
    expect(formatNumber(1.5)).toBe('1,5')
    expect(formatNumber(25.5)).toBe('25,5')
  })

  it('reads numbers typed with a decimal comma', () => {
    expect(parseDecimal('1,5')).toBe(1.5)
    expect(parseDecimal(' 65.00 ')).toBe(65)
    expect(parseDecimal('1 234,5')).toBe(1234.5)
    expect(parseDecimal('')).toBe('')
  })
})

