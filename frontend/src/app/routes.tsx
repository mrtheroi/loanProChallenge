import type { RouteObject } from 'react-router'
import { LoginContainer } from '@/features/auth/containers/LoginContainer'
import { CartContainer } from '@/features/cart/containers/CartContainer'
import { CatalogContainer } from '@/features/catalog/containers/CatalogContainer'
import { ProductDetailContainer } from '@/features/catalog/containers/ProductDetailContainer'
import { RegisterContainer } from '@/features/auth/containers/RegisterContainer'
import { RequireRole } from '@/features/auth/containers/RequireRole'
import { CheckoutContainer } from '@/features/checkout/containers/CheckoutContainer'
import { OrderDetailContainer } from '@/features/orders/containers/OrderDetailContainer'
import { OrdersListContainer } from '@/features/orders/containers/OrdersListContainer'
import { AdminLayout } from './layouts/AdminLayout'
import { StoreLayout } from './layouts/StoreLayout'
import { AdminHomePage } from './pages/AdminHomePage'
import { NotFoundPage } from './pages/NotFoundPage'

export const routes: RouteObject[] = [
  {
    path: '/admin',
    element: <AdminLayout />,
    children: [{ index: true, element: <AdminHomePage /> }],
  },
  {
    element: <StoreLayout />,
    children: [
      { index: true, element: <CatalogContainer /> },
      { path: 'products/:sku', element: <ProductDetailContainer /> },
      { path: 'cart', element: <CartContainer /> },
      { path: 'checkout', element: <RequireRole role="buyer"><CheckoutContainer /></RequireRole> },
      { path: 'orders', element: <RequireRole role="buyer"><OrdersListContainer /></RequireRole> },
      { path: 'orders/:id', element: <RequireRole role="buyer"><OrderDetailContainer /></RequireRole> },
      { path: 'login', element: <LoginContainer /> },
      { path: 'register', element: <RegisterContainer /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
