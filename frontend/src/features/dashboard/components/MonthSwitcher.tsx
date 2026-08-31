import { addMonths, format, parseISO, subMonths } from 'date-fns'
import { ptBR } from 'date-fns/locale'

import { cn } from '@/lib/cn'
import { capitalize } from '@/lib/format'

interface Props {
  month: string
  onChange: (month: string) => void
}

/** Alterna entre o mes atual e os dois anteriores, como na tela de referência. */
export function MonthSwitcher({ month, onChange }: Props) {
  const current = parseISO(`${month}-01`)

  const options = [subMonths(current, 2), subMonths(current, 1), current].map((date) => ({
    value: format(date, 'yyyy-MM'),
    label: capitalize(format(date, 'MMM', { locale: ptBR }).replace('.', '')),
  }))

  return (
    <div
      role="group"
      aria-label="Selecionar mês"
      className="flex items-center gap-0.5 rounded-[11px] border border-hairline bg-surface-raised p-1"
    >
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          onClick={() => onChange(option.value)}
          aria-current={option.value === month}
          className={cn(
            'rounded-lg px-[13px] py-1.5 text-[12.5px] transition-colors',
            option.value === month
              ? 'bg-white/[0.08] font-bold text-ink'
              : 'font-semibold text-ink-muted hover:text-ink',
          )}
        >
          {option.label}
        </button>
      ))}

      <button
        type="button"
        onClick={() => onChange(format(addMonths(current, 1), 'yyyy-MM'))}
        aria-label="Próximo mês"
        className="rounded-lg px-2 py-1.5 text-[12.5px] font-semibold text-ink-muted transition-colors hover:text-ink"
      >
        ›
      </button>
    </div>
  )
}
