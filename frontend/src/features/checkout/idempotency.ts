import type { CheckoutPayload } from '@/features/orders/api/ordersApi'

const STORAGE_KEY = 'loanpro-checkout-attempt'

type Attempt = { fingerprint: string; key: string }

/**
 * Identifies a payment the way the backend does: the items plus the card's
 * last four digits and expiry. Formatting and fields the backend ignores for
 * request identity (spaces, the CVC) do not change it, and no secret is kept.
 */
export function paymentFingerprint(payload: CheckoutPayload): string {
  const items = [...payload.items].sort((a, b) => (a.sku < b.sku ? -1 : a.sku > b.sku ? 1 : 0)).map((item) => [item.sku, item.quantity])
  const digits = payload.card.number.replace(/\D/g, '')

  return JSON.stringify({ items, card: [digits.slice(-4), payload.card.exp_month, payload.card.exp_year] })
}

function readAttempt(): Attempt | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    const attempt = raw === null ? null : (JSON.parse(raw) as Partial<Attempt>)

    return typeof attempt?.fingerprint === 'string' && typeof attempt.key === 'string' ? { fingerprint: attempt.fingerprint, key: attempt.key } : null
  } catch {
    return null
  }
}

/**
 * Returns the Idempotency-Key for a payment. The key survives reloads while the
 * outcome is unknown, so a retry of the same payment is replayed by the backend
 * instead of charged again.
 */
export function idempotencyKeyFor(fingerprint: string): string {
  const pending = readAttempt()
  if (pending?.fingerprint === fingerprint) return pending.key

  const attempt = { fingerprint, key: crypto.randomUUID() }
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(attempt))
  } catch {
    // Without storage the key still covers retries within this page.
  }

  return attempt.key
}

/** Forgets the pending key once the backend has given a definitive answer. */
export function settlePayment(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // Nothing to forget when storage is unavailable.
  }
}
