import type { RouteObject } from 'react-router'
import { LoginContainer } from '@/features/auth/containers/LoginContainer'
import { CartContainer } from '@/features/cart/containers/CartContainer'
import { CatalogContainer } from '@/features/catalog/containers/CatalogContainer'
import { ProductDetailContainer } from '@/features/catalog/containers/ProductDetailContainer'
import { RegisterContainer } from '@/features/auth/containers/RegisterContainer'
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
      { path: 'login', element: <LoginContainer /> },
      { path: 'register', element: <RegisterContainer /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
