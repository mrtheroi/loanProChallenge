import { Link } from 'react-router'
import { formatCents, toCents } from '@/shared/lib/money'
import { Button } from '@/shared/ui/button'
import { useCartStore } from '../cartStore'
import { QuantityInput } from '../components/QuantityInput'
import { maxQuantity } from '../limits'
import { cartSubtotalCents, lineTotalCents } from '../totals'

export function CartContainer() {
  const items = useCartStore((state) => state.items)
  const setQuantity = useCartStore((state) => state.setQuantity)
  const remove = useCartStore((state) => state.remove)
  const lines = Object.values(items)

  if (lines.length === 0) {
    return (
      <section className="flex flex-col items-start gap-4">
        <h1 className="font-heading text-2xl font-semibold">Cart</h1>
        <p>Your cart is empty.</p>
        <Button asChild>
          <Link to="/">Browse products</Link>
        </Button>
      </section>
    )
  }

  const subtotal = cartSubtotalCents(lines)

  return (
    <section className="flex flex-col gap-6">
      <h1 className="font-heading text-2xl font-semibold">Cart</h1>
      <ul className="flex flex-col divide-y rounded-lg border">
        {lines.map((line) => (
          <li key={line.sku} aria-label={line.name} className="flex flex-wrap items-center justify-between gap-4 p-4">
            <div>
              <p className="font-medium">{line.name}</p>
              <p className="text-sm text-muted-foreground">{formatCents(toCents(line.price))} each</p>
            </div>
            <div className="flex items-center gap-3">
              <label className="flex items-center gap-2 text-sm">
                Quantity
                <QuantityInput value={line.quantity} max={maxQuantity(line.stock)} onCommit={(quantity) => void setQuantity(line.sku, quantity)} />
              </label>
              <span className="w-24 text-right font-medium">{formatCents(lineTotalCents(line))}</span>
              <Button variant="ghost" onClick={() => remove(line.sku)}>
                Remove
              </Button>
            </div>
          </li>
        ))}
      </ul>
      <div className="flex flex-col items-end gap-2">
        <p className="text-lg font-semibold">Subtotal: {formatCents(subtotal)}</p>
        <p className="text-sm text-muted-foreground">Prices and stock are confirmed when you check out.</p>
        <Button asChild>
          <Link to="/checkout">Checkout</Link>
        </Button>
      </div>
    </section>
  )
}
