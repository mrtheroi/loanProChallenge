import type { Order } from '@/features/orders/api/ordersApi'
import { http, HttpResponse } from 'msw'

export type TestOrder = Order

export function order(overrides: Partial<TestOrder> = {}): TestOrder {
  return {
    id: 41,
    status: 'paid',
    total: '33.98',
    card: { brand: 'visa', last4: '4242' },
    created_at: '2026-10-16T12:00:00+00:00',
    items: [{ sku: 'MUG-1', name: 'Mug', unit_price: '16.99', quantity: 2, line_total: '33.98' }],
    ...overrides,
  }
}

type PlaceRequest = { key: string | null; body: { items: Array<{ sku: string; quantity: number }>; card: Record<string, unknown> } }

export function orderHandlers(orders: TestOrder[] = []) {
  const placed: PlaceRequest[] = []
  const handlers = [
    http.post('/api/orders', async ({ request }) => {
      const body = (await request.json()) as PlaceRequest['body']
      placed.push({ key: request.headers.get('Idempotency-Key'), body })
      return HttpResponse.json({ data: order({ id: 77 }) }, { status: 201 })
    }),
    http.get('/api/orders', () => HttpResponse.json({ data: orders, meta: { current_page: 1, per_page: 10, total: orders.length, last_page: 1 } })),
    http.get('/api/orders/:id', ({ params }) => {
      const found = [...orders, order({ id: 77 })].find((o) => o.id === Number(params.id))
      return found ? HttpResponse.json({ data: found }) : HttpResponse.json({ message: 'Order not found.' }, { status: 404 })
    }),
  ]

  return { handlers, placed }
}
