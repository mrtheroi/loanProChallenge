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

describe('cart rehydration', () => {
  it('drops persisted lines that are not valid instead of crashing', async () => {
    localStorage.setItem(
      'loanpro-cart',
      JSON.stringify({
        state: {
          items: {
            'MUG-1': { sku: 'MUG-1', name: 'Mug', price: '16.99', stock: 5, quantity: 2 },
            'BAD-1': { sku: 'BAD-1', name: 'Bad', price: '1.999', stock: 5, quantity: 1 },
            'BAD-2': { sku: 'BAD-2', name: 'Bad', price: '1.00', stock: 5, quantity: -3 },
            'BAD-3': 'not a line',
          },
        },
        version: 0,
      }),
    )

    await useCartStore.persist.rehydrate()

    expect(Object.keys(useCartStore.getState().items)).toEqual(['MUG-1'])
  })
})
