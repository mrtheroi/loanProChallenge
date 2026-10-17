export type CatalogFilters = {
  q: string
  category: string
  min_price: string
  max_price: string
  in_stock: boolean
  sort: string
}

export const SORT_OPTIONS = [
  { value: 'name', label: 'Name (A–Z)' },
  { value: '-name', label: 'Name (Z–A)' },
  { value: 'price', label: 'Price (low to high)' },
  { value: '-price', label: 'Price (high to low)' },
] as const

export function filtersFrom(params: URLSearchParams): CatalogFilters {
  return {
    q: params.get('q') ?? '',
    category: params.get('category') ?? '',
    min_price: params.get('min_price') ?? '',
    max_price: params.get('max_price') ?? '',
    in_stock: params.get('in_stock') === '1',
    sort: SORT_OPTIONS.some((option) => option.value === params.get('sort')) ? (params.get('sort') ?? 'name') : 'name',
  }
}

export function paramsFrom(filters: CatalogFilters): URLSearchParams {
  const params = new URLSearchParams()
  const text: Array<keyof Omit<CatalogFilters, 'in_stock' | 'sort'>> = ['q', 'category', 'min_price', 'max_price']

  for (const key of text) {
    const value = filters[key].trim()
    if (value !== '') params.set(key, value)
  }
  if (filters.in_stock) params.set('in_stock', '1')
  if (filters.sort !== 'name') params.set('sort', filters.sort)

  return params
}
