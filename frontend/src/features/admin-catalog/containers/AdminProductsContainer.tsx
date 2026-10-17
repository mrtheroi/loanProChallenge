import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { searchProducts, type Product } from '@/features/catalog/api/catalogApi'
import { ApiError } from '@/shared/api/http'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { Input } from '@/shared/ui/input'
import { Pagination } from '@/shared/ui/Pagination'
import { deleteProduct } from '../api/adminCatalogApi'
import { DeleteProductDialog } from '../components/DeleteProductDialog'
import { ProductTable } from '../components/ProductTable'

export function AdminProductsContainer() {
  const [params, setParams] = useSearchParams()
  const products = useQuery({ queryKey: ['products', params.toString()], queryFn: () => searchProducts(params), placeholderData: keepPreviousData })
  const queryClient = useQueryClient()
  const [pendingDelete, setPendingDelete] = useState<Product | null>(null)
  const remove = useMutation({
    mutationFn: (product: Product) => deleteProduct(product.sku),
    onSuccess: async () => {
      setPendingDelete(null)
      await queryClient.invalidateQueries({ queryKey: ['products'] })
      await queryClient.invalidateQueries({ queryKey: ['product'] })
      await queryClient.invalidateQueries({ queryKey: ['categories'] })
    },
  })
  const invalidSearch = products.error instanceof ApiError && products.error.status === 422 ? products.error : null
  const failedDelete = remove.isError ? remove.variables : undefined

  const search = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const q = String(new FormData(event.currentTarget).get('q') ?? '').trim()
    setParams(q === '' ? {} : { q })
  }

  const goToPage = (page: number) => {
    const next = new URLSearchParams(params)
    next.set('page', String(page))
    setParams(next)
  }

  return (
    <section className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="font-heading text-2xl font-semibold">Products</h1>
        <Button asChild>
          <Link to="/admin/products/new">New product</Link>
        </Button>
      </div>
      <form role="search" onSubmit={search} className="flex gap-2">
        <Input name="q" aria-label="Search products" maxLength={100} defaultValue={params.get('q') ?? ''} key={params.get('q') ?? ''} placeholder="Name, SKU or description" />
        <Button type="submit" variant="outline">
          Search
        </Button>
      </form>
      {failedDelete && (
        <Alert variant="destructive">
          <AlertDescription>{failedDelete.name} could not be deleted. Please try again.</AlertDescription>
        </Alert>
      )}
      {products.isPending && <p className="text-muted-foreground">Loading…</p>}
      {invalidSearch && (
        <Alert variant="destructive">
          <AlertDescription>Check your search: {invalidSearch.message}</AlertDescription>
        </Alert>
      )}
      {products.isError && !invalidSearch && (
        <div className="flex flex-col items-start gap-3">
          <p>We could not load the products.</p>
          <Button onClick={() => void products.refetch()}>Try again</Button>
        </div>
      )}
      {products.data && products.data.data.length === 0 && products.data.meta.total > 0 && (
        <div className="flex flex-col items-start gap-3">
          <p>This page is past the end of the results.</p>
          <Button variant="outline" onClick={() => goToPage(1)}>
            Go to the first page
          </Button>
        </div>
      )}
      {products.data && (products.data.data.length > 0 || products.data.meta.total === 0) && (
        <>
          <ProductTable products={products.data.data} onDelete={setPendingDelete} />
          <Pagination page={products.data.meta.current_page} lastPage={products.data.meta.last_page} onChange={goToPage} />
        </>
      )}
      <DeleteProductDialog product={pendingDelete} deleting={remove.isPending} onConfirm={(product) => remove.mutate(product)} onClose={() => setPendingDelete(null)} />
    </section>
  )
}
