import { Link, Outlet } from 'react-router'
import { AuthMenu } from '@/features/auth/containers/AuthMenu'
import { CartLink } from '@/features/cart/components/CartLink'

export function StoreLayout() {
  return (
    <div className="min-h-svh bg-background text-foreground">
      <header className="border-b">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
          <Link to="/" className="font-heading text-lg font-semibold">
            LoanPro Store
          </Link>
          <div className="flex items-center gap-2">
            <CartLink />
            <AuthMenu />
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
