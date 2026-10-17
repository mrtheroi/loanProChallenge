import { type QueryClient, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { removePrivateQueries } from '@/shared/api/privateQueries'
import { fetchCurrentUser, login, logout, register, type User } from '../api/authApi'

export const currentUserKey = ['auth', 'me'] as const

async function signIn(queryClient: QueryClient, user: User): Promise<void> {
  await queryClient.cancelQueries({ queryKey: currentUserKey })
  removePrivateQueries(queryClient)
  queryClient.setQueryData(currentUserKey, user)
}

export function useCurrentUser() {
  return useQuery({ queryKey: currentUserKey, queryFn: fetchCurrentUser, staleTime: 60_000, refetchOnWindowFocus: 'always' })
}

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: login,
    onSuccess: (user: User) => signIn(queryClient, user),
  })
}

export function useRegister() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: register,
    onSuccess: (user: User) => signIn(queryClient, user),
  })
}

export function useLogout() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      queryClient.clear()
      queryClient.setQueryData(currentUserKey, null)
    },
  })
}
