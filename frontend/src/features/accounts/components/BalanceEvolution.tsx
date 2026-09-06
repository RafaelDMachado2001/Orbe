import {
  Area,
  AreaChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  type TooltipProps,
} from 'recharts'

import { usePrivacy } from '@/app/usePrivacy'
import { Card, CardHeader } from '@/components/ui/Card'
import { cn } from '@/lib/cn'
import { capitalize, formatBRL, formatBRLCompact, formatCompetence } from '@/lib/format'
import type { BalancePoint } from '@/types/api'

/**
 * Evolução do saldo consolidado.
 *
 * Uma linha, não barras: o saldo é um estoque, e o que interessa é a direção em
 * que ele anda. O eixo Y não começa em zero à força — quem tem saldo alto e
 * estável veria uma reta achatada e não perceberia a queda do mês.
 */
export function BalanceEvolution({
  data,
  isRefreshing,
}: {
  data: BalancePoint[]
  isRefreshing: boolean
}) {
  const { isPrivate } = usePrivacy()

  const first = data[0]?.balance ?? 0
  const last = data[data.length - 1]?.balance ?? 0
  const change = last - first
  const hasAnyBalance = data.some((point) => point.balance !== 0)

  return (
    <Card className="flex h-full flex-col p-[20px_22px_16px]">
      <CardHeader
        title="Evolução do saldo"
        subtitle={
          hasAnyBalance
            ? `${change >= 0 ? 'Subiu' : 'Caiu'} ${formatBRLCompact(Math.abs(change))} nos últimos ${data.length} meses`
            : 'Cadastre uma conta para ver o saldo caminhar'
        }
        className="mb-[22px]"
      />

      <div
        className={cn(
          'min-h-[200px] w-full flex-1 transition-opacity',
          isRefreshing && 'opacity-60',
          isPrivate && 'privacy-blur',
        )}
      >
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={data} margin={{ top: 4, right: 4, bottom: 0, left: 0 }}>
            <defs>
              <linearGradient id="balanceArea" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#35D68A" stopOpacity={0.34} />
                <stop offset="100%" stopColor="#35D68A" stopOpacity={0.02} />
              </linearGradient>
            </defs>

            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={{ stroke: 'rgba(255,255,255,0.07)' }}
              tick={{ fontSize: 11, fontWeight: 600, fill: '#6E7681' }}
              tickFormatter={(value: string) => capitalize(value.replace('.', ''))}
            />

            <YAxis
              width={54}
              tickLine={false}
              axisLine={false}
              tick={{ fontSize: 10.5, fontWeight: 600, fill: '#4A525E' }}
              tickFormatter={(value: number) => formatBRLCompact(value).replace('R$ ', '')}
            />

            <Tooltip cursor={{ stroke: 'rgba(255,255,255,0.12)' }} content={<BalanceTooltip />} />

            <Area
              type="monotone"
              dataKey="balance"
              stroke="#35D68A"
              strokeWidth={2}
              fill="url(#balanceArea)"
              dot={{ r: 2.5, fill: '#0F1216', stroke: '#35D68A', strokeWidth: 2 }}
              activeDot={{ r: 4, fill: '#35D68A', stroke: '#04140C', strokeWidth: 2 }}
              isAnimationActive={false}
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
    </Card>
  )
}

function BalanceTooltip({ active, payload }: TooltipProps<number, string>) {
  if (!active || !payload || payload.length === 0) {
    return null
  }

  const point = payload[0]?.payload as BalancePoint | undefined

  if (!point) {
    return null
  }

  return (
    <div className="rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-2.5 shadow-xl">
      <p className="mb-1 text-[11.5px] font-bold text-ink">
        {capitalize(formatCompetence(point.month))}
      </p>
      <p className="flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Saldo no fim do mês</span>
        <span
          className={cn(
            'font-mono font-semibold',
            point.balance >= 0 ? 'text-green-bright' : 'text-orange-light',
          )}
        >
          {formatBRL(point.balance)}
        </span>
      </p>
    </div>
  )
}

export function BalanceEvolutionSkeleton() {
  return (
    <Card className="flex h-full flex-col gap-4 p-[20px_22px_16px]">
      <div className="flex flex-col gap-2">
        <div className="skeleton h-3 w-36 rounded-lg" />
        <div className="skeleton h-2.5 w-52 rounded-lg" />
      </div>
      <div className="skeleton min-h-[200px] flex-1 rounded-[14px]" />
    </Card>
  )
}
