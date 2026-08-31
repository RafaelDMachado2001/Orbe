import { addMonths, format, parseISO, subMonths } from 'date-fns'
import { ChevronLeft, ChevronRight } from 'lucide-react'

import { capitalize, formatCompetence } from '@/lib/format'

interface Props {
  /** Mês no formato AAAA-MM. */
  month: string
  onChange: (month: string) => void
}

/**
 * Navegação mês a mês. Diferente do seletor da Visão geral, que oferece três
 * meses fixos, aqui o passo é livre — quem organiza contas fixas precisa
 * alcançar tanto o mês passado quanto o próximo.
 */
export function MonthNav({ month, onChange }: Props) {
  const current = parseISO(`${month}-01`)
  const label = `${capitalize(formatCompetence(month))} de ${format(current, 'yyyy')}`

  return (
    <div
      role="group"
      aria-label="Selecionar mês"
      className="flex items-center gap-1 rounded-[11px] border border-hairline bg-surface-raised p-1"
    >
      <button
        type="button"
        onClick={() => onChange(format(subMonths(current, 1), 'yyyy-MM'))}
        aria-label="Mês anterior"
        className="grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink"
      >
        <ChevronLeft className="size-3.5" aria-hidden="true" />
      </button>

      <span className="min-w-[126px] text-center text-[12.5px] font-bold text-ink">{label}</span>

      <button
        type="button"
        onClick={() => onChange(format(addMonths(current, 1), 'yyyy-MM'))}
        aria-label="Próximo mês"
        className="grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink"
      >
        <ChevronRight className="size-3.5" aria-hidden="true" />
      </button>
    </div>
  )
}
