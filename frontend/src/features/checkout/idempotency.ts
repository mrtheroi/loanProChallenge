import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const ATTEMPT_PREFIX = 'loanpro-checkout-attempt:'

const memoryAttempts = new Map<string, string>()

/** Mirrors the backend's PaymentCard::brand() so both sides agree on request identity. */
function cardBrand(digits: string): string {
  if (digits.startsWith('4')) return 'visa'
  if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/.test(digits)) return 'mastercard'
  if (/^3[47]/.test(digits)) return 'amex'
  return 'unknown'
}

/**
 * Identifies a payment the way the backend's request hash does: the items with
 * their expected prices plus the card's brand, last four digits and expiry.
 * Formatting and the CVC do not change it.
 */
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
  try {
    return localStorage.getItem(ATTEMPT_PREFIX + slot) ?? memoryAttempts.get(slot)
  } catch {
    return memoryAttempts.get(slot)
  }
}

/** Runs the callback while holding a lock shared by every tab of this site, when the browser supports it. */
async function withCrossTabLock<T>(name: string, callback: () => T): Promise<T> {
  return 'locks' in navigator ? navigator.locks.request(name, async () => callback()) : callback()
}

/**
 * Returns the Idempotency-Key for a buyer's payment. A fresh random key starts
 * each attempt, so a repeat purchase from any device is a new order. While the
 * outcome is unknown the key is kept per buyer and payment (under a hash, never
 * card details) and is created inside a cross-tab lock, so tabs paying the same
 * thing converge on one key and a retry is replayed instead of charged again.
 */
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
      // The in-memory attempt still covers retries in this page.
    }

    return key
  })
}

/** Ends this payment's attempt once its outcome is known. */
export async function settlePayment(userId: number, fingerprint: string): Promise<void> {
  const slot = await slotFor(userId, fingerprint)
  memoryAttempts.delete(slot)
  try {
    localStorage.removeItem(ATTEMPT_PREFIX + slot)
  } catch {
    // Nothing else to forget.
  }
}

const OUTCOME_STATUSES = new Set([402, 409, 422])

/** Only answers produced by the checkout itself establish an attempt's outcome. */
export function settlesPayment(status: number): boolean {
  return OUTCOME_STATUSES.has(status)
}
