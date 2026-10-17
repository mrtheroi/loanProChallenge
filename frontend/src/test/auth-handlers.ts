import { http, HttpResponse } from 'msw'

export type TestUser = { id: number; name: string; email: string; role: 'admin' | 'buyer' }

export const buyer: TestUser = { id: 2, name: 'Demo Buyer', email: 'buyer@example.com', role: 'buyer' }
export const admin: TestUser = { id: 1, name: 'Demo Admin', email: 'admin@example.com', role: 'admin' }

export function authHandlers(initial: TestUser | null, accounts: Record<string, TestUser> = { password: buyer }) {
  let current = initial
  const calls = { login: 0, register: 0, logout: 0 }

  const handlers = [
    http.get('/sanctum/csrf-cookie', () => {
      document.cookie = 'XSRF-TOKEN=test-token; path=/'
      return new HttpResponse(null, { status: 204 })
    }),
    http.get('/api/auth/me', () =>
      current === null ? HttpResponse.json({ message: 'Unauthenticated.' }, { status: 401 }) : HttpResponse.json({ data: current }),
    ),
    http.post('/api/auth/login', async ({ request }) => {
      calls.login++
      const { password } = (await request.json()) as { email: string; password: string }
      const user = accounts[password]
      if (user === undefined) {
        return HttpResponse.json({ message: 'These credentials do not match our records.', errors: { email: ['These credentials do not match our records.'] } }, { status: 422 })
      }
      current = user
      return HttpResponse.json({ data: user })
    }),
    http.post('/api/auth/register', async ({ request }) => {
      calls.register++
      const body = (await request.json()) as { name: string; email: string }
      if (body.email === 'taken@example.com') {
        return HttpResponse.json({ message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } }, { status: 422 })
      }
      current = { id: 9, name: body.name, email: body.email, role: 'buyer' }
      return HttpResponse.json({ data: current }, { status: 201 })
    }),
    http.post('/api/auth/logout', () => {
      calls.logout++
      current = null
      return new HttpResponse(null, { status: 204 })
    }),
  ]

  return { handlers, calls }
}
