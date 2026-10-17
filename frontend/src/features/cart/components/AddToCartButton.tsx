import { Button } from '@/shared/ui/button'
import { useCartStore, type CartProduct } from '../cartStore'
import { MAX_CART_LINES, maxQuantity } from '../limits'

export function AddToCartButton({ product }: { product: CartProduct }) {
  const inCart = useCartStore((state) => state.items[product.sku]?.quantity ?? 0)
  const lineCount = useCartStore((state) => Object.keys(state.items).length)
  const add = useCartStore((state) => state.add)
  const lineAtMax = product.stock > 0 && inCart >= maxQuantity(product.stock)
  const cartFull = inCart === 0 && lineCount >= MAX_CART_LINES

  return (
    <div className="grid gap-1">
      <Button disabled={product.stock < 1 || lineAtMax || cartFull} onClick={() => add({ sku: product.sku, name: product.name, price: product.price, stock: product.stock })}>
        Add to cart
      </Button>
      {lineAtMax && <p className="text-xs text-muted-foreground">All available units are in your cart</p>}
      {cartFull && <p className="text-xs text-muted-foreground">Your cart can hold up to {MAX_CART_LINES} different products.</p>}
    </div>
  )
}
