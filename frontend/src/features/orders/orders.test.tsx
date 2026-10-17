import { focusManager } from '@tanstack/react-query'
import { act, screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
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

  it('pages through orders and retries after a failure', async () => {
    let failing = true
    const pages: string[] = []
    server.use(
      http.get('/api/orders', ({ request }) => {
        const page = new URL(request.url).searchParams.get('page') ?? '1'
        pages.push(page)
        if (failing) return HttpResponse.json({ message: 'Server Error' }, { status: 500 })
        return HttpResponse.json({ data: [order({ id: page === '1' ? 41 : 30 })], meta: { current_page: Number(page), per_page: 1, total: 2, last_page: 2 } })
      }),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/orders')

    expect(await screen.findByText('We could not load your orders.')).toBeInTheDocument()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))
    expect(await screen.findByRole('listitem', { name: 'Order #41' })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Next page' }))

    expect(await screen.findByRole('listitem', { name: 'Order #30' })).toBeInTheDocument()
    expect(pages.at(-1)).toBe('2')
  })

  it('offers a way back from a page past the end instead of the empty message', async () => {
    server.use(
      http.get('/api/orders', ({ request }) => {
        const page = new URL(request.url).searchParams.get('page') ?? '1'
        return HttpResponse.json({ data: page === '1' ? [order()] : [], meta: { current_page: Number(page), per_page: 10, total: 1, last_page: 1 } })
      }),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/orders?page=7')

    expect(await screen.findByText('This page is past the end of your orders.')).toBeInTheDocument()
    expect(screen.queryByText('You have not placed any orders yet.')).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Go to the first page' }))

    expect(await screen.findByRole('listitem', { name: 'Order #41' })).toBeInTheDocument()
  })

  it('offers a retry when an order cannot be loaded for a temporary reason', async () => {
    let failing = true
    server.use(
      http.get('/api/orders/:id', () => (failing ? HttpResponse.json({ message: 'Server Error' }, { status: 500 }) : HttpResponse.json({ data: order() }))),
      ...authHandlers(buyer).handlers,
    )
    const { user } = renderRoute('/orders/41')

    expect(await screen.findByText('We could not load this order.')).toBeInTheDocument()
    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('heading', { name: 'Order #41' })).toBeInTheDocument()
  })

  it('does not show a cached order to the next buyer after the session ends', async () => {
    const detailRequests: string[] = []
    let currentUser: typeof buyer | null = buyer
    server.use(
      http.get('/api/auth/me', () => (currentUser ? HttpResponse.json({ data: currentUser }) : HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }))),
      http.get('/api/orders/:id', ({ params }) => {
        detailRequests.push(String(params.id))
        return currentUser?.id === buyer.id ? HttpResponse.json({ data: order({ id: 41 }) }) : HttpResponse.json({ message: 'Order not found.' }, { status: 404 })
      }),
      http.post('/api/auth/logout', () => {
        currentUser = null
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('/api/auth/login', () => {
        currentUser = { id: 3, name: 'Other Buyer', email: 'other@example.com', role: 'buyer' }
        return HttpResponse.json({ data: currentUser })
      }),
      ...authHandlers(buyer).handlers,
    )
    const { user, router } = renderRoute('/orders/41')
    expect(await screen.findByRole('heading', { name: 'Order #41' })).toBeInTheDocument()

    currentUser = null
    act(() => {
      focusManager.setFocused(false)
      focusManager.setFocused(true)
    })
    await screen.findByRole('link', { name: 'Log in' })
    focusManager.setFocused(undefined)
    await act(() => router.navigate('/login?redirect=%2Forders%2F41'))
    await user.type(await screen.findByLabelText('Email'), 'other@example.com')
    await user.type(screen.getByLabelText('Password'), 'password')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByRole('heading', { name: 'Order not found' })).toBeInTheDocument()
    expect(detailRequests).toEqual(['41', '41'])
  })
  it('does not show a cached order after another tab signs in as a different buyer', async () => {
    const detailRequests: string[] = []
    let currentUser: typeof buyer = buyer
    server.use(
      http.get('/api/auth/me', () => HttpResponse.json({ data: currentUser })),
      http.get('/api/orders/:id', ({ params }) => {
        detailRequests.push(String(params.id))
        return currentUser.id === buyer.id ? HttpResponse.json({ data: order({ id: 41 }) }) : HttpResponse.json({ message: 'Order not found.' }, { status: 404 })
      }),
      ...authHandlers(buyer).handlers,
      ...orderHandlers().handlers,
    )
    const { router } = renderRoute('/orders/41')
    expect(await screen.findByRole('heading', { name: 'Order #41' })).toBeInTheDocument()
    await act(() => router.navigate('/orders'))

    currentUser = { id: 3, name: 'Other Buyer', email: 'other@example.com', role: 'buyer' }
    act(() => {
      focusManager.setFocused(false)
      focusManager.setFocused(true)
    })
    expect(await screen.findByText('Other Buyer')).toBeInTheDocument()
    focusManager.setFocused(undefined)
    await act(() => router.navigate('/orders/41'))

    expect(await screen.findByRole('heading', { name: 'Order not found' })).toBeInTheDocument()
    expect(detailRequests).toEqual(['41', '41'])
  })
})
