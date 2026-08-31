import { Money } from '@/components/ui/Money'
import { formatPercent } from '@/lib/format'
import type { Dashboard } from '@/types/api'

export function ForecastStrip({ forecast }: { forecast: Dashboard['forecast'] }) {
  if (forecast.committed_amount === null) {
    return null
  }

  return (
    <section className="grid grid-cols-1 items-center gap-6 rounded-card border border-purple/[0.18] bg-[linear-gradient(120deg,rgba(160,124,255,0.09),rgba(53,214,138,0.05)_70%),#0F1216] p-[22px] lg:grid-cols-[1.1fr_1fr_1fr_1fr]">
      <div>
        <h2 className="text-[14.5px] font-bold tracking-[-0.2px]">Previsão financeira</h2>
        <p className="mt-[5px] text-[11.5px] font-medium leading-[1.5] text-[#8E96A1]">
          {forecast.description}
        </p>
      </div>

      <ForecastCell label="Comprometido" hint="parcelas + fixas do próximo mês">
        <Money value={forecast.committed_amount} className="text-[20px] font-semibold text-orange-light" />
      </ForecastCell>

      <ForecastCell
        label="Sobra prevista"
        hint={
          forecast.commitment_rate === null
            ? undefined
            : `${formatPercent(100 - forecast.commitment_rate)} da renda prevista`
        }
      >
        <Money value={forecast.leftover} className="text-[20px] font-semibold text-green-bright" />
      </ForecastCell>

      <ForecastCell label="Meta reserva">
        {forecast.goal_progress === null ? (
          <span className="font-mono text-[20px] font-semibold text-ink-muted">—</span>
        ) : (
          <>
            <span className="font-mono text-[20px] font-semibold text-purple-light">
              {formatPercent(forecast.goal_progress, 0)}
            </span>
            <div className="mt-2.5 h-[5px] overflow-hidden rounded bg-white/[0.08]">
              <div
                className="h-full rounded bg-[linear-gradient(90deg,#A07CFF,#35D68A)]"
                style={{ width: `${forecast.goal_progress}%` }}
              />
            </div>
          </>
        )}
      </ForecastCell>
    </section>
  )
}

function ForecastCell({
  label,
  hint,
  children,
}: {
  label: string
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div className="border-hairline pl-[22px] lg:border-l">
      <p className="text-[11px] font-bold uppercase tracking-[0.06em] text-ink-faint">{label}</p>
      <div className="mt-[7px]">{children}</div>
      {hint ? <p className="mt-1 text-[11px] font-medium text-ink-muted">{hint}</p> : null}
    </div>
  )
}
