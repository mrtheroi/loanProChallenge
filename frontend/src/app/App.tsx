import { QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { createQueryClient, shouldRetry } from './queryClient'
import { routes } from './routes'

const queryClient = createQueryClient({ queries: { staleTime: 30_000, refetchOnWindowFocus: false, retry: shouldRetry } })

const router = createBrowserRouter(routes)

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
