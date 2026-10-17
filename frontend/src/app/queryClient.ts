import { MutationCache, QueryCache, QueryClient, type DefaultOptions } from '@tanstack/react-query'
import { currentUserKey } from '@/features/auth/hooks/useAuth'
import { ApiError } from '@/shared/api/http'

const UNAUTHENTICATED = 401

export function createQueryClient(defaultOptions: DefaultOptions = {}): QueryClient {
  const signOutOnExpiredSession = (error: unknown) => {
    if (isExpiredSession(error)) {
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
