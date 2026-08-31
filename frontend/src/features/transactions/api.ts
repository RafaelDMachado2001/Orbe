import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { dashboardKeys } from '@/features/dashboard/api'
import { api } from '@/lib/api'
import type {
  AmountMode,
  EntryKind,
  MovementOrigin,
  ResourceEnvelope,
  TransactionForm,
  TransactionOptions,
  TransactionPage,
  TransactionStatus,
} from '@/types/api'

export interface TransactionFilters {
  from: string
  to: string
  origins: MovementOrigin[]
  types: string[]
  statuses: string[]
  categories: number[]
  accountId: number | null
  creditCardId: number | null
  search: string
  page: number
  perPage: number
}

/** Corpo aceito por criar e editar; os campos variam conforme a aba. */
export interface TransactionPayload {
  kind?: EntryKind
  description: string
  amount: number
  competence_date: string
  status?: TransactionStatus
  notes?: string | null
  category_id?: number | null
  account_id?: number
  method?: string | null
  paid_date?: string | null
  credit_card_id?: number
  installments?: number
  /** Diz se `amount` é o total do parcelamento ou o de cada prestação. */
  amount_mode?: AmountMode
  lender?: string
  from_account_id?: number
  to_account_id?: number
}

/**
 * Só o que o usuário realmente escolheu vai para a URL. Mandar `types=[]`
 * faria a API entender "nenhum tipo" em vez de "todos".
 */
function toParams(filters: TransactionFilters): Record<string, unknown> {
  return {
    from: filters.from,
    to: filters.to,
    page: filters.page,
    per_page: filters.perPage,
    ...(filters.origins.length > 0 && { origins: filters.origins }),
    ...(filters.types.length > 0 && { types: filters.types }),
    ...(filters.statuses.length > 0 && { statuses: filters.statuses }),
    ...(filters.categories.length > 0 && { categories: filters.categories }),
    ...(filters.accountId !== null && { account_id: filters.accountId }),
    ...(filters.creditCardId !== null && { credit_card_id: filters.creditCardId }),
    ...(filters.search.trim() !== '' && { search: filters.search.trim() }),
  }
}

export const transactionKeys = {
  all: ['transactions'] as const,
  list: (filters: TransactionFilters) => ['transactions', 'list', filters] as const,
  detail: (id: number) => ['transactions', 'detail', id] as const,
  options: ['transactions', 'options'] as const,
}

export async function fetchTransactions(filters: TransactionFilters): Promise<TransactionPage> {
  const { data } = await api.get<ResourceEnvelope<TransactionPage>>('/transactions', {
    params: toParams(filters),
  })

  return data.data
}

/**
 * `keepPreviousData` é o que dá o "hot reload": ao trocar uma data ou marcar
 * um filtro, a lista e os totais anteriores continuam na tela e são
 * substituídos quando a resposta chega. Sem isso, cada mudança devolveria a
 * página ao esqueleto e pareceria um recarregamento.
 */
export function useTransactions(filters: TransactionFilters): UseQueryResult<TransactionPage, Error> {
  return useQuery({
    queryKey: transactionKeys.list(filters),
    queryFn: () => fetchTransactions(filters),
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useTransactionOptions(): UseQueryResult<TransactionOptions, Error> {
  return useQuery({
    queryKey: transactionKeys.options,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<TransactionOptions>>('/transactions/options')

      return data.data
    },
    // Contas, cartões e categorias mudam em outras telas, não nesta.
    staleTime: 5 * 60_000,
  })
}

/** Carrega o lançamento inteiro para preencher o formulário de edição. */
export function useTransaction(id: number | null): UseQueryResult<TransactionForm, Error> {
  return useQuery({
    queryKey: transactionKeys.detail(id ?? 0),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<TransactionForm>>(`/transactions/${id}`)

      return data.data
    },
    enabled: id !== null,
    staleTime: 0,
  })
}

/**
 * Toda escrita reflete em duas telas: o extrato e a Visão geral, que resume os
 * mesmos lançamentos. Invalidar as duas chaves faz o dashboard se refazer
 * sozinho na próxima vez que aparecer, sem ninguém recarregar nada.
 */
function useLedgerInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateTransaction(): UseMutationResult<TransactionForm, Error, TransactionPayload> {
  const invalidate = useLedgerInvalidation()

  return useMutation({
    mutationFn: async (payload: TransactionPayload) => {
      const { data } = await api.post<ResourceEnvelope<TransactionForm>>('/transactions', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateTransaction(): UseMutationResult<
  TransactionForm,
  Error,
  { id: number; payload: TransactionPayload }
> {
  const invalidate = useLedgerInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<TransactionForm>>(`/transactions/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteTransaction(): UseMutationResult<void, Error, number> {
  const invalidate = useLedgerInvalidation()

  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/transactions/${id}`)
    },
    onSuccess: invalidate,
  })
}

export function useChangeTransactionStatus(): UseMutationResult<
  TransactionForm,
  Error,
  { id: number; status: TransactionStatus }
> {
  const invalidate = useLedgerInvalidation()

  return useMutation({
    mutationFn: async ({ id, status }) => {
      const { data } = await api.patch<ResourceEnvelope<TransactionForm>>(
        `/transactions/${id}/status`,
        { status },
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}
