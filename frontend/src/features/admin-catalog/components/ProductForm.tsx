import { useForm } from 'react-hook-form'
import { FormField } from '@/features/auth/components/FormField'
import { Button } from '@/shared/ui/button'
import type { ProductInput } from '../api/adminCatalogApi'

export type ProductValues = { sku: string; name: string; description: string; category: string; price: string; stock: string; weight_kg: string }

export const productFields = ['sku', 'name', 'description', 'category', 'price', 'stock', 'weight_kg'] as const

type ProductFormProps = {
  initial?: ProductValues
  categories: string[]
  submitLabel: string
  disabled: boolean
  onSubmit: (input: ProductInput, form: ReturnType<typeof useForm<ProductValues>>) => void
}

const emptyValues: ProductValues = { sku: '', name: '', description: '', category: '', price: '', stock: '', weight_kg: '' }

function toInput(values: ProductValues): ProductInput {
  const optional = (value: string) => (value.trim() === '' ? null : value)

  return { ...values, description: optional(values.description), weight_kg: optional(values.weight_kg) }
}

export function ProductForm({ initial, categories, submitLabel, disabled, onSubmit }: ProductFormProps) {
  const form = useForm<ProductValues>({ defaultValues: initial ?? emptyValues })
  const { errors } = form.formState

  return (
    <form noValidate onSubmit={form.handleSubmit((values) => onSubmit(toInput(values), form))} className="grid max-w-xl gap-4">
      <FormField id="sku" label="SKU" readOnly={initial !== undefined} error={errors.sku?.message} {...form.register('sku')} />
      <FormField id="name" label="Name" error={errors.name?.message} {...form.register('name')} />
      <FormField id="description" label="Description" error={errors.description?.message} {...form.register('description')} />
      <FormField id="category" label="Category" list="category-options" autoComplete="off" error={errors.category?.message} {...form.register('category')} />
      <datalist id="category-options">
        {categories.map((category) => (
          <option key={category} value={category} label={category} />
        ))}
      </datalist>
      <div className="grid grid-cols-3 gap-4">
        <FormField id="price" label="Price" inputMode="decimal" error={errors.price?.message} {...form.register('price')} />
        <FormField id="stock" label="Stock" inputMode="numeric" error={errors.stock?.message} {...form.register('stock')} />
        <FormField id="weight_kg" label="Weight (kg)" inputMode="decimal" error={errors.weight_kg?.message} {...form.register('weight_kg')} />
      </div>
      <Button type="submit" disabled={disabled}>
        {submitLabel}
      </Button>
    </form>
  )
}
