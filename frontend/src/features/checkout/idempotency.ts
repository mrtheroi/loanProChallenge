import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const STORAGE_KEY = 'loanpro-checkout-attempt'

type Attempt = { fingerprint: string; key: string }

let memoryAttempt: Attempt | null = null

/** Mirrors the backend's PaymentCard::brand() so both sides agree on request identity. */
function cardBrand(digits: string): string {
  if (digits.startsWith('4')) return 'visa'
  if (/^(5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d{2}|27[01]\d|2720)/.test(digits)) return 'mastercard'
  if (/^3[47]/.test(digits)) return 'amex'
  return 'unknown'
}

/**
 * Identifies a payment the way the backend's request hash does: the items plus
 * the card's brand, last four digits and expiry. Formatting and the CVC do not
 * change it, and no secret is kept.
 */
export function paymentFingerprint(payload: CheckoutPayload): string {
  const items = [...payload.items].sort((a, b) => (a.sku < b.sku ? -1 : a.sku > b.sku ? 1 : 0)).map((item) => [item.sku, item.quantity])
  const digits = payload.card.number.replace(/\D/g, '')

  return JSON.stringify({ items, card: [cardBrand(digits), digits.slice(-4), payload.card.exp_month, payload.card.exp_year] })
}

function readAttempt(): Attempt | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const attempt = raw === null ? null : (JSON.parse(raw) as Partial<Attempt>)

    if (typeof attempt?.fingerprint === 'string' && typeof attempt.key === 'string') return { fingerprint: attempt.fingerprint, key: attempt.key }
  } catch {
    // Fall back to the in-memory attempt below.
  }

  return memoryAttempt
}

/**
 * Returns the Idempotency-Key for a payment. While the outcome is unknown the
 * key is shared by every tab (localStorage, like the cart) and kept in memory
 * when storage fails, so a retry of the same payment is replayed instead of
 * charged again.
 */
export function idempotencyKeyFor(fingerprint: string): string {
  const pending = readAttempt()
  if (pending?.fingerprint === fingerprint) return pending.key

  const attempt = { fingerprint, key: crypto.randomUUID() }
  memoryAttempt = attempt
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(attempt))
  } catch {
    // The in-memory attempt still covers retries in this page.
  }

  return attempt.key
}

/** Forgets the pending attempt once its outcome is known. */
export function settlePayment(): void {
  memoryAttempt = null
  try {
    localStorage.removeItem(STORAGE_KEY)
  } catch {
    // Nothing else to forget.
  }
}

const OUTCOME_STATUSES = new Set([402, 409, 422])

/** Only answers produced by the checkout itself establish an attempt's outcome. */
export function settlesPayment(status: number): boolean {
  return OUTCOME_STATUSES.has(status)
}
