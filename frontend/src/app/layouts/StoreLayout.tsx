import { Link, Outlet } from 'react-router'
import { AuthMenu } from '@/features/auth/containers/AuthMenu'

export function StoreLayout() {
  return (
    <div className="min-h-svh bg-background text-foreground">
      <header className="border-b">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
          <Link to="/" className="font-heading text-lg font-semibold">
            LoanPro Store
          </Link>
          <AuthMenu />
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
