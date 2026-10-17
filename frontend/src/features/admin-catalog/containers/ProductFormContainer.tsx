import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { applyServerErrors } from '@/features/auth/serverErrors'
import { fetchCategories, type Product } from '@/features/catalog/api/catalogApi'
import { ApiError } from '@/shared/api/http'
import { Alert, AlertDescription } from '@/shared/ui/alert'
import { Button } from '@/shared/ui/button'
import { createProduct, fetchAdminProduct, updateProduct, type ProductInput } from '../api/adminCatalogApi'
import { ProductForm, productFields, type ProductValues } from '../components/ProductForm'

function valuesFrom(product: Product): ProductValues {
  return { ...product, description: product.description ?? '', stock: String(product.stock), weight_kg: product.weight_kg ?? '' }
}

export function ProductFormContainer() {
  const { sku } = useParams()
  const editing = sku !== undefined
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [problem, setProblem] = useState<string | null>(null)
  const categories = useQuery({ queryKey: ['categories'], queryFn: fetchCategories, staleTime: 5 * 60_000 })
  const current = useQuery({ queryKey: ['admin-product', sku], queryFn: () => fetchAdminProduct(sku ?? ''), enabled: editing, gcTime: 0 })
  const save = useMutation({
    mutationFn: (input: ProductInput) => (editing ? updateProduct(sku, input) : createProduct(input)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['products'] })
      await queryClient.invalidateQueries({ queryKey: ['product'] })
      await queryClient.invalidateQueries({ queryKey: ['categories'] })
      await navigate('/admin')
    },
  })

  if (editing && current.isPending) return <p className="text-muted-foreground">Loading…</p>

  if (editing && current.data === undefined) {
    const notFound = current.error instanceof ApiError && current.error.status === 404

    return (
      <section className="flex flex-col items-start gap-4">
        <h1 className="font-heading text-2xl font-semibold">{notFound ? 'Product not found' : 'We could not load this product.'}</h1>
        <div className="flex gap-2">
          {!notFound && <Button onClick={() => void current.refetch()}>Try again</Button>}
          <Button asChild variant="outline">
            <Link to="/admin">Back to products</Link>
          </Button>
        </div>
      </section>
    )
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="font-heading text-2xl font-semibold">{editing ? `Edit ${current.data?.name}` : 'New product'}</h1>
      {problem && (
        <Alert variant="destructive">
          <AlertDescription>{problem}</AlertDescription>
        </Alert>
      )}
      <ProductForm
        initial={current.data && valuesFrom(current.data)}
        categories={categories.data ?? []}
        submitLabel={editing ? 'Save changes' : 'Create product'}
        disabled={save.isPending}
        onSubmit={(input, form) => {
          setProblem(null)
          save.mutate(input, { onError: (error) => setProblem(applyServerErrors(error, productFields, form.setError)) })
        }}
      />
    </section>
  )
}
