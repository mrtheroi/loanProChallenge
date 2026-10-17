import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router'
import { AddToCartButton } from '@/features/cart/components/AddToCartButton'
import { ApiError } from '@/shared/api/http'
import { formatCents, toCents } from '@/shared/lib/money'
import { Button } from '@/shared/ui/button'
import { fetchProduct } from '../api/catalogApi'

export function ProductDetailContainer() {
  const { sku = '' } = useParams()
  const product = useQuery({ queryKey: ['product', sku], queryFn: () => fetchProduct(sku), retry: false })

  if (product.isPending) return <p className="text-muted-foreground">Loading…</p>

  if (product.isError) {
    const notFound = product.error instanceof ApiError && product.error.status === 404

    return (
      <section className="flex flex-col items-start gap-4">
        <h1 className="font-heading text-2xl font-semibold">{notFound ? 'Product not found' : 'We could not load this product.'}</h1>
        <div className="flex gap-2">
          {!notFound && <Button onClick={() => void product.refetch()}>Try again</Button>}
          <Button asChild variant="outline">
            <Link to="/">Back to the store</Link>
          </Button>
        </div>
      </section>
    )
  }

  const { data } = product

  return (
    <article className="grid gap-4 md:max-w-xl">
      <Link to="/" className="text-sm text-muted-foreground hover:underline">
        ← All products
      </Link>
      <h1 className="font-heading text-3xl font-semibold">{data.name}</h1>
      <p className="text-2xl font-semibold">{formatCents(toCents(data.price))}</p>
      {data.description && <p>{data.description}</p>}
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
        <dt className="text-muted-foreground">SKU</dt>
        <dd>{data.sku}</dd>
        <dt className="text-muted-foreground">Category</dt>
        <dd>{data.category}</dd>
        <dt className="text-muted-foreground">Availability</dt>
        <dd>{data.stock > 0 ? `${data.stock} in stock` : 'Out of stock'}</dd>
        {data.weight_kg !== null && (
          <>
            <dt className="text-muted-foreground">Weight</dt>
            <dd>{data.weight_kg} kg</dd>
          </>
        )}
      </dl>
      <AddToCartButton product={data} />
    </article>
  )
}
