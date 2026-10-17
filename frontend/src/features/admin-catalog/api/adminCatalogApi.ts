import type { Product } from '@/features/catalog/api/catalogApi'
import { apiFetch } from '@/shared/api/http'

export type ProductInput = {
  sku: string
  name: string
  description: string | null
  category: string
  price: string
  stock: string
  weight_kg: string | null
}

export async function createProduct(input: ProductInput): Promise<Product> {
  return (await apiFetch<{ data: Product }>('/api/admin/products', { method: 'POST', body: input })).data
}

export async function fetchAdminProduct(sku: string): Promise<Product> {
  return (await apiFetch<{ data: Product }>(`/api/admin/products/${encodeURIComponent(sku)}`)).data
}

export async function updateProduct(sku: string, input: ProductInput): Promise<Product> {
  return (await apiFetch<{ data: Product }>(`/api/admin/products/${encodeURIComponent(sku)}`, { method: 'PUT', body: input })).data
}

export async function deleteProduct(sku: string): Promise<void> {
  await apiFetch<void>(`/api/admin/products/${encodeURIComponent(sku)}`, { method: 'DELETE' })
}
