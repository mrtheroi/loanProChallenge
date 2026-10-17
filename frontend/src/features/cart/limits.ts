export const MAX_QUANTITY_PER_LINE = 999
export const MAX_CART_LINES = 50

export function maxQuantity(stock: number): number {
  return Math.min(stock, MAX_QUANTITY_PER_LINE)
}
