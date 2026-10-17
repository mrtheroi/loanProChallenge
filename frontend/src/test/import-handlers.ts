import { http, HttpResponse } from 'msw'
import type { ImportReport } from '@/features/import/api/importApi'

export type { ImportReport }

export function importHandlers(report: ImportReport) {
  const uploads: { name: string; content: string }[] = []
  const handler = http.post('/api/admin/products/import', async ({ request }) => {
    const file = (await request.formData()).get('file')
    if (file !== null && typeof file !== 'string') uploads.push({ name: file.name, content: await file.text() })
    return HttpResponse.json(report)
  })

  return { handlers: [handler], handler, uploads }
}
