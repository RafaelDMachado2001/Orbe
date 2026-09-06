import { useMutation, useQuery, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query'

import { downloadBlob, filenameFromDisposition } from '@/lib/download'
import { api } from '@/lib/api'
import type { AnnualReport, ResourceEnvelope } from '@/types/api'

export const reportKeys = {
  annual: (year: number) => ['reports', 'annual', year] as const,
}

export function useAnnualReport(year: number): UseQueryResult<AnnualReport, Error> {
  return useQuery({
    queryKey: reportKeys.annual(year),
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<AnnualReport>>('/reports/annual', {
        params: { year },
      })

      return data.data
    },
    staleTime: 60_000,
  })
}

export function useExportAnnualReport(): UseMutationResult<
  void,
  Error,
  { year: number; format: 'csv' | 'pdf' }
> {
  return useMutation({
    mutationFn: async ({ year, format }) => {
      const response = await api.get('/reports/annual/export', {
        params: { year, format },
        responseType: 'blob',
      })

      const filename = filenameFromDisposition(response.headers['content-disposition'])
        ?? `relatorio-anual-${year}.${format}`

      downloadBlob(response.data as Blob, filename)
    },
  })
}
