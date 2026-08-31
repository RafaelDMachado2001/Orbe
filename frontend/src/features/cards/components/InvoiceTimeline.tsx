import { useEffect, useRef } from 'react'

import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { capitalize, formatCompetenceShort } from '@/lib/format'
import type { InvoiceRow, InvoiceStatus } from '@/types/api'

const statusDot: Record<InvoiceStatus, string> = {
  aberta: 'bg-purple',
  fechada: 'bg-orange',
  paga: 'bg-green',
}

interface Props {
  invoices: InvoiceRow[]
  selectedId: number | null
  onSelect: (id: number) => void
}

/**
 * Linha do tempo das faturas do cartão, da mais recente para a mais antiga.
 *
 * Rola na horizontal em vez de paginar: quem procura a fatura de março quer
 * varrer os meses com o olho, não avançar páginas até achar.
 */
export function InvoiceTimeline({ invoices, selectedId, onSelect }: Props) {
  const selectedRef = useRef<HTMLButtonElement>(null)

  // A lista abre no mês mais recente, mas a fatura que interessa é a mais
  // antiga ainda em aberto — que fica lá no fim da rolagem. Sem isto, a aba
  // marcada nasce fora da tela e a linha do tempo parece não ter seleção.
  useEffect(() => {
    selectedRef.current?.scrollIntoView({ inline: 'center', block: 'nearest' })
  }, [selectedId])

  if (invoices.length === 0) {
    return (
      <p className="rounded-[13px] border border-dashed border-hairline px-4 py-5 text-center text-[11.5px] text-ink-muted">
        Este cartão ainda não tem fatura. Ela nasce com a primeira compra.
      </p>
    )
  }

  return (
    <div
      role="tablist"
      aria-label="Faturas do cartão"
      className="flex gap-2 overflow-x-auto pb-1"
    >
      {invoices.map((invoice) => (
        <button
          key={invoice.id}
          ref={invoice.id === selectedId ? selectedRef : undefined}
          type="button"
          role="tab"
          aria-selected={invoice.id === selectedId}
          onClick={() => onSelect(invoice.id)}
          className={cn(
            'flex min-w-[112px] shrink-0 flex-col gap-1 rounded-[12px] border px-3 py-2.5 text-left transition-colors',
            invoice.id === selectedId
              ? 'border-purple/45 bg-purple/[0.10]'
              : 'border-hairline bg-surface-alt hover:border-hairline-strong',
          )}
        >
          <span className="flex items-center gap-1.5">
            <span className={cn('size-[6px] shrink-0 rounded-full', statusDot[invoice.status])} />
            <span className="text-[11.5px] font-bold text-ink">
              {capitalize(formatCompetenceShort(invoice.reference_month))}
              <span className="ml-1 font-medium text-ink-muted">
                {invoice.reference_month.slice(2, 4)}
              </span>
            </span>
          </span>
          <Money value={invoice.total} className="text-[12.5px] font-semibold text-ink-soft" />
          <span className="text-[10px] font-medium text-ink-faint">{invoice.status_label}</span>
        </button>
      ))}
    </div>
  )
}

export function InvoiceTimelineSkeleton() {
  return (
    <div className="flex gap-2">
      {Array.from({ length: 5 }).map((_, index) => (
        <Skeleton key={index} className="h-[74px] w-[112px] rounded-[12px]" />
      ))}
    </div>
  )
}
