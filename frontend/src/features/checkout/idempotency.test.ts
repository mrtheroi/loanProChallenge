import { afterEach, describe, expect, it, vi } from 'vitest'
import { idempotencyKeyFor, paymentFingerprint, settlePayment } from './idempotency'

const payload = (number: string, exp_month = 12) => ({
  items: [{ sku: 'MUG-1', quantity: 2, unit_price: '16.99' }],
  card: { number, exp_month, exp_year: 2030, cvc: '123' },
})

describe('payment fingerprint', () => {
  it('ignores formatting and the CVC but tells brands, expiry and prices apart', () => {
    const visa = paymentFingerprint(payload('4000 0000 0000 4242'))

    expect(paymentFingerprint({ ...payload('4000-0000-0000-4242 '), card: { ...payload('4000-0000-0000-4242 ').card, cvc: '999' } })).toBe(visa)
    expect(paymentFingerprint(payload('5555 5555 5555 4242'))).not.toBe(visa)
    expect(paymentFingerprint(payload('4000 0000 0000 4242', 11))).not.toBe(visa)
    expect(paymentFingerprint({ ...payload('4000 0000 0000 4242'), items: [{ sku: 'MUG-1', quantity: 2, unit_price: '17.00' }] })).not.toBe(visa)
  })
})

describe('pending attempts', () => {
  afterEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
  })

  it('keeps one attempt per payment so another tab cannot overwrite it', async () => {
    const first = await idempotencyKeyFor('payment-a')
    const second = await idempotencyKeyFor('payment-b')

    expect(await idempotencyKeyFor('payment-a')).toBe(first)
    expect(await idempotencyKeyFor('payment-b')).toBe(second)

    await settlePayment('payment-a')
    expect(await idempotencyKeyFor('payment-a')).not.toBe(first)
    expect(await idempotencyKeyFor('payment-b')).toBe(second)
  })

  it('stores only hashed fingerprints and is read by a fresh tab', async () => {
    const key = await idempotencyKeyFor('MUG-1|4242|12/2030')

    expect(Object.keys(localStorage).join(' ')).not.toContain('4242')
    expect(Object.keys(localStorage).every((key) => key.startsWith('loanpro-checkout-attempt:'))).toBe(true)
    vi.resetModules()
    const freshTab = await import('./idempotency')
    expect(await freshTab.idempotencyKeyFor('MUG-1|4242|12/2030')).toBe(key)
  })

  it('keeps the key in memory when storage cannot be written', async () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError')
    })

    const key = await idempotencyKeyFor('fp-memory')

    expect(await idempotencyKeyFor('fp-memory')).toBe(key)
  })
})

describe('forgetting attempts', () => {
  it('removes every attempt, including entries written by older versions', async () => {
    const { forgetAllPayments } = await import('./idempotency')
    await idempotencyKeyFor('payment-a')
    localStorage.setItem('loanpro-checkout-attempt', JSON.stringify({ fingerprint: 'old', key: 'k' }))
    localStorage.setItem('loanpro-cart', '{}')

    forgetAllPayments()

    expect(Object.keys(localStorage)).toEqual(['loanpro-cart'])
  })
})
