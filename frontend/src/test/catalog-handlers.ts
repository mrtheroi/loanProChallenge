import { http, HttpResponse } from 'msw'

export type TestProduct = {
  sku: string
  name: string
  description: string | null
  category: string
  price: string
  stock: number
  weight_kg: string | null
}

export function product(overrides: Partial<TestProduct> = {}): TestProduct {
  return { sku: 'MUG-1', name: 'Mug', description: 'Ceramic mug', category: 'Kitchen', price: '16.99', stock: 5, weight_kg: '0.300', ...overrides }
}

export function catalogHandlers(products: TestProduct[], options: { perPage?: number; categories?: string[] } = {}) {
  const requests: URLSearchParams[] = []
  const perPage = options.perPage ?? 12

  const handlers = [
    http.get('/api/categories', () => HttpResponse.json({ data: options.categories ?? ['Kitchen', 'Outdoors'] })),
    http.get('/api/products', ({ request }) => {
      const params = new URL(request.url).searchParams
      requests.push(params)
      const page = Number(params.get('page') ?? '1')
      const q = params.get('q')?.toLowerCase()
      const filtered = q ? products.filter((p) => p.name.toLowerCase().includes(q)) : products

      return HttpResponse.json({
        data: filtered.slice((page - 1) * perPage, page * perPage),
        meta: { current_page: page, per_page: perPage, total: filtered.length, last_page: Math.max(1, Math.ceil(filtered.length / perPage)) },
      })
    }),
    http.get('/api/products/:sku', ({ params }) => {
      const found = products.find((p) => p.sku === String(params.sku).toUpperCase())

      return found ? HttpResponse.json({ data: found }) : HttpResponse.json({ message: 'Product not found.' }, { status: 404 })
    }),
  ]

  return { handlers, requests }
}
