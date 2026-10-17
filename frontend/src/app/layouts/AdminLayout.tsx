import { Link, Outlet } from 'react-router'
import { AuthMenu } from '@/features/auth/containers/AuthMenu'
import { RequireRole } from '@/features/auth/containers/RequireRole'

export function AdminLayout() {
  return (
    <RequireRole role="admin">
      <div className="min-h-svh bg-background text-foreground">
        <header className="border-b bg-muted/40">
          <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
            <div className="flex items-center gap-4">
              <Link to="/admin" className="font-heading text-lg font-semibold">
                LoanPro Admin
              </Link>
              <Link to="/admin/import" className="text-sm text-muted-foreground">
                Import CSV
              </Link>
              <Link to="/" className="text-sm text-muted-foreground">
                View store
              </Link>
            </div>
            <AuthMenu />
          </div>
        </header>
        <main className="mx-auto max-w-6xl px-4 py-8">
          <Outlet />
        </main>
      </div>
    </RequireRole>
  )
}
