import { describe, expect, it } from 'vitest'
import { safeRedirect } from './redirect'

describe('safeRedirect', () => {
  it.each([
    ['/orders', '/orders'],
    ['/products?q=mug#top', '/products?q=mug#top'],
  ])('keeps the same-site path %s', (target, expected) => {
    expect(safeRedirect(target)).toBe(expected)
  })

  it.each([
    [null],
    [''],
    ['orders'],
    ['//evil.example'],
    ['/\\evil.example'],
    ['/\t/evil.example'],
    ['/\n/evil.example'],
    ['https://evil.example/orders'],
    ['javascript:alert(1)'],
  ])('rejects %j', (target) => {
    expect(safeRedirect(target)).toBeNull()
  })
})
