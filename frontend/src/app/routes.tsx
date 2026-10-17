import type { RouteObject } from 'react-router'
import { StoreLayout } from './layouts/StoreLayout'
import { NotFoundPage } from './pages/NotFoundPage'

export const routes: RouteObject[] = [
  {
    element: <StoreLayout />,
    children: [
      { index: true, element: null },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
