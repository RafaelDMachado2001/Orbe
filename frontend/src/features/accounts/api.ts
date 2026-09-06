import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { cardKeys } from '@/features/cards/api'
import { dashboardKeys } from '@/features/dashboard/api'
import { recurrenceKeys } from '@/features/recurrences/api'
import { transactionKeys } from '@/features/transactions/api'
import { api } from '@/lib/api'
import type {
  AccountOptions,
  AccountRecord,
  AccountsPage,
  AccountType,
  BalanceAdjustment,
  BankKind,
  BankRecord,
  ResourceEnvelope,
} from '@/types/api'

export interface AccountPayload {
  bank_id: number
  nickname: string
  type: AccountType
  /** Só no cadastro: a edição não reescreve o ponto de partida do histórico. */
  initial_balance?: number
}

export interface BankPayload {
  name: string
  color: string
  kind: BankKind
}

export interface AdjustBalancePayload {
  balance: number
  date: string | null
  notes: string | null
}

export const accountKeys = {
  all: ['accounts'] as const,
  page: (month: string, archived: boolean) => ['accounts', 'page', month, archived] as const,
  options: ['accounts', 'options'] as const,
  detail: (id: number) => ['accounts', 'detail', id] as const,
}

export function useAccounts(
  month: string,
  includeArchived: boolean,
): UseQueryResult<AccountsPage, Error> {
  return useQuery({
    queryKey: accountKeys.page(month, includeArchived),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<AccountsPage>>('/accounts', {
        params: { month, ...(includeArchived && { archived: 1 }) },
      })

      return data.data
    },
    // Trocar o mês ou mostrar arquivadas reaproveita a lista atual.
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useAccountOptions(): UseQueryResult<AccountOptions, Error> {
  return useQuery({
    queryKey: accountKeys.options,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<AccountOptions>>('/accounts/options')

      return data.data
    },
    staleTime: 5 * 60_000,
  })
}

/**
 * Conta e banco são o alicerce do resto: renomear um banco muda a cor no
 * extrato e nos cartões, e um ajuste de saldo cria um lançamento que aparece
 * na Visão geral. Por isso a invalidação sai desta tela e alcança as outras —
 * inclusive as options dos formulários de lançamento e de cartão.
 */
function useBankingInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: accountKeys.all }),
      queryClient.invalidateQueries({ queryKey: cardKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: recurrenceKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateAccount(): UseMutationResult<AccountRecord, Error, AccountPayload> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<AccountRecord>>('/accounts', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateAccount(): UseMutationResult<
  AccountRecord,
  Error,
  { id: number; payload: AccountPayload }
> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<AccountRecord>>(`/accounts/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useArchiveAccount(): UseMutationResult<
  AccountRecord,
  Error,
  { id: number; isActive: boolean }
> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async ({ id, isActive }) => {
      const { data } = await api.patch<ResourceEnvelope<AccountRecord>>(
        `/accounts/${id}/archive`,
        { is_active: isActive },
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteAccount(): UseMutationResult<void, Error, number> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/accounts/${id}`)
    },
    onSuccess: invalidate,
  })
}

export function useAdjustBalance(): UseMutationResult<
  BalanceAdjustment,
  Error,
  { id: number; payload: AdjustBalancePayload }
> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.post<ResourceEnvelope<BalanceAdjustment>>(
        `/accounts/${id}/adjustments`,
        payload,
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useCreateBank(): UseMutationResult<BankRecord, Error, BankPayload> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<BankRecord>>('/banks', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateBank(): UseMutationResult<
  BankRecord,
  Error,
  { id: number; payload: BankPayload }
> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<BankRecord>>(`/banks/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteBank(): UseMutationResult<void, Error, number> {
  const invalidate = useBankingInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/banks/${id}`)
    },
    onSuccess: invalidate,
  })
}
