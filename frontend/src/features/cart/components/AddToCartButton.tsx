import { Button } from '@/shared/ui/button'
import { useCartStore, type CartProduct } from '../cartStore'

export function AddToCartButton({ product }: { product: CartProduct }) {
  const inCart = useCartStore((state) => state.items[product.sku]?.quantity ?? 0)
  const add = useCartStore((state) => state.add)
  const full = product.stock > 0 && inCart >= product.stock

  return (
    <div className="grid gap-1">
      <Button disabled={product.stock < 1 || full} onClick={() => add({ sku: product.sku, name: product.name, price: product.price, stock: product.stock })}>
        Add to cart
      </Button>
      {full && <p className="text-xs text-muted-foreground">All available units are in your cart</p>}
    </div>
  )
}
