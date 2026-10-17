import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const ATTEMPT_PREFIX = 'loanpro-checkout-attempt:'
const LEGACY_KEYS = ['loanpro-checkout-attempt', 'loanpro-checkout-attempts']

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

async function hash(fingerprint: string): Promise<string> {
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(fingerprint))

  return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('')
}

function readAttempt(id: string): string | undefined {
  try {
    return localStorage.getItem(ATTEMPT_PREFIX + id) ?? memoryAttempts.get(id)
  } catch {
    return memoryAttempts.get(id)
  }
}

/**
 * Returns the Idempotency-Key for a payment. Each unresolved payment keeps its
 * own key under its own storage entry (named by a hash, never the card
 * details), so tabs never overwrite each other's attempts, and the key is kept
 * in memory when storage fails. A retry of the same payment is therefore
 * replayed by the backend instead of charged again.
 */
export async function idempotencyKeyFor(fingerprint: string): Promise<string> {
  const id = await hash(fingerprint)
  const existing = readAttempt(id)
  if (existing !== undefined) return existing

  const key = crypto.randomUUID()
  memoryAttempts.set(id, key)
  try {
    localStorage.setItem(ATTEMPT_PREFIX + id, key)
  } catch {
    // The in-memory attempt still covers retries in this page.
  }

  return key
}

/** Forgets one payment's attempt once its outcome is known. */
export async function settlePayment(fingerprint: string): Promise<void> {
  const id = await hash(fingerprint)
  memoryAttempts.delete(id)
  try {
    localStorage.removeItem(ATTEMPT_PREFIX + id)
  } catch {
    // Nothing else to forget.
  }
}

/** Forgets every pending attempt, for example on logout. */
export function forgetAllPayments(): void {
  memoryAttempts.clear()
  try {
    const keys = Array.from({ length: localStorage.length }, (_, index) => localStorage.key(index))
    for (const key of keys) {
      if (key !== null && (key.startsWith(ATTEMPT_PREFIX) || LEGACY_KEYS.includes(key))) localStorage.removeItem(key)
    }
  } catch {
    // Nothing else to forget.
  }
}

const OUTCOME_STATUSES = new Set([402, 409, 422])

/** Only answers produced by the checkout itself establish an attempt's outcome. */
export function settlesPayment(status: number): boolean {
  return OUTCOME_STATUSES.has(status)
}
