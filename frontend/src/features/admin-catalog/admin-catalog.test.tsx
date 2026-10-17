import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { adminHandlers } from '@/test/admin-handlers'
import { admin, authHandlers } from '@/test/auth-handlers'
import { catalogHandlers, product } from '@/test/catalog-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('admin catalog', () => {
  it('lists every product with its stock and actions to edit or delete it', async () => {
    server.use(...authHandlers(admin).handlers, ...catalogHandlers([product(), product({ sku: 'TENT-1', name: 'Tent', price: '199.99', stock: 0 })]).handlers)
    renderRoute('/admin')

    const tent = await screen.findByRole('row', { name: /TENT-1/ })
    expect(within(tent).getByText('Tent')).toBeInTheDocument()
    expect(within(tent).getByText('$199.99')).toBeInTheDocument()
    expect(within(tent).getByText('0')).toBeInTheDocument()
    expect(within(tent).getByRole('link', { name: 'Edit Tent' })).toHaveAttribute('href', '/admin/products/TENT-1/edit')
    expect(within(tent).getByRole('button', { name: 'Delete Tent' })).toBeInTheDocument()
    expect(screen.getByRole('row', { name: /MUG-1/ })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New product' })).toHaveAttribute('href', '/admin/products/new')
  })

  it('searches and pages through products, keeping both in the URL', async () => {
    const products = Array.from({ length: 13 }, (_, i) => product({ sku: `SKU-${i + 1}`, name: `Item ${i + 1}` }))
    const catalog = catalogHandlers([...products, product({ sku: 'LAMP-1', name: 'Lamp' })])
    server.use(...authHandlers(admin).handlers, ...catalog.handlers)
    const { user, router } = renderRoute('/admin')
    await screen.findByRole('row', { name: /SKU-1 / })

    await user.click(screen.getByRole('button', { name: 'Next page' }))
    expect(await screen.findByRole('row', { name: /LAMP-1/ })).toBeInTheDocument()
    expect(router.state.location.search).toBe('?page=2')

    await user.type(screen.getByLabelText('Search products'), 'lamp')
    await user.click(screen.getByRole('button', { name: 'Search' }))
    expect(await screen.findByText('Page 1 of 1')).toBeInTheDocument()
    expect(screen.getAllByRole('row')).toHaveLength(2)
    expect(router.state.location.search).toBe('?q=lamp')
    expect(catalog.requests.at(-1)?.get('q')).toBe('lamp')
  })
  it('creates a product with an existing or a new category and shows it in the list', async () => {
    const products = [product()]
    const writes = adminHandlers(products)
    server.use(...authHandlers(admin).handlers, ...catalogHandlers(products, { categories: ['Kitchen', 'Outdoors'] }).handlers, ...writes.handlers)
    const { user, router } = renderRoute('/admin/products/new')

    await user.type(await screen.findByLabelText('SKU'), 'lamp-1')
    await user.type(screen.getByLabelText('Name'), 'Desk lamp')
    await user.type(screen.getByLabelText('Description'), 'LED lamp')
    await user.type(screen.getByLabelText('Category'), 'Lighting')
    await user.type(screen.getByLabelText('Price'), '24.50')
    await user.type(screen.getByLabelText('Stock'), '7')
    await user.type(screen.getByLabelText('Weight (kg)'), '1.2')
    const suggestions = () => Array.from(document.getElementById(screen.getByLabelText('Category').getAttribute('list') ?? '')?.querySelectorAll('option') ?? [], (option) => option.value)
    await waitFor(() => expect(suggestions()).toEqual(['Kitchen', 'Outdoors']))
    await user.click(screen.getByRole('button', { name: 'Create product' }))

    expect(await screen.findByRole('row', { name: /LAMP-1/ })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/admin')
    expect(writes.writes).toEqual([
      { method: 'POST', sku: 'lamp-1', body: { sku: 'lamp-1', name: 'Desk lamp', description: 'LED lamp', category: 'Lighting', price: '24.50', stock: '7', weight_kg: '1.2' } },
    ])
  })
  it('shows the server validation errors next to their fields and stays on the form', async () => {
    const products = [product()]
    server.use(...authHandlers(admin).handlers, ...catalogHandlers(products).handlers, ...adminHandlers(products).handlers)
    const { user, router } = renderRoute('/admin/products/new')

    await user.type(await screen.findByLabelText('SKU'), 'mug-1')
    await user.type(screen.getByLabelText('Name'), 'Mug again')
    await user.type(screen.getByLabelText('Category'), 'Kitchen')
    await user.type(screen.getByLabelText('Price'), '10')
    await user.type(screen.getByLabelText('Stock'), '1')
    await user.click(screen.getByRole('button', { name: 'Create product' }))

    expect(await screen.findByText('already exists')).toBeInTheDocument()
    expect(screen.getByLabelText('SKU')).toHaveAttribute('aria-invalid', 'true')
    expect(router.state.location.pathname).toBe('/admin/products/new')
  })
  it('edits a product from the list with its current values filled in', async () => {
    const products = [product({ weight_kg: null })]
    const writes = adminHandlers(products)
    server.use(...authHandlers(admin).handlers, ...catalogHandlers(products).handlers, ...writes.handlers)
    const { user, router } = renderRoute('/admin')

    await user.click(await screen.findByRole('link', { name: 'Edit Mug' }))
    const name = await screen.findByLabelText('Name')
    await waitFor(() => expect(name).toHaveValue('Mug'))
    expect(screen.getByLabelText('SKU')).toHaveValue('MUG-1')
    expect(screen.getByLabelText('SKU')).toHaveAttribute('readonly')
    expect(screen.getByLabelText('Stock')).toHaveValue('5')
    expect(screen.getByLabelText('Weight (kg)')).toHaveValue('')
    await user.clear(name)
    await user.type(name, 'Big mug')
    await user.clear(screen.getByLabelText('Price'))
    await user.type(screen.getByLabelText('Price'), '18.00')
    await user.click(screen.getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByRole('row', { name: /Big mug/ })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/admin')
    expect(writes.writes).toEqual([
      { method: 'PUT', sku: 'MUG-1', body: { sku: 'MUG-1', name: 'Big mug', description: 'Ceramic mug', category: 'Kitchen', price: '18.00', stock: '5', weight_kg: null } },
    ])
  })

  it('says so when the product to edit does not exist', async () => {
    server.use(...authHandlers(admin).handlers, ...catalogHandlers([]).handlers, ...adminHandlers([]).handlers)
    renderRoute('/admin/products/NOPE-1/edit')

    expect(await screen.findByRole('heading', { name: 'Product not found' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to products' })).toHaveAttribute('href', '/admin')
  })
  it('deletes a product only after the admin confirms it', async () => {
    const products = [product(), product({ sku: 'TENT-1', name: 'Tent' })]
    const writes = adminHandlers(products)
    server.use(...authHandlers(admin).handlers, ...catalogHandlers(products).handlers, ...writes.handlers)
    const { user } = renderRoute('/admin')

    await user.click(await screen.findByRole('button', { name: 'Delete Tent' }))
    const dialog = await screen.findByRole('alertdialog', { name: 'Delete Tent?' })
    expect(within(dialog).getByText('It disappears from the store. Its SKU, TENT-1, stays reserved and cannot be reused.')).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    expect(writes.writes).toEqual([])

    await user.click(screen.getByRole('button', { name: 'Delete Tent' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete' }))

    await waitFor(() => expect(screen.queryByRole('row', { name: /TENT-1/ })).not.toBeInTheDocument())
    expect(screen.getByRole('row', { name: /MUG-1/ })).toBeInTheDocument()
    expect(writes.writes).toEqual([{ method: 'DELETE', sku: 'TENT-1', body: null }])
  })
  it('tells the admin when a delete fails and keeps the product listed', async () => {
    const products = [product()]
    server.use(
      http.delete('/api/admin/products/:sku', () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })),
      ...authHandlers(admin).handlers,
      ...catalogHandlers(products).handlers,
    )
    const { user } = renderRoute('/admin')

    await user.click(await screen.findByRole('button', { name: 'Delete Mug' }))
    await user.click(within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete' }))

    expect(await screen.findByText('Mug could not be deleted. Please try again.')).toBeInTheDocument()
    expect(screen.getByRole('row', { name: /MUG-1/ })).toBeInTheDocument()
  })
  it('offers a retry when the product list cannot be loaded', async () => {
    let fail = true
    const catalog = catalogHandlers([product()])
    server.use(
      http.get('/api/products', () => (fail ? HttpResponse.json({ message: 'Server Error' }, { status: 500 }) : undefined)),
      ...authHandlers(admin).handlers,
      ...catalog.handlers,
    )
    const { user } = renderRoute('/admin')

    expect(await screen.findByText('We could not load the products.')).toBeInTheDocument()
    fail = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('row', { name: /MUG-1/ })).toBeInTheDocument()
  })
  it('shows the API validation message for an invalid search instead of a retry', async () => {
    server.use(
      http.get('/api/products', () => HttpResponse.json({ message: 'The q field must not be greater than 100 characters.', errors: { q: ['The q field must not be greater than 100 characters.'] } }, { status: 422 })),
      ...authHandlers(admin).handlers,
      ...catalogHandlers([]).handlers,
    )
    renderRoute(`/admin?q=${'a'.repeat(101)}`)

    expect(await screen.findByRole('alert')).toHaveTextContent('Check your search: The q field must not be greater than 100 characters.')
    expect(screen.queryByRole('button', { name: 'Try again' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Search products')).toHaveAttribute('maxlength', '100')
  })
})
