import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { authHandlers } from '@/test/auth-handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('routes', () => {
  it('renders the store layout with its brand on the home page', async () => {
    server.use(...authHandlers(null).handlers)
    renderRoute('/')

    expect(await screen.findByRole('link', { name: 'LoanPro Store' })).toHaveAttribute('href', '/')
    expect(screen.getByRole('main')).toBeInTheDocument()
  })

  it('shows a not found page for unknown paths', async () => {
    server.use(...authHandlers(null).handlers)
    renderRoute('/does-not-exist')

    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to the store' })).toHaveAttribute('href', '/')
  })
})
