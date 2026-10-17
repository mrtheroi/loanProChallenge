import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router'
import { ApiError } from '@/shared/api/http'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { fetchCategories, searchProducts } from '../api/catalogApi'
import { CatalogFiltersForm } from '../components/CatalogFiltersForm'
import { Pagination } from '@/shared/ui/Pagination'
import { ProductCard } from '../components/ProductCard'
import { filtersFrom, paramsFrom } from '../filters'

function isValidationError(error: unknown): error is ApiError {
  return error instanceof ApiError && error.status === 422
}

export function CatalogContainer() {
  const [params, setParams] = useSearchParams()
  const products = useQuery({ queryKey: ['products', params.toString()], queryFn: () => searchProducts(params), placeholderData: keepPreviousData })
  const categories = useQuery({ queryKey: ['categories'], queryFn: fetchCategories, staleTime: 5 * 60_000 })

  const goToPage = (page: number) => {
    const next = new URLSearchParams(params)
    next.set('page', String(page))
    setParams(next)
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="font-heading text-2xl font-semibold">Products</h1>
      <CatalogFiltersForm key={params.toString()} initial={filtersFrom(params)} categories={categories.data ?? []} onSubmit={(filters) => setParams(paramsFrom(filters))} />

      {products.isError && isValidationError(products.error) && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>Check your filters: {products.error.message}</AlertDescription>
        </Alert>
      )}

      {products.isError && !isValidationError(products.error) && (
        <div className="flex flex-col items-start gap-3">
          <p>We could not load the products.</p>
          <Button onClick={() => void products.refetch()}>Try again</Button>
        </div>
      )}

      {products.isPending && <p className="text-muted-foreground">Loading products…</p>}

      {products.data && products.data.data.length === 0 && products.data.meta.total === 0 && <p className="text-muted-foreground">No products match your search.</p>}

      {products.data && products.data.data.length === 0 && products.data.meta.total > 0 && (
        <div className="flex flex-col items-start gap-3">
          <p>This page is past the end of the results.</p>
          <Button variant="outline" onClick={() => goToPage(1)}>
            Go to the first page
          </Button>
        </div>
      )}

      {products.data && products.data.data.length > 0 && (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {products.data.data.map((product) => (
              <ProductCard key={product.sku} product={product} />
            ))}
          </div>
          <Pagination page={products.data.meta.current_page} lastPage={products.data.meta.last_page} onChange={goToPage} />
        </>
      )}
    </section>
  )
}
