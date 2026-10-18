import type { QueryClient } from '@tanstack/react-query'

const PRIVATE_QUERY_ROOTS = ['orders', 'order'] as const

export function removePrivateQueries(client: QueryClient): void {
  for (const root of PRIVATE_QUERY_ROOTS) client.removeQueries({ queryKey: [root] })
}
