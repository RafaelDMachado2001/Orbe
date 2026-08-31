import { useState } from 'react'

import { Card } from '@/components/ui/Card'
import { EmptyState } from '@/components/ui/States'
import { usePrivacy } from '@/app/usePrivacy'
import { cn } from '@/lib/cn'
import { formatDayMonth, formatSigned } from '@/lib/format'
import type { Movement } from '@/types/api'

type Filter = 'tudo' | 'cartao' | 'conta'

const filters: { key: Filter; label: string }[] = [
  { key: 'tudo', label: 'Tudo' },
  { key: 'cartao', label: 'Cartão' },
  { key: 'conta', label: 'Conta' },
]

export function LatestMovements({ movements }: { movements: Movement[] }) {
  const [filter, setFilter] = useState<Filter>('tudo')
  const { isPrivate } = usePrivacy()

  const visible = movements.filter((movement) => {
    if (filter === 'cartao') return movement.installment !== null
    if (filter === 'conta') return movement.installment === null

    return true
  })

  return (
    <Card className="p-[20px_22px]">
      <div className="mb-1.5 flex items-center">
        <h2 className="text-[14.5px] font-bold tracking-[-0.2px]">Últimos lançamentos</h2>
        <div className="ml-auto flex gap-[7px]" role="tablist" aria-label="Filtrar lançamentos">
          {filters.map((item) => (
            <button
              key={item.key}
              type="button"
              role="tab"
              aria-selected={filter === item.key}
              onClick={() => setFilter(item.key)}
              className={cn(
                'rounded-lg px-[11px] py-[5px] text-[11px] transition-colors',
                filter === item.key
                  ? 'bg-white/[0.07] font-bold text-ink'
                  : 'font-semibold text-ink-dim hover:text-ink',
              )}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      <div
        role="table"
        aria-label="Últimos lançamentos"
        className="text-[12.5px]"
      >
        <div
          role="row"
          className="grid grid-cols-[1.6fr_1fr_0.9fr_0.8fr] gap-3 border-b border-hairline pb-2.5 pt-3.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint"
        >
          <span role="columnheader">Descrição</span>
          <span role="columnheader">Categoria</span>
          <span role="columnheader">Origem</span>
          <span role="columnheader" className="text-right">
            Valor
          </span>
        </div>

        {visible.length === 0 ? (
          <div className="pt-4">
            <EmptyState
              title="Nada por aqui"
              description="Nenhum lançamento neste filtro. Registre uma despesa ou troque o período."
            />
          </div>
        ) : (
          visible.map((movement, index) => (
            <div
              key={movement.id}
              role="row"
              tabIndex={0}
              className={cn(
                'grid grid-cols-[1.6fr_1fr_0.9fr_0.8fr] items-center gap-3 py-[13px]',
                index < visible.length - 1 && 'border-b border-hairline-soft',
              )}
            >
              <div role="cell" className="flex min-w-0 flex-col gap-0.5">
                <span className="truncate font-semibold">{movement.description}</span>
                <span className="text-[10.5px] text-ink-muted">
                  {formatDayMonth(movement.date)} · {subtitle(movement)}
                </span>
              </div>

              <div role="cell">
                {movement.category ? (
                  <span
                    className="inline-block rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
                    style={{
                      color: movement.category.color,
                      background: `${movement.category.color}1F`,
                    }}
                  >
                    {movement.category.name}
                  </span>
                ) : (
                  <span className="text-[11px] text-ink-muted">Sem categoria</span>
                )}
              </div>

              <span role="cell" className="truncate text-[11.5px] font-medium text-ink-soft">
                {movement.source}
              </span>

              <span
                role="cell"
                className={cn(
                  'text-right font-mono text-[13px] font-semibold whitespace-nowrap',
                  movement.direction === 'entrada' ? 'text-green-bright' : 'text-[#FF9E5C]',
                  isPrivate && 'privacy-blur',
                )}
              >
                {formatSigned(movement.amount, movement.direction)}
              </span>
            </div>
          ))
        )}
      </div>
    </Card>
  )
}

function subtitle(movement: Movement): string {
  if (movement.installment !== null) {
    return `crédito ${movement.installment}`
  }

  if (movement.is_recurring) {
    return 'recorrente'
  }

  return movement.status === 'previsto' ? 'previsto' : 'confirmado'
}
