import { act, screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { admin, authHandlers } from '@/test/auth-handlers'
import { importHandlers, type ImportReport } from '@/test/import-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

// Node's fetch cannot send jsdom's File or FormData, so uploads use Node's own classes, as a browser sends its native ones.
let NodeFile: typeof File
let NodeFormData: typeof FormData
const csv = (content: string) => new NodeFile([content], 'products.csv', { type: 'text/csv' })

const report: ImportReport = {
  summary: { created: 1, updated: 1, unchanged: 0, failed: 1, blank_rows: 2 },
  new_categories: ['Footwear'],
  rows: [
    { row: 2, sku: 'RS-001', status: 'created', errors: {}, note: null },
    { row: 3, sku: 'MUG-1', status: 'updated', errors: {}, note: null },
    { row: 4, sku: 'HD-099', status: 'failed', errors: { name: ['is required'], price: ['must be a non-negative decimal number with at most 2 decimals'] }, note: null },
  ],
}

describe('product import', () => {
  beforeAll(async () => {
    const nodeBuffer = 'node:buffer'
    NodeFile = ((await import(/* @vite-ignore */ nodeBuffer)) as { File: typeof File }).File
    const empty = new Response('', { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
    NodeFormData = (await empty.formData()).constructor as typeof FormData
  })
  beforeEach(() => {
    vi.stubGlobal('FormData', NodeFormData)
    vi.stubGlobal('File', NodeFile)
  })

  it('uploads a CSV and shows the summary, the new categories and every row result', async () => {
    const api = importHandlers(report)
    server.use(...authHandlers(admin).handlers, ...api.handlers)
    const { user } = renderRoute('/admin/import')

    await user.upload(await screen.findByLabelText('CSV file'), csv('name,sku\nShoes,rs-001\n'))
    await user.click(screen.getByRole('button', { name: 'Import' }))

    const summary = await screen.findByRole('region', { name: 'Import summary' })
    expect(within(summary).getByText('Created').nextSibling).toHaveTextContent('1')
    expect(within(summary).getByText('Failed').nextSibling).toHaveTextContent('1')
    expect(within(summary).getByText('Blank rows').nextSibling).toHaveTextContent('2')
    expect(screen.getByText('New categories: Footwear')).toBeInTheDocument()
    const failed = screen.getByRole('row', { name: /HD-099/ })
    expect(within(failed).getByText('failed')).toBeInTheDocument()
    expect(within(failed).getByText('name: is required')).toBeInTheDocument()
    expect(within(failed).getByText('price: must be a non-negative decimal number with at most 2 decimals')).toBeInTheDocument()
    expect(screen.getByRole('row', { name: /RS-001/ })).toBeInTheDocument()
    expect(api.uploads).toEqual([{ name: 'products.csv', content: 'name,sku\nShoes,rs-001\n' }])
  })
  it('shows why the API rejected the whole file next to the file field', async () => {
    const message = 'The CSV header is missing the columns: price, stock.'
    server.use(
      http.post('/api/admin/products/import', () => HttpResponse.json({ message, errors: { file: [message] } }, { status: 422 })),
      ...authHandlers(admin).handlers,
    )
    const { user } = renderRoute('/admin/import')

    await user.upload(await screen.findByLabelText('CSV file'), csv('name,sku\n'))
    await user.click(screen.getByRole('button', { name: 'Import' }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(screen.getByLabelText('CSV file')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.queryByRole('region', { name: 'Import summary' })).not.toBeInTheDocument()
  })
  it('suggests the categories an import created in the product form right away', async () => {
    const categories = ['Kitchen']
    const api = importHandlers(report)
    server.use(
      http.get('/api/categories', () => HttpResponse.json({ data: [...categories] })),
      http.post('/api/admin/products/import', async (info) => {
        categories.push('Footwear')
        return (await api.handler.run(info))?.response
      }),
      ...authHandlers(admin).handlers,
    )
    const { user, router } = renderRoute('/admin/products/new')
    const suggestions = () => Array.from(document.querySelectorAll('#category-options option'), (option) => (option as HTMLOptionElement).value)
    await waitFor(() => expect(suggestions()).toEqual(['Kitchen']))

    await act(() => router.navigate('/admin/import'))
    await user.upload(await screen.findByLabelText('CSV file'), csv('name,sku\nShoes,rs-001\n'))
    await user.click(screen.getByRole('button', { name: 'Import' }))
    await screen.findByRole('region', { name: 'Import summary' })
    await act(() => router.navigate('/admin/products/new'))

    await waitFor(() => expect(suggestions()).toEqual(['Kitchen', 'Footwear']))
  })
  it('is reachable from the admin header', async () => {
    server.use(...authHandlers(admin).handlers, http.get('/api/products', () => HttpResponse.json({ data: [], meta: { current_page: 1, per_page: 12, total: 0, last_page: 1 } })))
    const { user, router } = renderRoute('/admin')

    await user.click(await screen.findByRole('link', { name: 'Import CSV' }))

    expect(await screen.findByRole('heading', { name: 'Import products' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/admin/import')
  })
  it('shows a general problem when the import fails for a server reason', async () => {
    server.use(http.post('/api/admin/products/import', () => HttpResponse.json({ message: 'Server Error' }, { status: 500 })), ...authHandlers(admin).handlers)
    const { user } = renderRoute('/admin/import')

    await user.upload(await screen.findByLabelText('CSV file'), csv('name,sku\n'))
    await user.click(screen.getByRole('button', { name: 'Import' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('The import could not be completed. Please try again.')
    expect(screen.getByRole('button', { name: 'Import' })).toBeEnabled()
  })

  it('shows script payloads from the file as plain text', async () => {
    const payload = '<img src=x onerror="window.hacked=true">'
    const api = importHandlers({
      summary: { created: 0, updated: 0, unchanged: 0, failed: 1, blank_rows: 0 },
      new_categories: [],
      rows: [{ row: 2, sku: payload, status: 'failed', errors: { sku: ['must contain only letters A-Z, digits, dashes, underscores and dots'] }, note: payload }],
    })
    server.use(...authHandlers(admin).handlers, ...api.handlers)
    const { user } = renderRoute('/admin/import')

    await user.upload(await screen.findByLabelText('CSV file'), csv('x'))
    await user.click(screen.getByRole('button', { name: 'Import' }))

    const row = await screen.findByRole('row', { name: /onerror/ })
    expect(within(row).getAllByText(payload)).toHaveLength(2)
    expect(row.querySelector('img')).toBeNull()
  })
  it('clears the previous report when another file is chosen', async () => {
    server.use(...authHandlers(admin).handlers, ...importHandlers(report).handlers)
    const { user } = renderRoute('/admin/import')

    await user.upload(await screen.findByLabelText('CSV file'), csv('first'))
    await user.click(screen.getByRole('button', { name: 'Import' }))
    await screen.findByRole('region', { name: 'Import summary' })
    await user.upload(screen.getByLabelText('CSV file'), csv('second'))

    expect(screen.queryByRole('region', { name: 'Import summary' })).not.toBeInTheDocument()
  })
})
