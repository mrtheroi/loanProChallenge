import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { authHandlers } from '@/test/auth-handlers'
import { catalogHandlers, product } from '@/test/catalog-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('catalog', () => {
  it('lists products with their price and marks the ones out of stock', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product(), product({ sku: 'TENT-1', name: 'Tent', price: '199.99', stock: 0 })]).handlers)
    renderRoute('/')

    const mug = await screen.findByRole('article', { name: 'Mug' })
    expect(within(mug).getByText('$16.99')).toBeInTheDocument()
    expect(within(mug).getByRole('button', { name: 'Add to cart' })).toBeEnabled()

    const tent = screen.getByRole('article', { name: 'Tent' })
    expect(within(tent).getByText('Out of stock')).toBeInTheDocument()
    expect(within(tent).getByRole('button', { name: 'Add to cart' })).toBeDisabled()
  })

  it('keeps the search and filters in the URL and sends them to the API', async () => {
    const catalog = catalogHandlers([product(), product({ sku: 'CUP-1', name: 'Cup' })])
    server.use(...authHandlers(null).handlers, ...catalog.handlers)
    const { user, router } = renderRoute('/')
    await screen.findByRole('article', { name: 'Mug' })

    await user.type(screen.getByLabelText('Search'), 'cup')
    await user.selectOptions(screen.getByLabelText('Category'), 'Kitchen')
    await user.type(screen.getByLabelText('Min price'), '1')
    await user.type(screen.getByLabelText('Max price'), '50')
    await user.click(screen.getByLabelText('In stock only'))
    await user.selectOptions(screen.getByLabelText('Sort by'), '-price')
    await user.click(screen.getByRole('button', { name: 'Search' }))

    await waitFor(() => expect(router.state.location.search).toBe('?q=cup&category=Kitchen&min_price=1&max_price=50&in_stock=1&sort=-price'))
    expect(await screen.findByRole('article', { name: 'Cup' })).toBeInTheDocument()
    expect(screen.queryByRole('article', { name: 'Mug' })).not.toBeInTheDocument()
    expect(Object.fromEntries(catalog.requests.at(-1) ?? [])).toEqual({ q: 'cup', category: 'Kitchen', min_price: '1', max_price: '50', in_stock: '1', sort: '-price' })
  })

  it('pages through results', async () => {
    const products = Array.from({ length: 3 }, (_, i) => product({ sku: `P-${i}`, name: `Product ${i}` }))
    const catalog = catalogHandlers(products, { perPage: 2 })
    server.use(...authHandlers(null).handlers, ...catalog.handlers)
    const { user } = renderRoute('/')

    await screen.findByRole('article', { name: 'Product 0' })
    expect(screen.getByText('Page 1 of 2')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Previous page' })).toBeDisabled()

    await user.click(screen.getByRole('button', { name: 'Next page' }))

    expect(await screen.findByRole('article', { name: 'Product 2' })).toBeInTheDocument()
    expect(screen.getByText('Page 2 of 2')).toBeInTheDocument()
    expect(catalog.requests.at(-1)?.get('page')).toBe('2')
  })

  it('says when nothing matches', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([]).handlers)
    renderRoute('/?q=nothing')

    expect(await screen.findByText('No products match your search.')).toBeInTheDocument()
  })

  it('offers a retry when the catalog cannot be loaded', async () => {
    let failing = true
    server.use(
      http.get('/api/products', () => (failing ? HttpResponse.json({ message: 'Server Error' }, { status: 500 }) : HttpResponse.json({ data: [product()], meta: { current_page: 1, per_page: 12, total: 1, last_page: 1 } }))),
      ...authHandlers(null).handlers,
      ...catalogHandlers([]).handlers,
    )
    const { user } = renderRoute('/')

    expect(await screen.findByText('We could not load the products.')).toBeInTheDocument()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('article', { name: 'Mug' })).toBeInTheDocument()
  })
})

describe('product detail', () => {
  it('shows a product with its details and links back from the catalog', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product()]).handlers)
    const { user, router } = renderRoute('/')

    await user.click(await screen.findByRole('link', { name: 'Mug' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/products/MUG-1'))
    expect(await screen.findByRole('heading', { name: 'Mug' })).toBeInTheDocument()
    expect(screen.getByText('Ceramic mug')).toBeInTheDocument()
    expect(screen.getByText('Kitchen')).toBeInTheDocument()
    expect(screen.getByText('5 in stock')).toBeInTheDocument()
    expect(screen.getByText('0.300 kg')).toBeInTheDocument()
  })

  it('shows not found for an unknown product', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([]).handlers)
    renderRoute('/products/NOPE-1')

    expect(await screen.findByRole('heading', { name: 'Product not found' })).toBeInTheDocument()
  })
})

describe('catalog states', () => {
  it('shows a loading state and a way back from a page past the end', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product()]).handlers)
    const { user } = renderRoute('/?page=9')

    expect(screen.getByText('Loading products…')).toBeInTheDocument()
    expect(await screen.findByText('This page is past the end of the results.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Go to the first page' }))

    expect(await screen.findByRole('article', { name: 'Mug' })).toBeInTheDocument()
  })
})

describe('catalog validation and detail errors', () => {
  it('shows the API validation message for a bad filter instead of a retry', async () => {
    server.use(
      http.get('/api/products', () => HttpResponse.json({ message: 'must be a non-negative decimal number with at most 2 decimals', errors: { min_price: ['must be a non-negative decimal number with at most 2 decimals'] } }, { status: 422 })),
      ...authHandlers(null).handlers,
      ...catalogHandlers([]).handlers,
    )
    renderRoute('/?min_price=abc')

    expect(await screen.findByRole('alert')).toHaveTextContent('Check your filters: must be a non-negative decimal number with at most 2 decimals')
    expect(screen.queryByRole('button', { name: 'Try again' })).not.toBeInTheDocument()
  })

  it('offers a retry when a product cannot be loaded for a temporary reason', async () => {
    let failing = true
    server.use(
      http.get('/api/products/:sku', () => (failing ? HttpResponse.json({ message: 'Server Error' }, { status: 503 }) : HttpResponse.json({ data: product() }))),
      ...authHandlers(null).handlers,
    )
    const { user } = renderRoute('/products/MUG-1')

    expect(await screen.findByText('We could not load this product.')).toBeInTheDocument()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('heading', { name: 'Mug' })).toBeInTheDocument()
  })
})
