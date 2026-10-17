import { MutationCache, QueryCache, QueryClient, type DefaultOptions } from '@tanstack/react-query'
import { currentUserKey } from '@/features/auth/hooks/useAuth'
import { ApiError } from '@/shared/api/http'
import { removePrivateQueries } from '@/shared/api/privateQueries'

const UNAUTHENTICATED = 401
const MAX_RETRIES = 2

export function shouldRetry(failureCount: number, error: unknown): boolean {
  if (error instanceof ApiError && error.status < 500) return false

  return failureCount < MAX_RETRIES
}

export function createQueryClient(defaultOptions: DefaultOptions = {}): QueryClient {
  const signOutOnExpiredSession = (error: unknown) => {
    if (isExpiredSession(error)) {
      removePrivateQueries(client)
      client.setQueryData(currentUserKey, null)
    }
  }

  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({ onError: signOutOnExpiredSession }),
    mutationCache: new MutationCache({ onError: signOutOnExpiredSession }),
    defaultOptions,
  })

  return client
}

export function isExpiredSession(error: unknown): boolean {
  return error instanceof ApiError && error.status === UNAUTHENTICATED
}
