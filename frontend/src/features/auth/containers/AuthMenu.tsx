import { Link } from 'react-router'
import { Button } from '@/shared/ui/button'
import { useCurrentUser, useLogout } from '../hooks/useAuth'

export function AuthMenu() {
  const { data: user, isPending } = useCurrentUser()
  const logout = useLogout()

  if (isPending) return null

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
      {user.role === 'admin' && (
        <Button asChild variant="ghost">
          <Link to="/admin">Admin</Link>
        </Button>
      )}
      <span className="text-sm">{user.name}</span>
      <Button variant="outline" disabled={logout.isPending} onClick={() => logout.mutate()}>
        Log out
      </Button>
    </nav>
  )
}
