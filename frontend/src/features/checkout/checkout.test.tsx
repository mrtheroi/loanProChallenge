import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { useCartStore } from '@/features/cart/cartStore'
import { authHandlers, buyer } from '@/test/auth-handlers'
import { orderHandlers } from '@/test/order-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/

function fillCart() {
  useCartStore.getState().add({ sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5 })
  useCartStore.getState().add({ sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5 })
  useCartStore.getState().add({ sku: 'CUP-1', name: 'Cup', price: '5.50', stock: 1 })
}

async function payWith(user: ReturnType<typeof renderRoute>['user'], number = '4242 4242 4242 4242') {
  await user.type(await screen.findByLabelText('Card number'), number)
  await user.type(screen.getByLabelText('Expiry month'), '12')
  await user.type(screen.getByLabelText('Expiry year'), '2030')
  await user.type(screen.getByLabelText('CVC'), '123')
  await user.click(screen.getByRole('button', { name: /^Pay / }))
}

describe('checkout', () => {
  it('asks a guest to log in and comes back to checkout', async () => {
    fillCart()
    server.use(...authHandlers(null).handlers)
    const { user, router } = renderRoute('/cart')

    await user.click(await screen.findByRole('link', { name: 'Checkout' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
    expect(router.state.location.search).toBe('?redirect=%2Fcheckout')
  })

  it('places the order with an idempotency key, clears the cart and shows the receipt', async () => {
    fillCart()
    const orders = orderHandlers()
    server.use(...authHandlers(buyer).handlers, ...orders.handlers)
    const { user, router } = renderRoute('/checkout')

    expect(await screen.findByText('Total: $39.48')).toBeInTheDocument()
    await payWith(user)

    await waitFor(() => expect(router.state.location.pathname).toBe('/orders/77'))
    expect(await screen.findByText('Thank you! Your order is confirmed.')).toBeInTheDocument()
    expect(orders.placed).toHaveLength(1)
    expect(orders.placed[0]?.key).toMatch(UUID)
    expect(orders.placed[0]?.body).toEqual({
      items: [{ sku: 'MUG-1', quantity: 2 }, { sku: 'CUP-1', quantity: 1 }],
      card: { number: '4242 4242 4242 4242', exp_month: 12, exp_year: 2030, cvc: '123' },
    })
    expect(useCartStore.getState().items).toEqual({})
  })

  it('keeps the cart and shows the reason when the card is declined', async () => {
    fillCart()
    server.use(http.post('/api/orders', () => HttpResponse.json({ message: 'Your card was declined.' }, { status: 402 })), ...authHandlers(buyer).handlers)
    const { user } = renderRoute('/checkout')

    await payWith(user, '4000 0000 0000 0002')

    expect(await screen.findByRole('alert')).toHaveTextContent('Your card was declined.')
    expect(Object.keys(useCartStore.getState().items)).toEqual(['MUG-1', 'CUP-1'])
  })

  it('lists unavailable items and updates the cart to what is left', async () => {
    fillCart()
    server.use(
      http.post('/api/orders', () =>
        HttpResponse.json(
          { message: 'Some items are not available in the requested quantity.', unavailable: [{ sku: 'MUG-1', requested: 2, available: 1 }, { sku: 'CUP-1', requested: 1, available: 0 }] },
          { status: 409 },
        ),
      ),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/checkout')

    await payWith(user)

    const alert = await screen.findByRole('alert')
    expect(within(alert).getByText('Mug: 1 left (you asked for 2)')).toBeInTheDocument()
    expect(within(alert).getByText('Cup: sold out')).toBeInTheDocument()

    await user.click(within(alert).getByRole('button', { name: 'Update my cart' }))

    expect(useCartStore.getState().items).toEqual({ 'MUG-1': { sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 1, quantity: 1 } })
  })

  it('validates the card before calling the API', async () => {
    fillCart()
    const orders = orderHandlers()
    server.use(...authHandlers(buyer).handlers, ...orders.handlers)
    const { user } = renderRoute('/checkout')

    await user.type(await screen.findByLabelText('Card number'), '1234')
    await user.type(screen.getByLabelText('CVC'), '1')
    await user.click(screen.getByRole('button', { name: /^Pay / }))

    expect(await screen.findByText('Enter 13 to 19 digits.')).toBeInTheDocument()
    expect(screen.getByText('Enter 3 or 4 digits.')).toBeInTheDocument()
    expect(orders.placed).toHaveLength(0)
  })

  it('reuses the key when the same payment is retried after a network failure and makes a new one for a changed payment', async () => {
    fillCart()
    const keys: Array<string | null> = []
    server.use(
      http.post('/api/orders', ({ request }) => {
        keys.push(request.headers.get('Idempotency-Key'))
        return HttpResponse.error()
      }),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/checkout')

    await payWith(user)
    expect(await screen.findByRole('alert')).toHaveTextContent('We could not reach the server.')
    await user.click(screen.getByRole('button', { name: /^Pay / }))
    await waitFor(() => expect(keys).toHaveLength(2))

    await user.clear(screen.getByLabelText('CVC'))
    await user.type(screen.getByLabelText('CVC'), '999')
    await user.click(screen.getByRole('button', { name: /^Pay / }))
    await waitFor(() => expect(keys).toHaveLength(3))

    expect(keys[0]).toMatch(UUID)
    expect(keys[1]).toBe(keys[0])
    expect(keys[2]).not.toBe(keys[0])
  })

  it('says the cart is empty instead of showing the payment form', async () => {
    server.use(...authHandlers(buyer).handlers)
    renderRoute('/checkout')

    expect(await screen.findByText('Your cart is empty.')).toBeInTheDocument()
    expect(screen.queryByLabelText('Card number')).not.toBeInTheDocument()
  })

  it('uses a new key for the next attempt once the server has answered', async () => {
    fillCart()
    const keys: Array<string | null> = []
    server.use(
      http.post('/api/orders', ({ request }) => {
        keys.push(request.headers.get('Idempotency-Key'))
        return HttpResponse.json({ message: 'Your card was declined.' }, { status: 402 })
      }),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/checkout')

    await payWith(user)
    await screen.findByText('Your card was declined.')
    await user.click(screen.getByRole('button', { name: /^Pay / }))
    await waitFor(() => expect(keys).toHaveLength(2))

    expect(keys[1]).not.toBe(keys[0])
  })
})
