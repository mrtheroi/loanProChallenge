import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { useCartStore } from '@/features/cart/cartStore'
import { admin, authHandlers, buyer } from '@/test/auth-handlers'
import { catalogHandlers, product } from '@/test/catalog-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('cart', () => {
  it('adds products from the catalog and shows the count in the header', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product({ stock: 2 })]).handlers)
    const { user } = renderRoute('/')

    const mug = await screen.findByRole('article', { name: 'Mug' })
    await user.click(within(mug).getByRole('button', { name: 'Add to cart' }))
    await user.click(within(mug).getByRole('button', { name: 'Add to cart' }))

    expect(screen.getByRole('link', { name: 'Cart (2)' })).toHaveAttribute('href', '/cart')
    expect(within(mug).getByRole('button', { name: 'Add to cart' })).toBeDisabled()
    expect(within(mug).getByText('All available units are in your cart')).toBeInTheDocument()
  })

  it('shows the lines with an exact subtotal and lets the buyer change or remove them', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product({ stock: 5 }), product({ sku: 'CUP-1', name: 'Cup', price: '0.10', stock: 9 })]).handlers)
    const { user } = renderRoute('/')

    await user.click(within(await screen.findByRole('article', { name: 'Mug' })).getByRole('button', { name: 'Add to cart' }))
    await user.click(within(screen.getByRole('article', { name: 'Cup' })).getByRole('button', { name: 'Add to cart' }))
    await user.click(screen.getByRole('link', { name: 'Cart (2)' }))

    const mugLine = await screen.findByRole('listitem', { name: 'Mug' })
    await user.clear(within(mugLine).getByLabelText('Quantity'))
    await user.type(within(mugLine).getByLabelText('Quantity'), '3')
    await user.tab()

    expect(within(mugLine).getByText('$50.97')).toBeInTheDocument()
    expect(screen.getByText('Subtotal: $51.07')).toBeInTheDocument()

    await user.click(within(screen.getByRole('listitem', { name: 'Cup' })).getByRole('button', { name: 'Remove' }))

    expect(screen.queryByRole('listitem', { name: 'Cup' })).not.toBeInTheDocument()
    expect(screen.getByText('Subtotal: $50.97')).toBeInTheDocument()
    expect(screen.getByText('Prices and stock are confirmed when you check out.')).toBeInTheDocument()
  })

  it('shows an empty cart', async () => {
    server.use(...authHandlers(null).handlers)
    renderRoute('/cart')

    expect(await screen.findByText('Your cart is empty.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Browse products' })).toHaveAttribute('href', '/')
  })

  it('keeps focus while typing a multi-digit quantity and shows the clamped value after leaving the field', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product({ stock: 30 })]).handlers)
    const { user } = renderRoute('/')

    await user.click(within(await screen.findByRole('article', { name: 'Mug' })).getByRole('button', { name: 'Add to cart' }))
    await user.click(screen.getByRole('link', { name: 'Cart (1)' }))
    const input = within(await screen.findByRole('listitem', { name: 'Mug' })).getByLabelText('Quantity')

    await user.clear(input)
    await user.type(input, '25')
    expect(input).toHaveFocus()
    await user.keyboard('{Enter}')
    expect(screen.getByText('Subtotal: $424.75')).toBeInTheDocument()

    await user.clear(input)
    await user.type(input, '99')
    await user.tab()
    expect(input).toHaveValue(30)
    expect(screen.getByText('Subtotal: $509.70')).toBeInTheDocument()
  })

  it('explains why a product cannot be added when the cart has 50 products', async () => {
    for (let i = 0; i < 50; i++) useCartStore.getState().add({ sku: `P-${i}`, name: `P ${i}`, price: '1.00', stock: 5 })
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product()]).handlers)
    renderRoute('/')

    const mug = await screen.findByRole('article', { name: 'Mug' })
    expect(within(mug).getByRole('button', { name: 'Add to cart' })).toBeDisabled()
    expect(within(mug).getByText('Your cart can hold up to 50 different products.')).toBeInTheDocument()
  })
  it('shows how many units are in stock next to each cart line', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product({ stock: 5 }), product({ sku: 'CUP-1', name: 'Cup', stock: 1 })]).handlers)
    const { user } = renderRoute('/')

    await user.click(within(await screen.findByRole('article', { name: 'Mug' })).getByRole('button', { name: 'Add to cart' }))
    await user.click(within(screen.getByRole('article', { name: 'Cup' })).getByRole('button', { name: 'Add to cart' }))
    await user.click(screen.getByRole('link', { name: 'Cart (2)' }))

    expect(within(await screen.findByRole('listitem', { name: 'Mug' })).getByText('5 in stock')).toBeInTheDocument()
    expect(within(screen.getByRole('listitem', { name: 'Cup' })).getByText('1 in stock')).toBeInTheDocument()
  })
  it('confirms that a product was added to the cart', async () => {
    server.use(...authHandlers(null).handlers, ...catalogHandlers([product({ stock: 5 })]).handlers)
    const { user } = renderRoute('/')

    const mug = await screen.findByRole('article', { name: 'Mug' })
    expect(within(mug).queryByRole('status')).not.toBeInTheDocument()
    await user.click(within(mug).getByRole('button', { name: 'Add to cart' }))

    expect(within(mug).getByRole('status')).toHaveTextContent('Added to cart')
    await waitFor(() => expect(within(mug).queryByRole('status')).not.toBeInTheDocument(), { timeout: 3000 })
  })
  it('tells an admin that only buyer accounts can check out instead of offering checkout', async () => {
    useCartStore.getState().add({ sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5 })
    server.use(...authHandlers(admin).handlers)
    renderRoute('/cart')

    expect(await screen.findByText('Admin accounts cannot place orders. Sign in with a buyer account to check out.')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Checkout' })).not.toBeInTheDocument()
  })

  it.each([
    ['a buyer', buyer],
    ['a guest', null],
  ])('keeps offering checkout to %s', async (_who, account) => {
    useCartStore.getState().add({ sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5 })
    server.use(...authHandlers(account).handlers)
    renderRoute('/cart')

    expect(await screen.findByRole('link', { name: 'Checkout' })).toHaveAttribute('href', '/checkout')
    expect(screen.queryByText(/Admin accounts cannot place orders/)).not.toBeInTheDocument()
  })

  it('does not offer checkout to an admin while the session is still loading', async () => {
    useCartStore.getState().add({ sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5 })
    let release: () => void = () => undefined
    const held = new Promise<void>((resolve) => {
      release = resolve
    })
    server.use(
      http.get('/api/auth/me', async () => {
        await held
        return HttpResponse.json({ data: admin })
      }),
      ...authHandlers(admin).handlers,
    )
    renderRoute('/cart')

    expect(await screen.findByRole('listitem', { name: 'Mug' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Checkout' })).not.toBeInTheDocument()
    release()
    expect(await screen.findByText(/Admin accounts cannot place orders/)).toBeInTheDocument()
  })
})
