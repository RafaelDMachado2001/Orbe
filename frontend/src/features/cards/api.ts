import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type UseMutationResult,
  type UseQueryResult,
} from '@tanstack/react-query'

import { dashboardKeys } from '@/features/dashboard/api'
import { transactionKeys } from '@/features/transactions/api'
import { api } from '@/lib/api'
import type {
  CardOptions,
  CardsPage,
  CreditCardRecord,
  InvoiceDetail,
  InvoiceRow,
  ResourceEnvelope,
} from '@/types/api'

export interface CreditCardPayload {
  bank_id: number
  payment_account_id: number | null
  nickname: string
  brand: string
  last_four: string
  limit_amount: number
  closing_day: number
  due_day: number
  color: string
}

export interface PayInvoicePayload {
  account_id: number
  amount: number | null
  paid_at: string | null
}

export const cardKeys = {
  all: ['cards'] as const,
  page: (month: string, archived: boolean) => ['cards', 'page', month, archived] as const,
  options: ['cards', 'options'] as const,
  detail: (id: number) => ['cards', 'detail', id] as const,
  invoices: (cardId: number) => ['cards', 'invoices', cardId] as const,
}

export const invoiceKeys = {
  all: ['invoices'] as const,
  detail: (id: number) => ['invoices', 'detail', id] as const,
}

export function useCards(month: string, includeArchived: boolean): UseQueryResult<CardsPage, Error> {
  return useQuery({
    queryKey: cardKeys.page(month, includeArchived),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<CardsPage>>('/cards', {
        params: { month, ...(includeArchived && { archived: 1 }) },
      })

      return data.data
    },
    // Mostrar/esconder arquivados troca o conjunto sem piscar a tela.
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useCardOptions(): UseQueryResult<CardOptions, Error> {
  return useQuery({
    queryKey: cardKeys.options,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<CardOptions>>('/cards/options')

      return data.data
    },
    staleTime: 5 * 60_000,
  })
}

export function useCard(id: number | null): UseQueryResult<CreditCardRecord, Error> {
  return useQuery({
    queryKey: cardKeys.detail(id ?? 0),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<CreditCardRecord>>(`/cards/${id}`)

      return data.data
    },
    enabled: id !== null,
    staleTime: 0,
  })
}

export function useCardInvoices(cardId: number | null): UseQueryResult<InvoiceRow[], Error> {
  return useQuery({
    queryKey: cardKeys.invoices(cardId ?? 0),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<InvoiceRow[]>>(`/cards/${cardId}/invoices`)

      return data.data
    },
    enabled: cardId !== null,
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

export function useInvoice(id: number | null): UseQueryResult<InvoiceDetail, Error> {
  return useQuery({
    queryKey: invoiceKeys.detail(id ?? 0),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<InvoiceDetail>>(`/invoices/${id}`)

      return data.data
    },
    enabled: id !== null,
    // Trocar de mês na linha do tempo mantém a fatura anterior à vista.
    placeholderData: keepPreviousData,
    staleTime: 15_000,
  })
}

/**
 * Pagar uma fatura debita a conta, então mexe em três telas: Cartões, o
 * extrato de Lançamentos e a Visão geral. Invalidar as quatro chaves faz
 * qualquer uma delas se refazer sozinha na próxima vez que aparecer.
 */
function useCardsInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: cardKeys.all }),
      queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
    ])
  }
}

export function useCreateCard(): UseMutationResult<CreditCardRecord, Error, CreditCardPayload> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<CreditCardRecord>>('/cards', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUpdateCard(): UseMutationResult<
  CreditCardRecord,
  Error,
  { id: number; payload: CreditCardPayload }
> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.put<ResourceEnvelope<CreditCardRecord>>(`/cards/${id}`, payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useArchiveCard(): UseMutationResult<
  CreditCardRecord,
  Error,
  { id: number; isActive: boolean }
> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async ({ id, isActive }) => {
      const { data } = await api.patch<ResourceEnvelope<CreditCardRecord>>(`/cards/${id}/archive`, {
        is_active: isActive,
      })

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useDeleteCard(): UseMutationResult<void, Error, number> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/cards/${id}`)
    },
    onSuccess: invalidate,
  })
}

export function usePayInvoice(): UseMutationResult<
  InvoiceDetail,
  Error,
  { id: number; payload: PayInvoicePayload }
> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async ({ id, payload }) => {
      const { data } = await api.post<ResourceEnvelope<InvoiceDetail>>(
        `/invoices/${id}/payments`,
        payload,
      )

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUndoInvoicePayment(): UseMutationResult<InvoiceDetail, Error, number> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      const { data } = await api.delete<ResourceEnvelope<InvoiceDetail>>(`/invoices/${id}/payments`)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useCloseInvoice(): UseMutationResult<InvoiceDetail, Error, number> {
  const invalidate = useCardsInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      const { data } = await api.post<ResourceEnvelope<InvoiceDetail>>(`/invoices/${id}/close`)

      return data.data
    },
    onSuccess: invalidate,
  })
}
