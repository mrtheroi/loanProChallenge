const PRICE = /^(\d+)(?:\.(\d{1,2}))?$/

export function toCents(price: string): bigint {
  const match = PRICE.exec(price)
  if (match === null) throw new Error(`Invalid price: ${price}`)

  const [, whole = '0', fraction = ''] = match

  return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))
}

export function formatCents(cents: bigint): string {
  const whole = cents / 100n
  const fraction = (cents % 100n).toString().padStart(2, '0')

  return `$${whole.toLocaleString('en-US')}.${fraction}`
}
