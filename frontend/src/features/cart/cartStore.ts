import { create } from 'zustand'
import { createJSONStorage, persist } from 'zustand/middleware'

export type CartProduct = { sku: string; name: string; price: string; stock: number }
export type CartLine = CartProduct & { quantity: number }

type CartState = {
  items: Record<string, CartLine>
  add: (product: CartProduct) => void
  setQuantity: (sku: string, quantity: number) => void
  remove: (sku: string) => void
  limit: (sku: string, available: number) => void
  clear: () => void
}

export const useCartStore = create<CartState>()(
  persist(
    (set) => ({
      items: {},
      add: (product) =>
        set((state) => {
          if (product.stock < 1) return state
          const quantity = Math.min((state.items[product.sku]?.quantity ?? 0) + 1, product.stock)

          return { items: { ...state.items, [product.sku]: { ...product, quantity } } }
        }),
      setQuantity: (sku, quantity) =>
        set((state) => {
          const line = state.items[sku]
          if (line === undefined) return state

          return { items: { ...state.items, [sku]: { ...line, quantity: Math.max(1, Math.min(quantity, line.stock)) } } }
        }),
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
    { name: 'loanpro-cart', storage: createJSONStorage(() => localStorage), partialize: (state) => ({ items: state.items }) },
  ),
)

export function cartLineCount(items: Record<string, CartLine>): number {
  return Object.values(items).reduce((total, line) => total + line.quantity, 0)
}
