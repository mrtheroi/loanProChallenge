import { QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { createQueryClient } from './queryClient'
import { routes } from './routes'

const queryClient = createQueryClient({ queries: { staleTime: 30_000, refetchOnWindowFocus: false } })

const router = createBrowserRouter(routes)

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
