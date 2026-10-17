import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { createQueryClient } from '@/app/queryClient'
import { routes } from '@/app/routes'

export function renderRoute(path: string) {
  const queryClient = createQueryClient({ queries: { retry: false }, mutations: { retry: false } })
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  const user = userEvent.setup()

  const view = render(
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )

  return { ...view, router, user }
}
