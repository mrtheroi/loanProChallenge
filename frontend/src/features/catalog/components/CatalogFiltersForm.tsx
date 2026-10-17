import { useState, type FormEvent } from 'react'
import { Button } from '@/shared/ui/button'
import { Input } from '@/shared/ui/input'
import { Label } from '@/shared/ui/label'
import { SORT_OPTIONS, type CatalogFilters } from '../filters'

type CatalogFiltersFormProps = {
  initial: CatalogFilters
  categories: string[]
  onSubmit: (filters: CatalogFilters) => void
}

const selectClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm'

export function CatalogFiltersForm({ initial, categories, onSubmit }: CatalogFiltersFormProps) {
  const [filters, setFilters] = useState(initial)
  const update = <K extends keyof CatalogFilters>(key: K, value: CatalogFilters[K]) => setFilters((current) => ({ ...current, [key]: value }))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    onSubmit(filters)
  }

  return (
    <form role="search" onSubmit={submit} className="grid gap-4 rounded-lg border p-4 md:grid-cols-6 md:items-end">
      <div className="grid gap-2 md:col-span-2">
        <Label htmlFor="q">Search</Label>
        <Input id="q" value={filters.q} onChange={(e) => update('q', e.target.value)} placeholder="Name, SKU or description" />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="category">Category</Label>
        <select id="category" className={selectClass} value={filters.category} onChange={(e) => update('category', e.target.value)}>
          <option value="">All</option>
          {categories.map((category) => (
            <option key={category} value={category}>
              {category}
            </option>
          ))}
        </select>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="min_price">Min price</Label>
        <Input id="min_price" inputMode="decimal" value={filters.min_price} onChange={(e) => update('min_price', e.target.value)} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="max_price">Max price</Label>
        <Input id="max_price" inputMode="decimal" value={filters.max_price} onChange={(e) => update('max_price', e.target.value)} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="sort">Sort by</Label>
        <select id="sort" className={selectClass} value={filters.sort} onChange={(e) => update('sort', e.target.value)}>
          {SORT_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
      <label className="flex items-center gap-2 text-sm md:col-span-5">
        <input
          type="checkbox"
          checked={filters.in_stock}
          onChange={(e) => {
            const next = { ...filters, in_stock: e.target.checked }
            setFilters(next)
            onSubmit(next)
          }}
        />
        In stock only
      </label>
      <Button type="submit">Search</Button>
    </form>
  )
}
