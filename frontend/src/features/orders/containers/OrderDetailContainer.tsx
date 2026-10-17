import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useParams } from 'react-router'
import { ApiError } from '@/shared/api/http'
import { formatCents, toCents } from '@/shared/lib/money'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { fetchOrder } from '../api/ordersApi'

export function OrderDetailContainer() {
  const { id = '' } = useParams()
  const location = useLocation()
  const placed = (location.state as { placed?: boolean } | null)?.placed === true
  const order = useQuery({ queryKey: ['order', id], queryFn: () => fetchOrder(id), retry: false })

  if (order.isPending) return <p className="text-muted-foreground">Loading…</p>

  if (order.isError) {
    const notFound = order.error instanceof ApiError && order.error.status === 404

    return (
      <section className="flex flex-col items-start gap-4">
        <h1 className="font-heading text-2xl font-semibold">{notFound ? 'Order not found' : 'We could not load this order.'}</h1>
        <Button asChild variant="outline">
          <Link to="/orders">My orders</Link>
        </Button>
      </section>
    )
  }

  const { data } = order

  return (
    <section className="flex flex-col gap-6">
      {placed && (
        <Alert>
          <AlertDescription>Thank you! Your order is confirmed.</AlertDescription>
        </Alert>
      )}
      <h1 className="font-heading text-2xl font-semibold">Order #{data.id}</h1>
      <p className="text-sm text-muted-foreground">
        Paid with {data.card.brand} ending in {data.card.last4}
      </p>
      <table className="w-full text-sm">
        <thead className="text-left text-muted-foreground">
          <tr>
            <th className="py-2">Product</th>
            <th className="py-2">Quantity</th>
            <th className="py-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {data.items.map((line) => (
            <tr key={line.sku}>
              <td className="py-2">
                {line.name} <span className="text-muted-foreground">({line.sku})</span>
              </td>
              <td className="py-2">
                {line.quantity} × {formatCents(toCents(line.unit_price))}
              </td>
              <td className="py-2 text-right">{formatCents(toCents(line.line_total))}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p className="text-right text-lg font-semibold">Total: {formatCents(toCents(data.total))}</p>
      <Link to="/orders" className="text-sm hover:underline">
        ← My orders
      </Link>
    </section>
  )
}
