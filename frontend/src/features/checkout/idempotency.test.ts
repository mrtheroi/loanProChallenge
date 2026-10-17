import { describe, expect, it, vi } from 'vitest'
import { idempotencyKeyFor, paymentFingerprint, settlePayment } from './idempotency'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

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

describe('idempotency keys', () => {
  it('gives a repeat purchase on another device a fresh key instead of the old order key', async () => {
    const first = await idempotencyKeyFor(7, 'payment')
    expect(first).toMatch(UUID)
    localStorage.clear()
    vi.resetModules()
    const otherDevice = await import('./idempotency')

    expect(await otherDevice.idempotencyKeyFor(7, 'payment')).not.toBe(first)
  })

  it('creates a key inside a cross-tab lock named after the buyer and payment', async () => {
    const request = vi.fn((_name: string, callback: () => Promise<string>) => callback())
    Object.defineProperty(navigator, 'locks', { value: { request }, configurable: true })

    const key = await idempotencyKeyFor(7, 'payment-locked')

    expect(request).toHaveBeenCalledWith(expect.stringMatching(/^loanpro-checkout:7:[0-9a-f]{64}$/), expect.any(Function))
    expect(await idempotencyKeyFor(7, 'payment-locked')).toBe(key)
    Reflect.deleteProperty(navigator, 'locks')
  })

  it('scopes keys to the buyer and keeps them until the outcome is known', async () => {
    const first = await idempotencyKeyFor(7, 'payment')

    expect(await idempotencyKeyFor(8, 'payment')).not.toBe(first)
    expect(await idempotencyKeyFor(7, 'other payment')).not.toBe(first)
    expect(await idempotencyKeyFor(7, 'payment')).toBe(first)

    await settlePayment(7, 'payment')
    const next = await idempotencyKeyFor(7, 'payment')
    expect(next).not.toBe(first)
    expect(await idempotencyKeyFor(7, 'payment')).toBe(next)
  })

  it('keeps a shared key in every tab until that tab sees the outcome', async () => {
    const key = await idempotencyKeyFor(7, 'shared payment')
    vi.resetModules()
    const otherTab = await import('./idempotency')
    expect(await otherTab.idempotencyKeyFor(7, 'shared payment')).toBe(key)

    await settlePayment(7, 'shared payment')
    const next = await idempotencyKeyFor(7, 'shared payment')

    expect(next).not.toBe(key)
    expect(await otherTab.idempotencyKeyFor(7, 'shared payment')).toBe(key)
  })

  it('never stores card details', async () => {
    await idempotencyKeyFor(7, 'MUG-1|4242|12/2030')

    expect(JSON.stringify({ ...localStorage })).not.toContain('4242')
  })

  it('still settles within the page when storage cannot be written', async () => {
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError')
    })
    const first = await idempotencyKeyFor(9, 'fp-memory')

    await settlePayment(9, 'fp-memory')

    expect(await idempotencyKeyFor(9, 'fp-memory')).not.toBe(first)
    setItem.mockRestore()
  })
})
