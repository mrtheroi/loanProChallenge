import { beforeEach, describe, expect, it } from 'vitest'
import { cartLineCount, useCartStore } from './cartStore'

const mug = { sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 2 }

describe('cart store', () => {
  beforeEach(() => {
    localStorage.clear()
    useCartStore.getState().clear()
  })

  it('adds a product and increments it up to the stock seen when it was added', () => {
    const { add } = useCartStore.getState()
    add(mug)
    add(mug)
    add(mug)

    expect(useCartStore.getState().items).toEqual({ 'MUG-1': { ...mug, quantity: 2 } })
    expect(cartLineCount(useCartStore.getState().items)).toBe(2)
  })

  it('does not add a product that is out of stock', () => {
    useCartStore.getState().add({ ...mug, stock: 0 })

    expect(useCartStore.getState().items).toEqual({})
  })

  it('sets a quantity within 1 and the stock, and removes a line', () => {
    const { add, setQuantity, remove } = useCartStore.getState()
    add(mug)

    setQuantity('MUG-1', 5)
    expect(useCartStore.getState().items['MUG-1']?.quantity).toBe(2)
    setQuantity('MUG-1', 0)
    expect(useCartStore.getState().items['MUG-1']?.quantity).toBe(1)

    remove('MUG-1')
    expect(useCartStore.getState().items).toEqual({})
  })

  it('persists the cart in localStorage', () => {
    useCartStore.getState().add(mug)

    expect(JSON.parse(localStorage.getItem('loanpro-cart') ?? '{}')).toMatchObject({ state: { items: { 'MUG-1': { quantity: 1 } } } })
  })
})
