import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const ATTEMPT_PREFIX = 'loanpro-checkout-attempt:'

const memoryAttempts = new Map<string, string>()

function cardBrand(digits: string): string {
  if (digits.startsWith('4')) return 'visa'
  if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/.test(digits)) return 'mastercard'
  if (/^3[47]/.test(digits)) return 'amex'
  return 'unknown'
}

export function paymentFingerprint(payload: CheckoutPayload): string {
  const items = [...payload.items]
    .sort((a, b) => (a.sku < b.sku ? -1 : a.sku > b.sku ? 1 : 0))
    .map((item) => [item.sku, item.quantity, item.unit_price])
  const digits = payload.card.number.replace(/\D/g, '')

  return JSON.stringify({ items, card: [cardBrand(digits), digits.slice(-4), payload.card.exp_month, payload.card.exp_year] })
}

async function sha256(text: string): Promise<Uint8Array> {
  return new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text)))
}

function hex(bytes: Uint8Array): string {
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('')
}

async function slotFor(userId: number, fingerprint: string): Promise<string> {
  return `${userId}:${hex(await sha256(fingerprint))}`
}

function readAttempt(slot: string): string | undefined {
  const own = memoryAttempts.get(slot)
  if (own !== undefined) return own

  try {
    const shared = localStorage.getItem(ATTEMPT_PREFIX + slot) ?? undefined
    if (shared !== undefined) memoryAttempts.set(slot, shared)
    return shared
  } catch {
    return undefined
  }
}

export class CheckoutUnavailable extends Error {
  constructor() {
    super('This browser cannot place orders safely. Please update it and try again.')
  }
}

async function withCrossTabLock<T>(name: string, callback: () => T): Promise<T> {
  if (!('locks' in navigator)) throw new CheckoutUnavailable()

  return navigator.locks.request(name, async () => callback())
}

export async function idempotencyKeyFor(userId: number, fingerprint: string): Promise<string> {
  const slot = await slotFor(userId, fingerprint)

  return withCrossTabLock(`loanpro-checkout:${slot}`, () => {
    const existing = readAttempt(slot)
    if (existing !== undefined) return existing

    const key = crypto.randomUUID()
    memoryAttempts.set(slot, key)
    try {
      localStorage.setItem(ATTEMPT_PREFIX + slot, key)
    } catch {
    }

    return key
  })
}

export async function settlePayment(userId: number, fingerprint: string, key: string): Promise<void> {
  const slot = await slotFor(userId, fingerprint)

  await withCrossTabLock(`loanpro-checkout:${slot}`, () => {
    if (memoryAttempts.get(slot) === key) memoryAttempts.delete(slot)
    try {
      if (localStorage.getItem(ATTEMPT_PREFIX + slot) === key) localStorage.removeItem(ATTEMPT_PREFIX + slot)
    } catch {
    }
  })
}

const OUTCOME_STATUSES = new Set([402, 409, 422])

export function settlesPayment(status: number): boolean {
  return OUTCOME_STATUSES.has(status)
}
