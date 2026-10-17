import { Link } from 'react-router'
import { isExpiredSession } from '@/app/queryClient'
import { useCartStore } from '@/features/cart/cartStore'
import { settlePayment } from '@/features/checkout/idempotency'
import { Button } from '@/shared/ui/button'
import { useCurrentUser, useLogout } from '../hooks/useAuth'

function forgetLocalData() {
  useCartStore.getState().clear()
  settlePayment()
}

export function AuthMenu() {
  const { data: user, isPending, isError, refetch } = useCurrentUser()
  const logout = useLogout()

  if (isPending) return null

  if (isError && user === undefined) {
    return (
      <Button variant="outline" onClick={() => void refetch()}>
        Retry session check
      </Button>
    )
  }

  if (!user) {
    return (
      <nav className="flex items-center gap-2">
        <Button asChild variant="ghost">
          <Link to="/login">Log in</Link>
        </Button>
        <Button asChild>
          <Link to="/register">Register</Link>
        </Button>
      </nav>
    )
  }

  return (
    <nav className="flex items-center gap-3">
      {user.role === 'buyer' && (
        <Button asChild variant="ghost">
          <Link to="/orders">My orders</Link>
        </Button>
      )}
      {user.role === 'admin' && (
        <Button asChild variant="ghost">
          <Link to="/admin">Admin</Link>
        </Button>
      )}
      <span className="text-sm">{user.name}</span>
      <Button variant="outline" disabled={logout.isPending} onClick={() => logout.mutate(undefined, { onSuccess: forgetLocalData, onError: (error) => isExpiredSession(error) && forgetLocalData() })}>
        Log out
      </Button>
      {logout.isError && !isExpiredSession(logout.error) && (
        <p role="alert" className="text-sm text-destructive">
          We could not log you out. Please try again.
        </p>
      )}
    </nav>
  )
}
