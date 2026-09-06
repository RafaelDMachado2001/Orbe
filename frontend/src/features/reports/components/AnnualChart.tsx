import {
  Bar,
  CartesianGrid,
  ComposedChart,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  type TooltipProps,
} from 'recharts'

import { usePrivacy } from '@/app/usePrivacy'
import { Card, CardHeader } from '@/components/ui/Card'
import { cn } from '@/lib/cn'
import { formatBRL, formatBRLCompact, formatCompetence, formatCompetenceShort } from '@/lib/format'
import type { AnnualReport } from '@/types/api'

interface ChartRow {
  month: string
  label: string
  income: number
  expense: number
  balance: number
}

function toRows(report: AnnualReport): ChartRow[] {
  return report.months.map((month, index) => ({
    month: month.month,
    label: formatCompetenceShort(month.month),
    income: month.income,
    expense: month.expense,
    balance: report.balance_series[index] ?? 0,
  }))
}

/** Resultado mes a mes do ano (barras) e saldo consolidado real (linha) — nada aqui e projecao. */
export function AnnualChart({ report }: { report: AnnualReport }) {
  const { isPrivate } = usePrivacy()
  const rows = toRows(report)

  return (
    <Card className="flex h-full flex-col p-[20px_22px_16px]">
      <CardHeader
        title="Resultado mês a mês"
        subtitle="Barras: receitas e despesas · Linha: saldo consolidado no fim do mês"
        action={<Legend />}
        className="mb-[22px]"
      />

      <div className={cn('min-h-[260px] w-full flex-1', isPrivate && 'privacy-blur')}>
        <ResponsiveContainer width="100%" height="100%">
          <ComposedChart data={rows} margin={{ top: 4, right: 4, bottom: 0, left: 0 }} barGap={5}>
            <defs>
              <linearGradient id="annualIncome" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#35D68A" />
                <stop offset="100%" stopColor="#1D9260" />
              </linearGradient>
              <linearGradient id="annualExpense" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#FF8A3D" />
                <stop offset="100%" stopColor="#B45A22" />
              </linearGradient>
            </defs>

            <CartesianGrid vertical={false} stroke="rgba(255,255,255,0.05)" />

            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={{ stroke: 'rgba(255,255,255,0.07)' }}
              tick={{ fontSize: 11, fontWeight: 600, fill: '#6E7681' }}
            />

            <YAxis
              yAxisId="left"
              width={54}
              tickLine={false}
              axisLine={false}
              tick={{ fontSize: 10.5, fontWeight: 600, fill: '#4A525E' }}
              tickFormatter={(value: number) => formatBRLCompact(value).replace('R$ ', '')}
            />

            <YAxis
              yAxisId="right"
              orientation="right"
              width={54}
              tickLine={false}
              axisLine={false}
              tick={{ fontSize: 10.5, fontWeight: 600, fill: '#4A525E' }}
              tickFormatter={(value: number) => formatBRLCompact(value).replace('R$ ', '')}
            />

            <Tooltip cursor={{ fill: 'rgba(255,255,255,0.03)' }} content={<AnnualTooltip />} />

            <Bar yAxisId="left" dataKey="income" fill="url(#annualIncome)" radius={[5, 5, 0, 0]} isAnimationActive={false} />
            <Bar yAxisId="left" dataKey="expense" fill="url(#annualExpense)" radius={[5, 5, 0, 0]} isAnimationActive={false} />

            <Line
              yAxisId="right"
              type="monotone"
              dataKey="balance"
              stroke="#35D68A"
              strokeWidth={2}
              dot={{ r: 2.5, fill: '#0F1216', stroke: '#35D68A', strokeWidth: 2 }}
              activeDot={{ r: 4, fill: '#35D68A', stroke: '#04140C', strokeWidth: 2 }}
              isAnimationActive={false}
            />
          </ComposedChart>
        </ResponsiveContainer>
      </div>
    </Card>
  )
}

function Legend() {
  const items = [
    { label: 'Receitas', color: '#35D68A' },
    { label: 'Despesas', color: '#FF8A3D' },
    { label: 'Saldo consolidado', color: '#35D68A', outline: true },
  ]

  return (
    <div className="flex flex-wrap gap-3.5 text-[11.5px] font-semibold text-ink-soft">
      {items.map((item) => (
        <span key={item.label} className="flex items-center gap-1.5">
          <span
            className={cn('size-[9px] rounded-[3px]', item.outline && 'rounded-full border-2 border-current bg-transparent')}
            style={{ background: item.outline ? undefined : item.color, color: item.color }}
            aria-hidden="true"
          />
          {item.label}
        </span>
      ))}
    </div>
  )
}

function AnnualTooltip({ active, payload }: TooltipProps<number, string>) {
  if (!active || !payload || payload.length === 0) {
    return null
  }

  const row = payload[0]?.payload as ChartRow | undefined

  if (!row) {
    return null
  }

  return (
    <div className="rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-3 shadow-xl">
      <p className="mb-2 text-[11.5px] font-bold text-ink">
        {formatCompetence(row.month).replace(/^./, (char) => char.toUpperCase())}
      </p>
      <p className="flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Receitas</span>
        <span className="font-mono font-semibold text-green-bright">{formatBRL(row.income)}</span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Despesas</span>
        <span className="font-mono font-semibold text-orange-light">{formatBRL(row.expense)}</span>
      </p>
      <p className="mt-2 flex items-center justify-between gap-6 border-t border-hairline pt-2 text-[11.5px]">
        <span className="text-ink-soft">Saldo consolidado</span>
        <span className="font-mono font-semibold text-green-bright">{formatBRL(row.balance)}</span>
      </p>
    </div>
  )
}

export function AnnualChartSkeleton() {
  return (
    <Card className="flex h-full flex-col gap-4 p-[20px_22px_16px]">
      <div className="flex flex-col gap-2">
        <div className="skeleton h-3 w-48 rounded-lg" />
        <div className="skeleton h-2.5 w-64 rounded-lg" />
      </div>
      <div className="skeleton min-h-[260px] flex-1 rounded-[14px]" />
    </Card>
  )
}
