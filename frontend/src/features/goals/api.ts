import {
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { accountKeys } from '@/features/accounts/api'
import { dashboardKeys } from '@/features/dashboard/api'
import { transactionKeys } from '@/features/transactions/api'
import { api } from '@/lib/api'
import type { GoalContribution, GoalRecord, ResourceEnvelope } from '@/types/api'

export interface GoalPayload {
  name: string
  target_amount: number
  deadline: string | null
  account_id: number | null
}

export interface ContributionPayload {
  amount: number
  contributed_at: string
  source_account_id: number | null
}

export const goalKeys = {
  all: ['goals'] as const,
  contributions: (goalId: number) => ['goals', goalId, 'contributions'] as const,
}

export function useGoals(): UseQueryResult<GoalRecord[], Error> {
  return useQuery({
    queryKey: goalKeys.all,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<GoalRecord[]>>('/goals')

      return data.data
    },
    staleTime: 15000,
  })
}

export function useGoalContributions(
  goalId: number,
  enabled: boolean,
): UseQueryResult<GoalContribution[], Error> {
  return useQuery({
    queryKey: goalKeys.contributions(goalId),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<GoalContribution[]>>(
        `/goals/${goalId}/contributions`,
      )

      return data.data
    },
    enabled,
  })
}

/**
 * Meta some do dashboard tambem (faixa de "Meta reserva"), e um aporte com
 * conta vinculada gera uma transferencia real — alcanca contas e lancamentos.
 */
function useGoalInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: goalKeys.all }),
      queryClient.invalidateQueries({ queryKey: accountKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateGoal(): UseMutationResult<
  GoalRecord,
  Error,
  GoalPayload & { initial_amount: number }
> {
  const invalidate = useGoalInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<GoalRecord>>('/goals', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateGoal(): UseMutationResult<
  GoalRecord,
  Error,
  { id: number; payload: GoalPayload }
> {
  const invalidate = useGoalInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<GoalRecord>>(`/goals/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useArchiveGoal(): UseMutationResult<
  GoalRecord,
  Error,
  { id: number; isArchived: boolean }
> {
  const invalidate = useGoalInvalidation()

  return useMutation({
    mutationFn: async ({ id, isArchived }) => {
      const { data } = await api.patch<ResourceEnvelope<GoalRecord>>(`/goals/${id}/archive`, {
        is_archived: isArchived,
      })

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteGoal(): UseMutationResult<void, Error, number> {
  const invalidate = useGoalInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/goals/${id}`)
    },
    onSuccess: invalidate,
  })
}

export function useAddContribution(): UseMutationResult<
  GoalRecord,
  Error,
  { goalId: number; payload: ContributionPayload }
> {
  const invalidate = useGoalInvalidation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ goalId, payload }) => {
      const { data } = await api.post<ResourceEnvelope<GoalRecord>>(
        `/goals/${goalId}/contributions`,
        payload,
      )

      return data.data
    },
    onSuccess: async (_data, { goalId }) => {
      await Promise.all([
        invalidate(),
        queryClient.invalidateQueries({ queryKey: goalKeys.contributions(goalId) }),
      ])
    },
  })
}

export function useDeleteContribution(): UseMutationResult<
  void,
  Error,
  { goalId: number; contributionId: number }
> {
  const invalidate = useGoalInvalidation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async ({ goalId, contributionId }) => {
      await api.delete(`/goals/${goalId}/contributions/${contributionId}`)
    },
    onSuccess: async (_data, { goalId }) => {
      await Promise.all([
        invalidate(),
        queryClient.invalidateQueries({ queryKey: goalKeys.contributions(goalId) }),
      ])
    },
  })
}
