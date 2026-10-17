import { Link } from 'react-router'
import { AddToCartButton } from '@/features/cart/components/AddToCartButton'
import { formatCents, toCents } from '@/shared/lib/money'
import type { Product } from '../api/catalogApi'

export function ProductCard({ product }: { product: Product }) {
  const headingId = `product-${product.sku}`

  return (
    <article aria-labelledby={headingId} className="flex flex-col gap-3 rounded-lg border p-4">
      <div className="flex items-start justify-between gap-2">
        <h2 id={headingId} className="font-heading font-semibold">
          <Link to={`/products/${encodeURIComponent(product.sku)}`} className="hover:underline">
            {product.name}
          </Link>
        </h2>
        {product.stock < 1 && <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">Out of stock</span>}
      </div>
      <p className="text-sm text-muted-foreground">{product.category}</p>
      <p className="mt-auto text-lg font-semibold">{formatCents(toCents(product.price))}</p>
      <AddToCartButton product={product} />
    </article>
  )
}
