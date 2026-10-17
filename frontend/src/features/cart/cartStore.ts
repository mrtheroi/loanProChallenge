import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'
import { isPrice } from '@/shared/lib/money'
import { MAX_CART_LINES, MAX_QUANTITY_PER_LINE } from './limits'

export type CartProduct = { sku: string; name: string; price: string; stock: number }
export type CartLine = CartProduct & { quantity: number }

type CartState = {
  items: Record<string, CartLine>
  add: (product: CartProduct) => boolean
  setQuantity: (sku: string, quantity: number) => number
  remove: (sku: string) => void
  limit: (sku: string, available: number) => void
  clear: () => void
}

function maxQuantity(stock: number): number {
  return Math.min(stock, MAX_QUANTITY_PER_LINE)
}

export const useCartStore = create<CartState>()(
  persist(
    (set, get) => ({
      items: {},
      add: (product) => {
        const { items } = get()
        const existing = items[product.sku]
        if (product.stock < 1 || (existing === undefined && Object.keys(items).length >= MAX_CART_LINES)) return false

        const quantity = Math.min((existing?.quantity ?? 0) + 1, maxQuantity(product.stock))
        set({ items: { ...items, [product.sku]: { ...product, quantity } } })

        return true
      },
      setQuantity: (sku, quantity) => {
        const line = get().items[sku]
        if (line === undefined) return 0
        const clamped = Math.max(1, Math.min(quantity, maxQuantity(line.stock)))
        set((state) => ({ items: { ...state.items, [sku]: { ...line, quantity: clamped } } }))

        return clamped
      },
      remove: (sku) =>
        set((state) => {
          const items = { ...state.items }
          delete items[sku]

          return { items }
        }),
      limit: (sku, available) =>
        set((state) => {
          const line = state.items[sku]
          if (line === undefined) return state
          const items = { ...state.items }
          if (available < 1) delete items[sku]
          else items[sku] = { ...line, stock: available, quantity: Math.min(line.quantity, available) }

          return { items }
        }),
      clear: () => set({ items: {} }),
    }),
    {
      name: 'loanpro-cart',
      storage: createJSONStorage(() => localStorage),
      partialize: (state) => ({ items: state.items }),
      merge: (persisted, current) => ({ ...current, items: validLines(persisted) }),
    },
  ),
)

function isCartLine(value: unknown): value is CartLine {
  if (value === null || typeof value !== 'object') return false
  const line = value as Record<string, unknown>

  return (
    typeof line.sku === 'string' &&
    typeof line.name === 'string' &&
    typeof line.price === 'string' &&
    isPrice(line.price) &&
    Number.isInteger(line.stock) &&
    Number.isInteger(line.quantity) &&
    (line.quantity as number) >= 1 &&
    (line.quantity as number) <= maxQuantity(line.stock as number)
  )
}

function validLines(persisted: unknown): Record<string, CartLine> {
  const items = (persisted as { items?: unknown } | null)?.items
  if (items === null || typeof items !== 'object') return {}

  return Object.fromEntries(
    Object.entries(items)
      .filter((entry): entry is [string, CartLine] => isCartLine(entry[1]) && entry[1].sku === entry[0])
      .slice(0, MAX_CART_LINES),
  )
}

export function cartItemCount(items: Record<string, CartLine>): number {
  return Object.values(items).reduce((total, line) => total + line.quantity, 0)
}
