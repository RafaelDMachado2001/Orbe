import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { cardKeys, invoiceKeys } from '@/features/cards/api'
import { dashboardKeys } from '@/features/dashboard/api'
import { transactionKeys } from '@/features/transactions/api'
import { api } from '@/lib/api'
import type {
  RecurrenceOptions,
  RecurrenceRecord,
  RecurrencesPage,
  ResourceEnvelope,
} from '@/types/api'

export interface RecurrencePayload {
  source_kind: 'conta' | 'cartao'
  description: string
  amount: number
  type: 'receita' | 'despesa'
  frequency: string
  interval: number
  day_of_month: number | null
  starts_on: string
  ends_on: string | null
  category_id: number | null
  account_id: number | null
  credit_card_id: number | null
  method: string | null
}

export interface LaunchResult {
  created: number
  message: string
}

export const recurrenceKeys = {
  all: ['recurrences'] as const,
  page: (month: string, includePaused: boolean) =>
    ['recurrences', 'page', month, includePaused] as const,
  detail: (id: number) => ['recurrences', 'detail', id] as const,
  options: ['recurrences', 'options'] as const,
}

export function useRecurrences(
  month: string,
  includePaused: boolean,
): UseQueryResult<RecurrencesPage, Error> {
  return useQuery({
    queryKey: recurrenceKeys.page(month, includePaused),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<RecurrencesPage>>('/recurrences', {
        params: { month, paused: includePaused ? 1 : 0 },
      })

      return data.data
    },
    // Trocar o mês reaproveita a lista atual até a nova chegar.
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useRecurrenceOptions(): UseQueryResult<RecurrenceOptions, Error> {
  return useQuery({
    queryKey: recurrenceKeys.options,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<RecurrenceOptions>>('/recurrences/options')

      return data.data
    },
    staleTime: 5 * 60_000,
  })
}

export function useRecurrence(id: number | null): UseQueryResult<RecurrenceRecord, Error> {
  return useQuery({
    queryKey: recurrenceKeys.detail(id ?? 0),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<RecurrenceRecord>>(`/recurrences/${id}`)

      return data.data
    },
    enabled: id !== null,
    staleTime: 0,
  })
}

/**
 * Lançar uma regra cria lançamento — e, se a regra for de cartão, mexe também
 * na fatura. Por isso a invalidação alcança extrato, cartões e Visão geral,
 * não só esta tela.
 */
function useRecurrenceInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: recurrenceKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: cardKeys.all }),
      queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateRecurrence(): UseMutationResult<RecurrenceRecord, Error, RecurrencePayload> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<RecurrenceRecord>>('/recurrences', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateRecurrence(): UseMutationResult<
  RecurrenceRecord,
  Error,
  { id: number; payload: RecurrencePayload }
> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<RecurrenceRecord>>(
        `/recurrences/${id}`,
        payload,
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useToggleRecurrence(): UseMutationResult<
  RecurrenceRecord,
  Error,
  { id: number; isActive: boolean }
> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async ({ id, isActive }) => {
      const { data } = await api.patch<ResourceEnvelope<RecurrenceRecord>>(
        `/recurrences/${id}/status`,
        { is_active: isActive },
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteRecurrence(): UseMutationResult<void, Error, number> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/recurrences/${id}`)
    },
    onSuccess: invalidate,
  })
}

/** A API devolve a própria frase ("3 lançamentos criados"), que a tela exibe. */
export function useLaunchRecurrence(): UseMutationResult<
  LaunchResult,
  Error,
  { id: number; month: string }
> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async ({ id, month }) => {
      const { data } = await api.post<{ data: { created: number }; message: string }>(
        `/recurrences/${id}/launch`,
        { month },
      )

      return { created: data.data.created, message: data.message }
    },
    onSuccess: invalidate,
  })
}

export function useLaunchAllRecurrences(): UseMutationResult<LaunchResult, Error, string> {
  const invalidate = useRecurrenceInvalidation()

  return useMutation({
    mutationFn: async (month) => {
      const { data } = await api.post<{ data: { created: number }; message: string }>(
        '/recurrences/launch',
        { month },
      )

      return { created: data.data.created, message: data.message }
    },
    onSuccess: invalidate,
  })
}
