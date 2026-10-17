import { toCents } from '@/shared/lib/money'
import type { CartLine } from './cartStore'

export function lineTotalCents(line: Pick<CartLine, 'price' | 'quantity'>): bigint {
  return toCents(line.price) * BigInt(line.quantity)
}

export function cartSubtotalCents(lines: CartLine[]): bigint {
  return lines.reduce((total, line) => total + lineTotalCents(line), 0n)
}
