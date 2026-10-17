import { apiFetch } from '@/shared/api/http'
import type { PageMeta } from '@/shared/api/pagination'

export type OrderLine = { sku: string; name: string; unit_price: string; quantity: number; line_total: string }

export type Order = {
  id: number
  status: 'paid'
  total: string
  card: { brand: string; last4: string }
  created_at: string
  items: OrderLine[]
}

export type OrderPage = { data: Order[]; meta: PageMeta }

export type CheckoutPayload = {
  items: Array<{ sku: string; quantity: number; unit_price: string }>
  card: { number: string; exp_month: number; exp_year: number; cvc: string }
}

export type Unavailable = { sku: string; requested: number; available: number }

export type PriceChange = { sku: string; expected: string; current: string }

/** Private keys carry the buyer's id, so a cache filled for one buyer is never read for another. */
export function ordersQueryKey(userId: number | undefined, page: number) {
  return ['orders', userId, page] as const
}

export function orderQueryKey(userId: number | undefined, id: string | number) {
  return ['order', userId, String(id)] as const
}

export async function placeOrder(idempotencyKey: string, payload: CheckoutPayload): Promise<Order> {
  return (await apiFetch<{ data: Order }>('/api/orders', { method: 'POST', body: payload, headers: { 'Idempotency-Key': idempotencyKey } })).data
}

export function listOrders(page: number): Promise<OrderPage> {
  return apiFetch<OrderPage>(`/api/orders?page=${page}`)
}

export async function fetchOrder(id: string): Promise<Order> {
  return (await apiFetch<{ data: Order }>(`/api/orders/${encodeURIComponent(id)}`)).data
}
