import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { ForecastResult, ResourceEnvelope } from '@/types/api'

export const FORECAST_HORIZON_OPTIONS = [
  { value: '3', label: '3 meses' },
  { value: '6', label: '6 meses' },
  { value: '12', label: '12 meses' },
  { value: '24', label: '24 meses' },
]

export function useForecast(horizon = 6) {
  return useQuery({
    queryKey: ['forecast', horizon],
    queryFn: async () => {
      const { data } = await api.get<ResourceEnvelope<ForecastResult>>('/forecast', {
        params: { horizon },
      })

      return data.data
    },
    staleTime: 30000,
  })
}
