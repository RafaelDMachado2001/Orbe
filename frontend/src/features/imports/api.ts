import {
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
  CsvMapping,
  ImportBatch,
  ImportPreview,
  MovementDirection,
  ResourceEnvelope,
} from '@/types/api'

export interface PreviewPayload {
  file: File
  accountId: number | null
  creditCardId: number | null
  /** Só na segunda ida, quando o usuário corrige o palpite do parser. */
  mapping?: CsvMapping | null
}

export interface CommitRow {
  date: string
  description: string
  amount: number
  direction: MovementDirection
  category_id: number | null
}

export interface CommitPayload {
  filename: string
  format: string
  account_id: number | null
  credit_card_id: number | null
  skipped_count: number
  rows: CommitRow[]
}

export const importKeys = {
  all: ['imports'] as const,
  history: ['imports', 'history'] as const,
}

export function useImportHistory(): UseQueryResult<ImportBatch[], Error> {
  return useQuery({
    queryKey: importKeys.history,
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<ImportBatch[]>>('/imports')

      return data.data
    },
    staleTime: 30_000,
  })
}

/**
 * Lê o arquivo e devolve o que ele traz. Não grava nada — por isso é uma
 * mutation sem invalidação: nenhuma outra tela mudou.
 */
export function usePreviewImport(): UseMutationResult<ImportPreview, Error, PreviewPayload> {
  return useMutation({
    mutationFn: async ({ file, accountId, creditCardId, mapping }) => {
      const form = new FormData()

      form.append('file', file)

      if (accountId !== null) {
        form.append('account_id', String(accountId))
      }

      if (creditCardId !== null) {
        form.append('credit_card_id', String(creditCardId))
      }

      if (mapping) {
        form.append('mapping[date_column]', String(mapping.date_column))
        form.append('mapping[description_column]', String(mapping.description_column))
        form.append('mapping[amount_column]', String(mapping.amount_column))
        form.append('mapping[delimiter]', mapping.delimiter)
        form.append('mapping[has_header]', mapping.has_header ? '1' : '0')

        if (mapping.inflow_column !== null) {
          form.append('mapping[inflow_column]', String(mapping.inflow_column))
        }
      }

      const { data } = await api.post<ResourceEnvelope<ImportPreview>>('/imports/preview', form, {
        // O cliente manda JSON por padrão; para multipart o axios precisa
        // escrever o próprio cabeçalho, com o boundary que ele gera.
        headers: { 'Content-Type': undefined },
      })

      return data.data
    },
  })
}

/**
 * Gravar um extrato mexe em tudo o que soma dinheiro: extrato, visão geral e,
 * quando o destino é cartão, faturas e limite.
 */
function useImportInvalidation(): () => Promise<void> {
  const queryClient = useQueryClient()

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: importKeys.all }),
      queryClient.invalidateQueries({ queryKey: transactionKeys.all }),
      queryClient.invalidateQueries({ queryKey: dashboardKeys.all }),
      queryClient.invalidateQueries({ queryKey: cardKeys.all }),
      queryClient.invalidateQueries({ queryKey: invoiceKeys.all }),
    ])
  }
}

export function useCommitImport(): UseMutationResult<ImportBatch, Error, CommitPayload> {
  const invalidate = useImportInvalidation()

  return useMutation({
    mutationFn: async (payload) => {
      const { data } = await api.post<ResourceEnvelope<ImportBatch>>('/imports', payload)

      return data.data
    },
    onSuccess: invalidate,
  })
}

export function useUndoImport(): UseMutationResult<void, Error, number> {
  const invalidate = useImportInvalidation()

  return useMutation({
    mutationFn: async (id) => {
      await api.delete(`/imports/${id}`)
    },
    onSuccess: invalidate,
  })
}
