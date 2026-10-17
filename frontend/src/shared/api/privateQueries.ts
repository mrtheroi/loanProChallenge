import type { QueryClient } from '@tanstack/react-query'

const PRIVATE_QUERY_ROOTS = ['orders', 'order'] as const

/** Drops queries that belong to the signed-in buyer whenever the identity changes. */
export function removePrivateQueries(client: QueryClient): void {
  for (const root of PRIVATE_QUERY_ROOTS) client.removeQueries({ queryKey: [root] })
}
