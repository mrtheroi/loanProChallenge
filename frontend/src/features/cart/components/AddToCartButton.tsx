import { useEffect, useState } from 'react'
import { Button } from '@/shared/ui/button'
import { useCartStore, type CartProduct } from '../cartStore'
import { MAX_CART_LINES, maxQuantity } from '../limits'

const CONFIRMATION_MS = 2000

export function AddToCartButton({ product }: { product: CartProduct }) {
  const inCart = useCartStore((state) => state.items[product.sku]?.quantity ?? 0)
  const lineCount = useCartStore((state) => Object.keys(state.items).length)
  const add = useCartStore((state) => state.add)
  const [confirmationKey, setConfirmationKey] = useState(0)
  const lineAtMax = product.stock > 0 && inCart >= maxQuantity(product.stock)
  const cartFull = inCart === 0 && lineCount >= MAX_CART_LINES

  useEffect(() => {
    if (confirmationKey === 0) return
    const timer = setTimeout(() => setConfirmationKey(0), CONFIRMATION_MS)
    return () => clearTimeout(timer)
  }, [confirmationKey])

  const addToCart = () => {
    if (add({ sku: product.sku, name: product.name, price: product.price, stock: product.stock })) setConfirmationKey((key) => key + 1)
  }

  return (
    <div className="grid gap-1">
      <Button disabled={product.stock < 1 || lineAtMax || cartFull} onClick={addToCart}>
        Add to cart
      </Button>
      {confirmationKey > 0 && (
        <p role="status" className="text-xs font-medium text-emerald-600">
          Added to cart
        </p>
      )}
      {lineAtMax && <p className="text-xs text-muted-foreground">All available units are in your cart</p>}
      {cartFull && <p className="text-xs text-muted-foreground">Your cart can hold up to {MAX_CART_LINES} different products.</p>}
    </div>
  )
}
