import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll, beforeEach } from 'vitest'
import { useCartStore } from '@/features/cart/cartStore'
import { server } from './server'

beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))
beforeEach(() => {
  // jsdom has no Web Locks; a single page can stand in for every tab by queueing per lock name.
  const queues = new Map<string, Promise<unknown>>()
  const request = (name: string, callback: () => Promise<unknown>) => {
    const run = (queues.get(name) ?? Promise.resolve()).then(callback, callback)
    queues.set(name, run.catch(() => undefined))
    return run
  }
  Object.defineProperty(navigator, 'locks', { value: { request }, configurable: true })
})
afterEach(() => {
  server.resetHandlers()
  useCartStore.getState().clear()
  localStorage.clear()
  sessionStorage.clear()
  cleanup()
  document.cookie.split(';').forEach((cookie) => {
    const name = cookie.split('=')[0]?.trim()
    if (name) document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
  })
})
afterAll(() => server.close())
