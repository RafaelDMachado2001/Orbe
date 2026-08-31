import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { dashboardKeys } from '@/features/dashboard/api'
import { recurrenceKeys } from '@/features/recurrences/api'
import { transactionKeys } from '@/features/transactions/api'
import { api } from '@/lib/api'
import type { CategoriesPage, CategoryRecord, CategoryType, ResourceEnvelope } from '@/types/api'

export interface CategoryPayload {
  name: string
  type: CategoryType
  color: string
  icon: string | null
  parent_id: number | null
}

export const categoryKeys = {
  all: ['categories'] as const,
  page: (month: string) => ['categories', 'page', month] as const,
}

export function useCategories(month: string): UseQueryResult<CategoriesPage, Error> {
  return useQuery({
    queryKey: categoryKeys.page(month),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<CategoriesPage>>('/categories', {
        params: { month },
      })

      return data.data
    },
    // Trocar o mês reaproveita a árvore atual até a nova chegar.
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

/**
 * Categoria é o rótulo de tudo: renomear uma muda o extrato, a Visão geral e a
 * lista de despesas fixas na mesma hora. Por isso a invalidação sai desta tela
 * e alcança as outras — inclusive as options do formulário de lançamentos.
 */
function useCategoryInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: categoryKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: recurrenceKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateCategory(): UseMutationResult<CategoryRecord, Error, CategoryPayload> {
  const invalidate = useCategoryInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<CategoryRecord>>('/categories', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateCategory(): UseMutationResult<
  CategoryRecord,
  Error,
  { id: number; payload: CategoryPayload }
> {
  const invalidate = useCategoryInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<CategoryRecord>>(`/categories/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteCategory(): UseMutationResult<
  void,
  Error,
  { id: number; reassignTo: number | null }
> {
  const invalidate = useCategoryInvalidation()

  return useMutation({
    mutationFn: async ({ id, reassignTo }) => {
      await api.delete(`/categories/${id}`, {
        data: reassignTo === null ? {} : { reassign_to: reassignTo },
      })
    },
    onSuccess: invalidate,
  })
}
