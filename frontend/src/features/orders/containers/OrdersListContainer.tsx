import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { Pagination } from '@/features/catalog/components/Pagination'
import { formatCents, toCents } from '@/shared/lib/money'
import { Button } from '@/shared/ui/button'
import { listOrders } from '../api/ordersApi'

export function OrdersListContainer() {
  const [params, setParams] = useSearchParams()
  const page = Math.max(1, Number.parseInt(params.get('page') ?? '1', 10) || 1)
  const orders = useQuery({ queryKey: ['orders', page], queryFn: () => listOrders(page) })

  return (
    <section className="flex flex-col gap-6">
      <h1 className="font-heading text-2xl font-semibold">My orders</h1>
      {orders.isPending && <p className="text-muted-foreground">Loading…</p>}
      {orders.isError && (
        <div className="flex flex-col items-start gap-3">
          <p>We could not load your orders.</p>
          <Button onClick={() => void orders.refetch()}>Try again</Button>
        </div>
      )}
      {orders.data && orders.data.data.length === 0 && <p>You have not placed any orders yet.</p>}
      {orders.data && orders.data.data.length > 0 && (
        <>
          <ul className="divide-y rounded-lg border">
            {orders.data.data.map((order) => (
              <li key={order.id} aria-label={`Order #${order.id}`} className="flex flex-wrap items-center justify-between gap-2 p-4">
                <div>
                  <p className="font-medium">Order #{order.id}</p>
                  <p className="text-sm text-muted-foreground">
                    {new Date(order.created_at).toLocaleDateString('en-US', { dateStyle: 'medium' })} · {order.items.length} {order.items.length === 1 ? 'item' : 'items'}
                  </p>
                </div>
                <div className="flex items-center gap-4">
                  <span className="font-semibold">{formatCents(toCents(order.total))}</span>
                  <Button asChild variant="outline">
                    <Link to={`/orders/${order.id}`} aria-label={`View order #${order.id}`}>
                      View
                    </Link>
                  </Button>
                </div>
              </li>
            ))}
          </ul>
          <Pagination page={orders.data.meta.current_page} lastPage={orders.data.meta.last_page} onChange={(next) => setParams({ page: String(next) })} />
        </>
      )}
    </section>
  )
}
