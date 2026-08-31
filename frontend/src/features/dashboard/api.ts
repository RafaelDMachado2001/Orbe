import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query'

import { api } from '@/lib/api'
import type { Dashboard, ResourceEnvelope } from '@/types/api'

export async function fetchDashboard(month?: string): Promise<Dashboard> {
  const { data } = await api.get<ResourceEnvelope<Dashboard>>('/dashboard', {
    params: month ? { month } : undefined,
  })

  return data.data
}

export const dashboardKeys = {
  all: ['dashboard'] as const,
  month: (month?: string) => ['dashboard', month ?? 'atual'] as const,
}

export function useDashboard(month?: string): UseQueryResult<Dashboard, Error> {
  return useQuery({
    queryKey: dashboardKeys.month(month),
    queryFn: () => fetchDashboard(month),
    // Trocar de mes reaproveita a tela atual ate a nova resposta chegar, em vez
    // de devolver o balanco inteiro ao esqueleto a cada clique.
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  })
}
