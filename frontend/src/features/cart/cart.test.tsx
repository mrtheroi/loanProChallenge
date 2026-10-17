import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { authHandlers } from '@/test/auth-handlers'
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
})
