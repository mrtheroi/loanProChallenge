import { http, HttpResponse } from 'msw'
import type { TestProduct } from './catalog-handlers'

type ProductBody = Omit<TestProduct, 'stock'> & { stock: number | string }

/** Admin catalog writes against the same array the catalog handlers read, so the store reflects every change. */
export function adminHandlers(products: TestProduct[]) {
  const writes: { method: string; sku: string; body: ProductBody | null }[] = []
  const find = (sku: string) => products.findIndex((p) => p.sku === sku.toUpperCase())
  const toProduct = (body: ProductBody): TestProduct => ({ ...body, sku: body.sku.trim().toUpperCase(), stock: Number(body.stock) })

  const handlers = [
    http.get('/api/admin/products/:sku', ({ params }) => {
      const index = find(String(params.sku))
      return index === -1 ? HttpResponse.json({ message: 'Product not found.' }, { status: 404 }) : HttpResponse.json({ data: products[index] })
    }),
    http.post('/api/admin/products', async ({ request }) => {
      const body = (await request.json()) as ProductBody
      writes.push({ method: 'POST', sku: body.sku, body })
      if (find(body.sku) !== -1) return HttpResponse.json({ message: 'already exists', errors: { sku: ['already exists'] } }, { status: 422 })
      const created = toProduct(body)
      products.push(created)
      return HttpResponse.json({ data: created }, { status: 201 })
    }),
    http.put('/api/admin/products/:sku', async ({ params, request }) => {
      const body = (await request.json()) as ProductBody
      writes.push({ method: 'PUT', sku: String(params.sku), body })
      const index = find(String(params.sku))
      if (index === -1) return HttpResponse.json({ message: 'Product not found.' }, { status: 404 })
      products[index] = toProduct({ ...body, sku: String(params.sku) })
      return HttpResponse.json({ data: products[index] })
    }),
    http.delete('/api/admin/products/:sku', ({ params }) => {
      writes.push({ method: 'DELETE', sku: String(params.sku), body: null })
      const index = find(String(params.sku))
      if (index === -1) return HttpResponse.json({ message: 'Product not found.' }, { status: 404 })
      products.splice(index, 1)
      return new HttpResponse(null, { status: 204 })
    }),
  ]

  return { handlers, writes }
}
