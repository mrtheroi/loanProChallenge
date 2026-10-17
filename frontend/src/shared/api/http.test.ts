import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { server } from '@/test/server'
import { ApiError, apiFetch } from './http'

describe('apiFetch', () => {
  it('returns the parsed JSON body of a successful request', async () => {
    server.use(http.get('/api/health', () => HttpResponse.json({ status: 'ok' })))

    await expect(apiFetch<{ status: string }>('/api/health')).resolves.toEqual({ status: 'ok' })
  })

  it('requests the CSRF cookie before the first mutation and sends it back as a header', async () => {
    const seen: Array<string | null> = []
    server.use(
      http.get('/sanctum/csrf-cookie', () => {
        document.cookie = 'XSRF-TOKEN=abc%3D%3D; path=/'
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('/api/auth/login', ({ request }) => {
        seen.push(request.headers.get('X-XSRF-TOKEN'))
        return HttpResponse.json({ data: { id: 1 } })
      }),
    )

    await apiFetch('/api/auth/login', { method: 'POST', body: { email: 'a@b.c' } })

    expect(seen).toEqual(['abc=='])
  })

  it('does not request the CSRF cookie again when it already exists', async () => {
    document.cookie = 'XSRF-TOKEN=existing; path=/'
    let csrfCalls = 0
    server.use(
      http.get('/sanctum/csrf-cookie', () => {
        csrfCalls++
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('/api/auth/logout', () => new HttpResponse(null, { status: 204 })),
    )

    await expect(apiFetch('/api/auth/logout', { method: 'POST' })).resolves.toBeUndefined()
    expect(csrfCalls).toBe(0)
  })

  it('throws an ApiError with the field errors of a validation failure', async () => {
    document.cookie = 'XSRF-TOKEN=t; path=/'
    server.use(
      http.post('/api/auth/register', () =>
        HttpResponse.json({ message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } }, { status: 422 }),
      ),
    )

    const error = await apiFetch('/api/auth/register', { method: 'POST', body: {} }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 422, message: 'The email has already been taken.', errors: { email: ['The email has already been taken.'] } })
  })

  it('throws a generic ApiError when the error body is not JSON', async () => {
    server.use(http.get('/api/products', () => new HttpResponse('<html>Bad Gateway</html>', { status: 502 })))

    await expect(apiFetch('/api/products')).rejects.toMatchObject({ status: 502, message: 'Something went wrong. Please try again.', errors: {} })
  })
})

describe('test server', () => {
  it('reports a request that no handler covers as an error', async () => {
    const logged = vi.spyOn(console, 'error').mockImplementation(() => undefined)

    await fetch('http://localhost/api/unmocked').catch(() => undefined)

    expect(logged.mock.calls.flat().join(' ')).toContain('without a matching request handler')
  })
})
