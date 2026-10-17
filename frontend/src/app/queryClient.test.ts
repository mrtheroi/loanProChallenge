import { describe, expect, it } from 'vitest'
import { ApiError } from '@/shared/api/http'
import { shouldRetry } from './queryClient'

describe('query retry policy', () => {
  it('retries server and network failures twice but never a client error', () => {
    expect(shouldRetry(0, new ApiError(503, 'Unavailable'))).toBe(true)
    expect(shouldRetry(1, new TypeError('fetch failed'))).toBe(true)
    expect(shouldRetry(2, new ApiError(500, 'Error'))).toBe(false)
    expect(shouldRetry(0, new ApiError(422, 'Invalid'))).toBe(false)
    expect(shouldRetry(0, new ApiError(404, 'Not found'))).toBe(false)
  })
})
