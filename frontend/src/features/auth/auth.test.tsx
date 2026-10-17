import { focusManager } from '@tanstack/react-query'
import { act, screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { admin, authHandlers, buyer } from '@/test/auth-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('login', () => {
  it('signs a buyer in, shows the name in the header and returns to the store', async () => {
    server.use(...authHandlers(null).handlers)
    const { user, router } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await user.type(screen.getByLabelText('Password'), 'password')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('Demo Buyer')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })

  it('sends an admin to the admin area', async () => {
    server.use(...authHandlers(null, { secret: admin }).handlers)
    const { user, router } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'admin@example.com')
    await user.type(screen.getByLabelText('Password'), 'secret')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/admin'))
    expect(await screen.findByRole('heading', { name: 'Admin' })).toBeInTheDocument()
  })

  it('returns to a safe redirect target and ignores an external one', async () => {
    server.use(...authHandlers(null).handlers)
    const safe = renderRoute('/login?redirect=%2Forders')
    await safe.user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await safe.user.type(screen.getByLabelText('Password'), 'password')
    await safe.user.click(screen.getByRole('button', { name: 'Log in' }))
    await waitFor(() => expect(safe.router.state.location.pathname).toBe('/orders'))
    safe.unmount()

    server.use(...authHandlers(null).handlers)
    const unsafe = renderRoute('/login?redirect=%2F%2Fevil.example')
    await unsafe.user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await unsafe.user.type(screen.getByLabelText('Password'), 'password')
    await unsafe.user.click(screen.getByRole('button', { name: 'Log in' }))
    await waitFor(() => expect(unsafe.router.state.location.pathname).toBe('/'))
  })

  it('shows the server message for wrong credentials', async () => {
    server.use(...authHandlers(null).handlers)
    const { user } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await user.type(screen.getByLabelText('Password'), 'wrong')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument()
  })

  it('validates the form before calling the API', async () => {
    const auth = authHandlers(null)
    server.use(...auth.handlers)
    const { user } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'not-an-email')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('Enter a valid email address.')).toBeInTheDocument()
    expect(screen.getByText('Enter your password.')).toBeInTheDocument()
    expect(auth.calls.login).toBe(0)
  })
})

describe('register', () => {
  it('creates a buyer account and signs it in', async () => {
    server.use(...authHandlers(null).handlers)
    const { user, router } = renderRoute('/register')

    await user.type(await screen.findByLabelText('Name'), 'New Buyer')
    await user.type(screen.getByLabelText('Email'), 'new@example.com')
    await user.type(screen.getByLabelText('Password'), 'secret123')
    await user.type(screen.getByLabelText('Confirm password'), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('New Buyer')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })

  it('shows server field errors next to their fields', async () => {
    server.use(...authHandlers(null).handlers)
    const { user } = renderRoute('/register')

    await user.type(await screen.findByLabelText('Name'), 'Someone')
    await user.type(screen.getByLabelText('Email'), 'taken@example.com')
    await user.type(screen.getByLabelText('Password'), 'secret123')
    await user.type(screen.getByLabelText('Confirm password'), 'secret123')
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('The email has already been taken.')).toBeInTheDocument()
  })

  it('checks the password length and confirmation before calling the API', async () => {
    const auth = authHandlers(null)
    server.use(...auth.handlers)
    const { user } = renderRoute('/register')

    await user.type(await screen.findByLabelText('Name'), 'Someone')
    await user.type(screen.getByLabelText('Email'), 'someone@example.com')
    await user.type(screen.getByLabelText('Password'), 'short')
    await user.type(screen.getByLabelText('Confirm password'), 'different')
    await user.click(screen.getByRole('button', { name: 'Create account' }))

    expect(await screen.findByText('Use at least 8 characters.')).toBeInTheDocument()
    expect(screen.getByText('The passwords do not match.')).toBeInTheDocument()
    expect(auth.calls.register).toBe(0)
  })
})

describe('protected routes', () => {
  it('sends a guest to the login page and remembers where they were going', async () => {
    server.use(...authHandlers(null).handlers)
    const { router } = renderRoute('/admin')

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
    expect(router.state.location.search).toBe('?redirect=%2Fadmin')
  })

  it('tells a buyer they cannot open the admin area', async () => {
    server.use(...authHandlers(buyer).handlers)
    renderRoute('/admin')

    expect(await screen.findByRole('heading', { name: 'Access denied' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Admin' })).not.toBeInTheDocument()
  })

  it('lets an admin open the admin area', async () => {
    server.use(...authHandlers(admin).handlers)
    renderRoute('/admin')

    expect(await screen.findByRole('heading', { name: 'Admin' })).toBeInTheDocument()
  })
})

describe('header', () => {
  it('offers login and register to guests', async () => {
    server.use(...authHandlers(null).handlers)
    renderRoute('/')

    expect(await screen.findByRole('link', { name: 'Log in' })).toHaveAttribute('href', '/login')
    expect(screen.getByRole('link', { name: 'Register' })).toHaveAttribute('href', '/register')
  })

  it('logs a user out', async () => {
    const auth = authHandlers(buyer)
    server.use(...auth.handlers)
    const { user } = renderRoute('/')

    await user.click(await screen.findByRole('button', { name: 'Log out' }))

    expect(await screen.findByRole('link', { name: 'Log in' })).toBeInTheDocument()
    expect(auth.calls.logout).toBe(1)
  })

  it('shows the admin link only to admins', async () => {
    server.use(...authHandlers(admin).handlers)
    renderRoute('/')

    expect(await screen.findByRole('link', { name: 'Admin' })).toHaveAttribute('href', '/admin')
  })
})

describe('session state', () => {
  it('keeps the signed-in user when the initial guest check answers after the login', async () => {
    let releaseMe: () => void = () => undefined
    const meReleased = new Promise<void>((resolve) => {
      releaseMe = resolve
    })
    const auth = authHandlers(null)
    server.use(
      http.get('/api/auth/me', async () => {
        await meReleased
        return HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })
      }),
      ...auth.handlers,
    )
    const { user } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await user.type(screen.getByLabelText('Password'), 'password')
    await user.click(screen.getByRole('button', { name: 'Log in' }))
    await waitFor(() => expect(auth.calls.login).toBe(1))
    releaseMe()

    expect(await screen.findByText('Demo Buyer')).toBeInTheDocument()
    await new Promise((resolve) => setTimeout(resolve, 50))
    expect(screen.getByText('Demo Buyer')).toBeInTheDocument()
  })

  it('signs the user out locally when any request reports an expired session', async () => {
    server.use(http.post('/api/auth/logout', () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })), ...authHandlers(buyer).handlers)
    const { user } = renderRoute('/')

    await user.click(await screen.findByRole('button', { name: 'Log out' }))

    expect(await screen.findByRole('link', { name: 'Log in' })).toBeInTheDocument()
  })

  it('tells the user when logging out fails for another reason', async () => {
    server.use(http.post('/api/auth/logout', () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })), ...authHandlers(buyer).handlers)
    const { user } = renderRoute('/')

    await user.click(await screen.findByRole('button', { name: 'Log out' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('We could not log you out. Please try again.')
    expect(screen.getByText('Demo Buyer')).toBeInTheDocument()
  })

  it('rechecks the session when the window regains focus', async () => {
    const auth = authHandlers(buyer)
    server.use(...auth.handlers)
    renderRoute('/')
    expect(await screen.findByText('Demo Buyer')).toBeInTheDocument()

    server.use(http.get('/api/auth/me', () => HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 })))
    act(() => {
      focusManager.setFocused(false)
      focusManager.setFocused(true)
    })

    expect(await screen.findByRole('link', { name: 'Log in' })).toBeInTheDocument()
    focusManager.setFocused(undefined)
  })

  it('shows the generic message when the login fails unexpectedly', async () => {
    server.use(http.post('/api/auth/login', () => new HttpResponse('<html>oops</html>', { status: 500 })), ...authHandlers(null).handlers)
    const { user } = renderRoute('/login')

    await user.type(await screen.findByLabelText('Email'), 'buyer@example.com')
    await user.type(screen.getByLabelText('Password'), 'password')
    await user.click(screen.getByRole('button', { name: 'Log in' }))

    expect(await screen.findByText('Something went wrong. Please try again.')).toBeInTheDocument()
  })

  it('offers a retry instead of a login redirect when the session check fails', async () => {
    let failing = true
    server.use(
      http.get('/api/auth/me', () => (failing ? HttpResponse.json({ message: 'Server Error' }, { status: 500 }) : HttpResponse.json({ data: admin }))),
      ...authHandlers(admin).handlers,
    )
    const { user, router } = renderRoute('/admin')

    expect(await screen.findByText('We could not check your session.')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/admin')

    failing = false
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByRole('heading', { name: 'Admin' })).toBeInTheDocument()
  })
})
