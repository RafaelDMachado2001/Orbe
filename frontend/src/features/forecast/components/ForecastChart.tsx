import {
  Bar,
  CartesianGrid,
  Cell,
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
import { formatBRL, formatBRLCompact, formatCompetence, formatCompetenceShort, formatPercent } from '@/lib/format'
import type { ForecastResult } from '@/types/api'

interface ForecastPoint {
  month: string
  label: string
  income: number
  committed: number
  variable: number
  balance: number
  leftover: number
  commitmentRate: number
  confidence: number
}

/** Mes mais distante enxerga menos historico por tras dele: a barra fica mais apagada conforme a confianca cai. */
function opacityFor(confidence: number): number {
  return 0.35 + (Math.max(0, Math.min(100, confidence)) / 100) * 0.65
}

function toPoints(data: ForecastResult): ForecastPoint[] {
  return data.months.map((month) => ({
    month: month.month,
    label: formatCompetenceShort(month.month),
    income: month.predicted_income,
    committed: month.committed_amount,
    variable: Math.max(month.predicted_expense - month.committed_amount, 0),
    balance: month.projected_balance,
    leftover: month.leftover,
    commitmentRate: month.commitment_rate,
    confidence: month.confidence,
  }))
}

/**
 * Receita e despesa (comprometido + variavel empilhados) em barras, saldo
 * projetado acumulado em linha num eixo secundario — a escala acumulada e
 * grande demais para dividir eixo com os totais mensais. A confianca nao
 * ganha um elemento visual proprio: ela so apaga a barra do mes, o mesmo
 * idioma que o grafico do dashboard usa para separar real de previsto.
 */
export function ForecastChart({ data }: { data: ForecastResult }) {
  const { isPrivate } = usePrivacy()
  const points = toPoints(data)

  return (
    <Card className="flex h-full flex-col p-[20px_22px_16px]">
      <CardHeader
        title="Receitas, despesas e saldo projetado"
        subtitle="Barras: comprometido (fixas + parcelas) e variável estimado · Linha: saldo acumulado"
        action={<Legend />}
        className="mb-[22px]"
      />

      <div className={cn('min-h-[260px] w-full flex-1', isPrivate && 'privacy-blur')}>
        <ResponsiveContainer width="100%" height="100%">
          <ComposedChart data={points} margin={{ top: 4, right: 4, bottom: 0, left: 0 }} barGap={5}>
            <defs>
              <linearGradient id="forecastIncome" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#35D68A" />
                <stop offset="100%" stopColor="#1D9260" />
              </linearGradient>
              <linearGradient id="forecastCommitted" x1="0" y1="0" x2="0" y2="1">
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

            <Tooltip cursor={{ fill: 'rgba(255,255,255,0.03)' }} content={<ForecastTooltip />} />

            <Bar yAxisId="left" dataKey="income" stackId="income" radius={[5, 5, 0, 0]} isAnimationActive={false}>
              {points.map((point) => (
                <Cell key={`income-${point.month}`} fill="url(#forecastIncome)" fillOpacity={opacityFor(point.confidence)} />
              ))}
            </Bar>

            <Bar yAxisId="left" dataKey="committed" stackId="expense" isAnimationActive={false}>
              {points.map((point) => (
                <Cell key={`committed-${point.month}`} fill="url(#forecastCommitted)" fillOpacity={opacityFor(point.confidence)} />
              ))}
            </Bar>

            <Bar yAxisId="left" dataKey="variable" stackId="expense" radius={[5, 5, 0, 0]} isAnimationActive={false}>
              {points.map((point) => (
                <Cell key={`variable-${point.month}`} fill="#FFB072" fillOpacity={opacityFor(point.confidence) * 0.55} />
              ))}
            </Bar>

            <Line
              yAxisId="right"
              type="monotone"
              dataKey="balance"
              stroke="#A07CFF"
              strokeWidth={2}
              dot={{ r: 2.5, fill: '#0F1216', stroke: '#A07CFF', strokeWidth: 2 }}
              activeDot={{ r: 4, fill: '#A07CFF', stroke: '#1B1330', strokeWidth: 2 }}
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
    { label: 'Comprometido', color: '#FF8A3D' },
    { label: 'Variável', color: '#FFB072' },
    { label: 'Saldo projetado', color: '#A07CFF' },
  ]

  return (
    <div className="flex flex-wrap gap-3.5 text-[11.5px] font-semibold text-ink-soft">
      {items.map((item) => (
        <span key={item.label} className="flex items-center gap-1.5">
          <span className="size-[9px] rounded-[3px]" style={{ background: item.color }} aria-hidden="true" />
          {item.label}
        </span>
      ))}
    </div>
  )
}

function ForecastTooltip({ active, payload }: TooltipProps<number, string>) {
  if (!active || !payload || payload.length === 0) {
    return null
  }

  const point = payload[0]?.payload as ForecastPoint | undefined

  if (!point) {
    return null
  }

  return (
    <div className="rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-3 shadow-xl">
      <p className="mb-2 flex items-center gap-1.5 text-[11.5px] font-bold text-ink">
        {formatCompetence(point.month).replace(/^./, (char) => char.toUpperCase())}
        <span className="rounded-full bg-purple/[0.16] px-1.5 py-px text-[10px] font-semibold text-purple-light">
          confiança {point.confidence}%
        </span>
      </p>
      <p className="flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Receitas</span>
        <span className="font-mono font-semibold text-green-bright">{formatBRL(point.income)}</span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Comprometido</span>
        <span className="font-mono font-semibold text-orange-light">{formatBRL(point.committed)}</span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Variável estimado</span>
        <span className="font-mono font-semibold text-[#FFB072]">{formatBRL(point.variable)}</span>
      </p>
      <p className="mt-2 flex items-center justify-between gap-6 border-t border-hairline pt-2 text-[11.5px]">
        <span className="text-ink-soft">Sobra prevista</span>
        <span
          className={cn(
            'font-mono font-semibold',
            point.leftover >= 0 ? 'text-green-bright' : 'text-orange-light',
          )}
        >
          {formatBRL(point.leftover)}
        </span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Renda comprometida</span>
        <span className="font-mono font-semibold text-ink">{formatPercent(point.commitmentRate)}</span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Saldo projetado</span>
        <span className="font-mono font-semibold text-purple-light">{formatBRL(point.balance)}</span>
      </p>
    </div>
  )
}

export function ForecastChartSkeleton() {
  return (
    <Card className="flex h-full flex-col gap-4 p-[20px_22px_16px]">
      <div className="flex flex-col gap-2">
        <div className="skeleton h-3 w-56 rounded-lg" />
        <div className="skeleton h-2.5 w-72 rounded-lg" />
      </div>
      <div className="skeleton min-h-[260px] flex-1 rounded-[14px]" />
    </Card>
  )
}
