import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useCartStore } from '@/features/cart/cartStore'
import { cartSubtotalCents, lineTotalCents } from '@/features/cart/totals'
import { placeOrder, type CheckoutPayload, type Unavailable } from '@/features/orders/api/ordersApi'
import { ApiError } from '@/shared/api/http'
import { formatCents } from '@/shared/lib/money'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { CardForm, type Card } from '../components/CardForm'
import { idempotencyKeyFor, paymentFingerprint, settlePayment, settlesPayment } from '../idempotency'

type Problem = { message: string; unavailable: Unavailable[] }

function problemFrom(error: unknown): Problem {
  if (!(error instanceof ApiError)) return { message: 'We could not reach the server. Check your connection and try again.', unavailable: [] }

  const body = error.body as { unavailable?: Unavailable[] } | null
  const fieldMessage = Object.values(error.errors)[0]?.[0]

  return { message: fieldMessage ?? error.message, unavailable: error.status === 409 ? (body?.unavailable ?? []) : [] }
}

export function CheckoutContainer() {
  const items = useCartStore((state) => state.items)
  const clear = useCartStore((state) => state.clear)
  const limit = useCartStore((state) => state.limit)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [problem, setProblem] = useState<Problem | null>(null)

  const checkout = useMutation({
    mutationFn: ({ key, payload }: { key: string; payload: CheckoutPayload }) => placeOrder(key, payload),
    onSuccess: async (order) => {
      settlePayment()
      queryClient.setQueryData(['order', String(order.id)], order)
      clear()
      await navigate(`/orders/${order.id}`, { state: { placed: true } })
    },
    onError: (error) => {
      if (error instanceof ApiError && settlesPayment(error.status)) settlePayment()
      setProblem(problemFrom(error))
    },
  })

  const lines = Object.values(items)

  if (lines.length === 0 && !checkout.isSuccess) {
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
    const payload: CheckoutPayload = { items: lines.map((line) => ({ sku: line.sku, quantity: line.quantity })), card }
    setProblem(null)
    checkout.mutate({ key: idempotencyKeyFor(paymentFingerprint(payload)), payload })
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
        <p className="text-xs text-muted-foreground">The final price is taken from the store at the moment you pay.</p>
      </div>
      <div className="flex flex-col gap-4">
        {problem && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>
              <p>{problem.message}</p>
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
