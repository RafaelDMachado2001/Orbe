import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { Skeleton } from '@/components/ui/Skeleton'
import { useAuth } from '@/features/auth/useAuth'

/** Enquanto a sessao e reconfirmada com a API, mostramos o esqueleto da tela. */
export function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="flex min-h-screen bg-app">
        <div className="w-[248px] shrink-0 border-r border-hairline p-[26px_18px]">
          <Skeleton className="h-[34px] w-[34px] rounded-[11px]" />
          <div className="mt-8 flex flex-col gap-2.5">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-4 w-full" />
            ))}
          </div>
        </div>
        <div className="flex-1 p-[26px_30px]">
          <Skeleton className="h-6 w-56" />
          <div className="mt-6 grid grid-cols-4 gap-3.5">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-[118px] rounded-panel" />
            ))}
          </div>
        </div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/entrar" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}
