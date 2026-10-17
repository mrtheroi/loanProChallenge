import { apiFetch } from '@/shared/api/http'
import type { PageMeta } from '@/shared/api/pagination'

export type Product = {
  sku: string
  name: string
  description: string | null
  category: string
  price: string
  stock: number
  weight_kg: string | null
}

export type ProductPage = {
  data: Product[]
  meta: PageMeta
}

export function searchProducts(params: URLSearchParams): Promise<ProductPage> {
  const query = params.toString()

  return apiFetch<ProductPage>(query === '' ? '/api/products' : `/api/products?${query}`)
}

export async function fetchProduct(sku: string): Promise<Product> {
  return (await apiFetch<{ data: Product }>(`/api/products/${encodeURIComponent(sku)}`)).data
}

export async function fetchCategories(): Promise<string[]> {
  return (await apiFetch<{ data: string[] }>('/api/categories')).data
}
