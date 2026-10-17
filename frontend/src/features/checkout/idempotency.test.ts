import { afterEach, describe, expect, it, vi } from 'vitest'
import { idempotencyKeyFor, paymentFingerprint, settlePayment } from './idempotency'

const payload = (number: string, exp_month = 12) => ({
  items: [{ sku: 'MUG-1', quantity: 2 }],
  card: { number, exp_month, exp_year: 2030, cvc: '123' },
})

describe('payment fingerprint', () => {
  it('ignores formatting and the CVC but tells brands apart, like the backend request identity', () => {
    const visa = paymentFingerprint(payload('4000 0000 0000 4242'))

    expect(paymentFingerprint({ ...payload('4000-0000-0000-4242 '), card: { ...payload('').card, number: '4000-0000-0000-4242 ', cvc: '999' } })).toBe(visa)
    expect(paymentFingerprint(payload('5555 5555 5555 4242'))).not.toBe(visa)
    expect(paymentFingerprint(payload('4000 0000 0000 4242', 11))).not.toBe(visa)
  })
})

describe('idempotency key storage', () => {
  afterEach(() => settlePayment())

  it('shares the pending key across tabs through localStorage', () => {
    const key = idempotencyKeyFor('fp')

    expect(JSON.parse(localStorage.getItem('loanpro-checkout-attempt') ?? '{}')).toEqual({ fingerprint: 'fp', key })
    sessionStorage.clear()
    expect(idempotencyKeyFor('fp')).toBe(key)
  })

  it('keeps the key in memory when storage cannot be written', () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError')
    })

    const key = idempotencyKeyFor('fp-memory')

    expect(idempotencyKeyFor('fp-memory')).toBe(key)
    setItem.mockRestore()
  })
})
