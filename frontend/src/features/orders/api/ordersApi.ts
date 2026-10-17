import { apiFetch } from '@/shared/api/http'

export type OrderLine = { sku: string; name: string; unit_price: string; quantity: number; line_total: string }

export type Order = {
  id: number
  status: 'paid'
  total: string
  card: { brand: string; last4: string }
  created_at: string
  items: OrderLine[]
}

export type OrderPage = { data: Order[]; meta: { current_page: number; per_page: number; total: number; last_page: number } }

export type CheckoutPayload = {
  items: Array<{ sku: string; quantity: number }>
  card: { number: string; exp_month: number; exp_year: number; cvc: string }
}

export type Unavailable = { sku: string; requested: number; available: number }

export async function placeOrder(idempotencyKey: string, payload: CheckoutPayload): Promise<Order> {
  return (await apiFetch<{ data: Order }>('/api/orders', { method: 'POST', body: payload, headers: { 'Idempotency-Key': idempotencyKey } })).data
}

export function listOrders(page: number): Promise<OrderPage> {
  return apiFetch<OrderPage>(`/api/orders?page=${page}`)
}

export async function fetchOrder(id: string): Promise<Order> {
  return (await apiFetch<{ data: Order }>(`/api/orders/${encodeURIComponent(id)}`)).data
}
