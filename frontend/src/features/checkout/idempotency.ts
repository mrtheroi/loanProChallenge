import { useRef } from 'react'

/**
 * Keeps one Idempotency-Key per payment attempt: the same payload retried while
 * its outcome is unknown reuses the key, and a new attempt starts after the
 * server has answered or the payload changes.
 */
export function useIdempotencyKey() {
  const last = useRef<{ fingerprint: string; key: string } | null>(null)

  const keyFor = (fingerprint: string): string => {
    if (last.current?.fingerprint !== fingerprint) {
      last.current = { fingerprint, key: crypto.randomUUID() }
    }

    return last.current.key
  }

  const settle = () => {
    last.current = null
  }

  return { keyFor, settle }
}
