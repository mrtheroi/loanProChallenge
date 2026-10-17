import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import type { Role } from '../api/authApi'
import { useCurrentUser } from '../hooks/useAuth'

type RequireRoleProps = { role: Role; children: ReactNode }

export function RequireRole({ role, children }: RequireRoleProps) {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) {
    return <p className="p-8 text-muted-foreground">Loading…</p>
  }

  if (!user) {
    const redirect = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/login?redirect=${redirect}`} replace />
  }

  if (user.role !== role) {
    return (
      <section className="mx-auto max-w-6xl px-4 py-8">
        <h1 className="font-heading text-2xl font-semibold">Access denied</h1>
        <p className="text-muted-foreground">Your account cannot open this page.</p>
      </section>
    )
  }

  return children
}
