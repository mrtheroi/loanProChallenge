import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks/useAuth'
import { useCartStore } from '@/features/cart/cartStore'
import { cartSubtotalCents, lineTotalCents } from '@/features/cart/totals'
import { orderQueryKey, placeOrder, type CheckoutPayload, type PriceChange, type Unavailable } from '@/features/orders/api/ordersApi'
import { ApiError } from '@/shared/api/http'
import { formatCents, toCents } from '@/shared/lib/money'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { CardForm, type Card } from '../components/CardForm'
import { idempotencyKeyFor, paymentFingerprint, settlePayment, settlesPayment } from '../idempotency'

type Attempt = { userId: number; fingerprint: string; payload: CheckoutPayload }

type Problem = { message: string; unavailable: Unavailable[]; priceChanges: PriceChange[] }

function problemFrom(error: unknown): Problem {
  if (!(error instanceof ApiError)) return { message: 'We could not reach the server. Check your connection and try again.', unavailable: [], priceChanges: [] }

  const body = error.body as { unavailable?: Unavailable[]; price_changes?: PriceChange[] } | null
  const fieldMessage = Object.values(error.errors)[0]?.[0]
  const conflict = error.status === 409

  return {
    message: fieldMessage ?? error.message,
    unavailable: conflict ? (body?.unavailable ?? []) : [],
    priceChanges: conflict ? (body?.price_changes ?? []) : [],
  }
}

export function CheckoutContainer() {
  const items = useCartStore((state) => state.items)
  const clear = useCartStore((state) => state.clear)
  const limit = useCartStore((state) => state.limit)
  const reprice = useCartStore((state) => state.reprice)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const [problem, setProblem] = useState<Problem | null>(null)
  const [placedOrderId, setPlacedOrderId] = useState<number | null>(null)

  const checkout = useMutation({
    mutationFn: async ({ userId, fingerprint, payload }: Attempt) => placeOrder(await idempotencyKeyFor(userId, fingerprint), payload),
    onSuccess: async (order, { userId, fingerprint }) => {
      setPlacedOrderId(order.id)
      queryClient.setQueryData(orderQueryKey(order.id), order)
      await navigate(`/orders/${order.id}`, { state: { placed: true } })
      clear()
      await settlePayment(userId, fingerprint)
    },
    onError: async (error, { userId, fingerprint }) => {
      const found = problemFrom(error)
      found.priceChanges.forEach((change) => reprice(change.sku, change.current))
      setProblem(found)
      if (error instanceof ApiError && settlesPayment(error.status)) await settlePayment(userId, fingerprint)
    },
  })

  const lines = Object.values(items)

  if (placedOrderId !== null) {
    return <p className="text-muted-foreground">Order #{placedOrderId} placed. Opening your receipt…</p>
  }

  if (lines.length === 0) {
    return (
      <section className="flex flex-col items-start gap-4">
        <h1 className="font-heading text-2xl font-semibold">Checkout</h1>
        <p>Your cart is empty.</p>
        <Button asChild>
          <Link to="/">Browse products</Link>
        </Button>
      </section>
    )
  }

  const total = formatCents(cartSubtotalCents(lines))

  const pay = (card: Card) => {
    const payload: CheckoutPayload = { items: lines.map((line) => ({ sku: line.sku, quantity: line.quantity, unit_price: line.price })), card }
    if (user === null || user === undefined) return
    setProblem(null)
    checkout.mutate({ userId: user.id, fingerprint: paymentFingerprint(payload), payload })
  }

  return (
    <section className="grid gap-8 md:grid-cols-2">
      <div className="flex flex-col gap-3">
        <h1 className="font-heading text-2xl font-semibold">Checkout</h1>
        <ul className="divide-y rounded-lg border">
          {lines.map((line) => (
            <li key={line.sku} className="flex justify-between p-3 text-sm">
              <span>
                {line.quantity} × {line.name}
              </span>
              <span>{formatCents(lineTotalCents(line))}</span>
            </li>
          ))}
        </ul>
        <p className="font-semibold">Total: {total}</p>
        <p className="text-xs text-muted-foreground">If a price changed since you added the item, you will be asked to review it before anything is charged.</p>
      </div>
      <div className="flex flex-col gap-4">
        {problem && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>
              <p>{problem.message}</p>
              {problem.priceChanges.length > 0 && (
                <ul className="mt-2 list-disc pl-4">
                  {problem.priceChanges.map((change) => (
                    <li key={change.sku}>{`${items[change.sku]?.name ?? change.sku}: ${formatCents(toCents(change.expected))} → ${formatCents(toCents(change.current))}`}</li>
                  ))}
                </ul>
              )}
              {problem.unavailable.length > 0 && (
                <>
                  <ul className="mt-2 list-disc pl-4">
                    {problem.unavailable.map((item) => {
                      const name = items[item.sku]?.name ?? item.sku
                      return <li key={item.sku}>{item.available > 0 ? `${name}: ${item.available} left (you asked for ${item.requested})` : `${name}: sold out`}</li>
                    })}
                  </ul>
                  <Button
                    className="mt-3"
                    variant="outline"
                    onClick={() => {
                      problem.unavailable.forEach((item) => limit(item.sku, item.available))
                      setProblem(null)
                    }}
                  >
                    Update my cart
                  </Button>
                </>
              )}
            </AlertDescription>
          </Alert>
        )}
        <CardForm totalLabel={total} disabled={checkout.isPending} onSubmit={pay} />
      </div>
    </section>
  )
}
