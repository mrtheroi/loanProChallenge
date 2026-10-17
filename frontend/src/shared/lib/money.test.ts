import { describe, expect, it } from 'vitest'
import { formatCents, toCents } from './money'

describe('money', () => {
  it.each([
    ['16.99', 1699],
    ['0', 0],
    ['5.5', 550],
    ['92233720368547758.07', 9223372036854775807n],
  ])('converts %s to integer cents', (price, cents) => {
    expect(toCents(price)).toBe(BigInt(cents))
  })

  it('formats cents with two decimals and a dollar sign', () => {
    expect(formatCents(5097n)).toBe('$50.97')
    expect(formatCents(5n)).toBe('$0.05')
    expect(formatCents(123456789n)).toBe('$1,234,567.89')
  })

  it('adds prices exactly where floating point would not', () => {
    expect(formatCents(toCents('0.10') + toCents('0.20'))).toBe('$0.30')
    expect(formatCents(toCents('16.99') * 3n)).toBe('$50.97')
  })
})
