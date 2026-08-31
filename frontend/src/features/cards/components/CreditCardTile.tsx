import { Archive, ArchiveRestore, MoreHorizontal, Pencil, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { CardFace } from '@/components/ui/CardFace'
import { Money } from '@/components/ui/Money'
import { cn } from '@/lib/cn'
import { formatBRLCompact, formatDate, formatPercent } from '@/lib/format'
import type { CardDetail } from '@/types/api'

interface Props {
  card: CardDetail
  isSelected: boolean
  onSelect: () => void
  onEdit: () => void
  onArchive: (isActive: boolean) => void
  onDelete: () => void
}

/**
 * Uma linha da lista: a miniatura do cartão e, ao lado, o que ele deve.
 *
 * A miniatura não é enfeite — é o que faz o usuário achar o cartão dele na
 * lista pela cor e pela bandeira, antes de ler o apelido. Clicar em qualquer
 * ponto seleciona; as faturas ao lado seguem a seleção.
 */
export function CreditCardTile({ card, isSelected, onSelect, onEdit, onArchive, onDelete }: Props) {
  const invoice = card.current_invoice

  return (
    <article
      className={cn(
        'relative overflow-hidden rounded-[15px] border p-[14px] transition-colors',
        isSelected
          ? 'border-purple/45 bg-[linear-gradient(155deg,rgba(160,124,255,0.13),rgba(160,124,255,0.02))]'
          : 'border-hairline bg-surface-alt hover:border-hairline-strong',
      )}
    >
      <button
        type="button"
        onClick={onSelect}
        aria-pressed={isSelected}
        aria-label={`Ver faturas de ${card.nickname}`}
        className="absolute inset-0 z-0 cursor-pointer"
      />

      <div className="pointer-events-none relative z-10 flex gap-3.5">
        <CardFace
          nickname={card.nickname}
          lastFour={card.last_four}
          brand={card.brand}
          color={card.color}
          width={112}
          // Arquivado sai de cena sem sumir: menos cor, mesma posição.
          isMuted={!card.is_active}
        />

        <div className={cn('flex min-w-0 flex-1 flex-col', !card.is_active && 'opacity-60')}>
          <div className="flex items-start gap-2 pr-7">
            <span className="truncate text-[13px] font-bold tracking-[-0.2px]">
              {card.nickname}
            </span>
            {!card.is_active ? (
              <span className="shrink-0 rounded-full bg-white/[0.06] px-[7px] py-[2px] text-[9.5px] font-bold text-ink-muted">
                Arquivado
              </span>
            ) : null}
          </div>

          <span className="mt-px text-[10.5px] font-semibold text-ink-muted">
            {card.brand_label} · {card.bank}
          </span>

          <span className="mt-auto pt-2 text-[9.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            Fatura atual
          </span>
          <Money value={invoice.total} className="text-[16px] font-bold leading-tight text-ink" />
          <span className="truncate text-[10.5px] font-medium text-ink-muted">{caption(card)}</span>
        </div>
      </div>

      <div className="pointer-events-none relative z-10 mt-3 flex items-center gap-2.5">
        <div className="h-[5px] flex-1 overflow-hidden rounded-[3px] bg-white/[0.07]">
          <div
            className="h-full rounded-[3px] transition-[width]"
            style={{
              width: `${card.usage_percent}%`,
              background: card.usage_percent >= 80 ? '#FF8A3D' : card.color,
            }}
          />
        </div>
        <span className="whitespace-nowrap text-[10.5px] font-semibold text-ink-muted">
          {formatPercent(card.usage_percent, 0)} de {formatBRLCompact(card.limit_amount)}
        </span>
      </div>

      <div className="absolute right-2.5 top-2.5 z-20">
        <CardMenu card={card} onEdit={onEdit} onArchive={onArchive} onDelete={onDelete} />
      </div>
    </article>
  )
}

/** O ciclo do cartão em uma linha: fatura fechada já tem vencimento, aberta ainda vai fechar. */
function caption(card: CardDetail): string {
  const invoice = card.current_invoice

  if (invoice.status === 'paga') {
    return `paga · fecha dia ${card.closing_day}`
  }

  if (invoice.status === 'fechada') {
    return `fechada · vence ${formatDate(invoice.due_date).slice(0, 5)}`
  }

  const closing = invoice.days_to_close === 0 ? 'fecha hoje' : `fecha em ${invoice.days_to_close}d`

  return `${closing} · vence dia ${card.due_day}`
}

interface MenuProps {
  card: CardDetail
  onEdit: () => void
  onArchive: (isActive: boolean) => void
  onDelete: () => void
}

function CardMenu({ card, onEdit, onArchive, onDelete }: MenuProps) {
  const [isOpen, setIsOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!isOpen) {
      return
    }

    function handlePointer(event: MouseEvent) {
      if (!containerRef.current?.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    function handleKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointer)
    document.addEventListener('keydown', handleKey)

    return () => {
      document.removeEventListener('mousedown', handlePointer)
      document.removeEventListener('keydown', handleKey)
    }
  }, [isOpen])

  function run(action: () => void) {
    setIsOpen(false)
    action()
  }

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        aria-label={`Ações de ${card.nickname}`}
        className="grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.08] hover:text-ink"
      >
        <MoreHorizontal className="size-4" aria-hidden="true" />
      </button>

      {isOpen ? (
        <div
          role="menu"
          className="absolute right-0 top-8 z-30 flex w-[196px] flex-col gap-0.5 rounded-[13px] border border-hairline-strong bg-surface-alt p-1.5 shadow-[0_18px_40px_rgba(0,0,0,0.55)]"
        >
          <MenuItem icon={Pencil} onClick={() => run(onEdit)}>
            Editar cartão
          </MenuItem>

          {card.is_active ? (
            <MenuItem icon={Archive} onClick={() => run(() => onArchive(false))}>
              Arquivar
            </MenuItem>
          ) : (
            <MenuItem icon={ArchiveRestore} onClick={() => run(() => onArchive(true))}>
              Reativar
            </MenuItem>
          )}

          <MenuItem icon={Trash2} tone="danger" onClick={() => run(onDelete)}>
            Excluir
          </MenuItem>
        </div>
      ) : null}
    </div>
  )
}

function MenuItem({
  icon: Icon,
  children,
  onClick,
  tone = 'default',
}: {
  icon: typeof Pencil
  children: React.ReactNode
  onClick: () => void
  tone?: 'default' | 'danger'
}) {
  return (
    <button
      type="button"
      role="menuitem"
      onClick={onClick}
      className={cn(
        'flex items-center gap-2.5 rounded-[9px] px-2.5 py-2 text-left text-[12px] font-semibold transition-colors',
        tone === 'danger'
          ? 'text-ink-soft hover:bg-danger/[0.12] hover:text-danger'
          : 'text-ink-soft hover:bg-white/[0.06] hover:text-ink',
      )}
    >
      <Icon className="size-3.5 shrink-0" aria-hidden="true" />
      {children}
    </button>
  )
}
