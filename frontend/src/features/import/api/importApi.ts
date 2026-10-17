import { apiFetch } from '@/shared/api/http'

export type RowStatus = 'created' | 'updated' | 'unchanged' | 'failed'

export type ImportRow = { row: number; sku: string | null; status: RowStatus; errors: Record<string, string[]>; note: string | null }

export type ImportReport = {
  summary: { created: number; updated: number; unchanged: number; failed: number; blank_rows: number }
  new_categories: string[]
  rows: ImportRow[]
}

export function importProducts(file: File): Promise<ImportReport> {
  const body = new FormData()
  body.append('file', file)

  return apiFetch<ImportReport>('/api/admin/products/import', { method: 'POST', body })
}
