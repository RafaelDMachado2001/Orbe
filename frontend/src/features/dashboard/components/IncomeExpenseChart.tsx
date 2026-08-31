import {
  Bar,
  BarChart,
  Cell,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  type TooltipProps,
} from 'recharts'

import { Card, CardHeader } from '@/components/ui/Card'
import { usePrivacy } from '@/app/usePrivacy'
import { cn } from '@/lib/cn'
import { formatBRL, formatCompetence, capitalize } from '@/lib/format'
import type { ChartPoint } from '@/types/api'

interface Props {
  data: ChartPoint[]
}

/**
 * Entradas e saidas dos ultimos meses. O mes projetado nao e uma barra
 * cheia: ele vem com borda pontilhada roxa, para o olho separar na hora o
 * que aconteceu do que ainda e estimativa.
 */
export function IncomeExpenseChart({ data }: Props) {
  const { isPrivate } = usePrivacy()

  return (
    <Card className="flex h-full flex-col p-[20px_22px_16px]">
      <CardHeader
        title="Entradas e saídas"
        subtitle={`Últimos ${data.filter((point) => !point.is_forecast).length} meses · projeção pontilhada`}
        action={<Legend />}
        className="mb-[22px]"
      />

      <div className={cn('min-h-[228px] w-full flex-1', isPrivate && 'privacy-blur')}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 4, right: 0, bottom: 0, left: 0 }} barGap={5}>
            <defs>
              <linearGradient id="barIncome" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#35D68A" />
                <stop offset="100%" stopColor="#1D9260" />
              </linearGradient>
              <linearGradient id="barExpense" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#FF8A3D" />
                <stop offset="100%" stopColor="#B45A22" />
              </linearGradient>
            </defs>

            <XAxis
              dataKey="label"
              tickLine={false}
              axisLine={{ stroke: 'rgba(255,255,255,0.07)' }}
              tick={({ x, y, payload, index }) => (
                <text
                  x={x}
                  y={y + 16}
                  textAnchor="middle"
                  className="font-sans"
                  style={{
                    fontSize: 11,
                    fontWeight: 600,
                    fill: data[index]?.is_forecast
                      ? '#A07CFF'
                      : index === data.length - 2
                        ? '#E9ECF1'
                        : '#6E7681',
                  }}
                >
                  {capitalize(String(payload.value).replace('.', ''))}
                </text>
              )}
            />

            <Tooltip
              cursor={{ fill: 'rgba(255,255,255,0.03)' }}
              content={<ChartTooltip />}
            />

            <Bar dataKey="income" radius={[5, 5, 0, 0]} isAnimationActive={false}>
              {data.map((point) => (
                <Cell
                  key={`income-${point.month}`}
                  fill={point.is_forecast ? 'rgba(160,124,255,0.09)' : 'url(#barIncome)'}
                  stroke={point.is_forecast ? 'rgba(160,124,255,0.6)' : undefined}
                  strokeWidth={point.is_forecast ? 1.5 : 0}
                  strokeDasharray={point.is_forecast ? '4 3' : undefined}
                />
              ))}
            </Bar>

            <Bar dataKey="expense" radius={[5, 5, 0, 0]} isAnimationActive={false}>
              {data.map((point) => (
                <Cell
                  key={`expense-${point.month}`}
                  fill={point.is_forecast ? 'rgba(160,124,255,0.05)' : 'url(#barExpense)'}
                  stroke={point.is_forecast ? 'rgba(160,124,255,0.35)' : undefined}
                  strokeWidth={point.is_forecast ? 1.5 : 0}
                  strokeDasharray={point.is_forecast ? '4 3' : undefined}
                />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </Card>
  )
}

function Legend() {
  const items = [
    { label: 'Receitas', color: '#35D68A' },
    { label: 'Despesas', color: '#FF8A3D' },
    { label: 'Previsto', color: '#A07CFF' },
  ]

  return (
    <div className="flex gap-3.5 text-[11.5px] font-semibold text-ink-soft">
      {items.map((item) => (
        <span key={item.label} className="flex items-center gap-1.5">
          <span
            className="size-[9px] rounded-[3px]"
            style={{ background: item.color }}
            aria-hidden="true"
          />
          {item.label}
        </span>
      ))}
    </div>
  )
}

function ChartTooltip({ active, payload }: TooltipProps<number, string>) {
  if (!active || !payload || payload.length === 0) {
    return null
  }

  const point = payload[0]?.payload as ChartPoint | undefined

  if (!point) {
    return null
  }

  return (
    <div className="rounded-[12px] border border-hairline bg-surface-alt px-3.5 py-3 shadow-xl">
      <p className="mb-2 text-[11.5px] font-bold text-ink">
        {capitalize(formatCompetence(point.month))}
        {point.is_forecast ? (
          <span className="ml-1.5 rounded-full bg-purple/[0.16] px-1.5 py-px text-[10px] font-semibold text-purple-light">
            previsto
          </span>
        ) : null}
      </p>
      <p className="flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Receitas</span>
        <span className="font-mono font-semibold text-green-bright">{formatBRL(point.income)}</span>
      </p>
      <p className="mt-1 flex items-center justify-between gap-6 text-[11.5px]">
        <span className="text-ink-soft">Despesas</span>
        <span className="font-mono font-semibold text-orange-light">{formatBRL(point.expense)}</span>
      </p>
      <p className="mt-2 flex items-center justify-between gap-6 border-t border-hairline pt-2 text-[11.5px]">
        <span className="text-ink-soft">Resultado</span>
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
