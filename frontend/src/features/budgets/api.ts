import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { dashboardKeys } from '@/features/dashboard/api'
import { api } from '@/lib/api'
import type { BudgetRecord, BudgetStatus, ResourceEnvelope } from '@/types/api'

export interface BudgetPayload {
  category_id: number
  reference_month: string
  limit_amount: number
}

export const budgetKeys = {
  all: ['budgets'] as const,
  page: (month: string) => ['budgets', month] as const,
}

export function useBudgets(month: string): UseQueryResult<BudgetStatus[], Error> {
  return useQuery({
    queryKey: budgetKeys.page(month),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<BudgetStatus[]>>('/budgets', {
        params: { month },
      })

      return data.data
    },
    staleTime: 15000,
  })
}

/**
 * Orcamento aparece no dashboard (alerta de estouro) alem da propria tela, e a
 * invalidacao alcanca os dois quando um limite muda.
 */
function useBudgetInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: budgetKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateBudget(): UseMutationResult<BudgetRecord, Error, BudgetPayload> {
  const invalidate = useBudgetInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<BudgetRecord>>('/budgets', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateBudget(): UseMutationResult<
  BudgetRecord,
  Error,
  { id: number; payload: BudgetPayload }
> {
  const invalidate = useBudgetInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<BudgetRecord>>(`/budgets/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteBudget(): UseMutationResult<void, Error, number> {
  const invalidate = useBudgetInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/budgets/${id}`)
    },
    onSuccess: invalidate,
  })
}
