import { createBrowserRouter, Navigate } from 'react-router-dom'

import { LoginPage } from '@/features/auth/LoginPage'
import { RegisterPage } from '@/features/auth/RegisterPage'
import { CardsPage } from '@/features/cards/CardsPage'
import { CategoriesPage } from '@/features/categories/CategoriesPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { ImportPage } from '@/features/imports/ImportPage'
import { RecurrencesPage } from '@/features/recurrences/RecurrencesPage'
import { TransactionsPage } from '@/features/transactions/TransactionsPage'

import { ProtectedRoute } from './ProtectedRoute'

export const router = createBrowserRouter([
  { path: '/entrar', element: <LoginPage /> },
  { path: '/registrar', element: <RegisterPage /> },
  {
    element: <ProtectedRoute />,
    children: [
      { path: '/', element: <DashboardPage /> },
      { path: '/lancamentos', element: <TransactionsPage /> },
      { path: '/cartoes', element: <CardsPage /> },
      { path: '/categorias', element: <CategoriesPage /> },
      { path: '/recorrencias', element: <RecurrencesPage /> },
      { path: '/importar', element: <ImportPage /> },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
])
