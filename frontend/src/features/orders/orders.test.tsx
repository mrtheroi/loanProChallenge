import { screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { authHandlers, buyer } from '@/test/auth-handlers'
import { order, orderHandlers } from '@/test/order-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('my orders', () => {
  it('lists the buyer orders and opens one', async () => {
    server.use(...authHandlers(buyer).handlers, ...orderHandlers([order({ id: 41 }), order({ id: 40, total: '5.50', items: [{ sku: 'CUP-1', name: 'Cup', unit_price: '5.50', quantity: 1, line_total: '5.50' }] })]).handlers)
    const { user, router } = renderRoute('/')

    await user.click(await screen.findByRole('link', { name: 'My orders' }))

    const first = await screen.findByRole('listitem', { name: 'Order #41' })
    expect(within(first).getByText('$33.98')).toBeInTheDocument()
    expect(screen.getByRole('listitem', { name: 'Order #40' })).toBeInTheDocument()

    await user.click(within(first).getByRole('link', { name: 'View order #41' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/orders/41'))
    expect(await screen.findByRole('heading', { name: 'Order #41' })).toBeInTheDocument()
    expect(screen.getByText('Paid with visa ending in 4242')).toBeInTheDocument()
    expect(screen.getByRole('row', { name: /Mug/ })).toHaveTextContent('2 × $16.99$33.98')
  })

  it('shows an empty state', async () => {
    server.use(...authHandlers(buyer).handlers, ...orderHandlers([]).handlers)
    renderRoute('/orders')

    expect(await screen.findByText('You have not placed any orders yet.')).toBeInTheDocument()
  })

  it('shows not found for an order that is not the buyer own', async () => {
    server.use(...authHandlers(buyer).handlers, ...orderHandlers([]).handlers)
    renderRoute('/orders/999')

    expect(await screen.findByRole('heading', { name: 'Order not found' })).toBeInTheDocument()
  })
})
