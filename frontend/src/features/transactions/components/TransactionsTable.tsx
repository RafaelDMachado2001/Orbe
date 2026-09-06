import {
  Check,
  CreditCard,
  Landmark,
  MoreHorizontal,
  Pencil,
  Repeat,
  RotateCcw,
  Trash2,
  Undo2,
} from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { usePrivacy } from '@/app/usePrivacy'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/States'
import { cn } from '@/lib/cn'
import { formatDayMonth, formatSigned } from '@/lib/format'
import type { TransactionEntry, TransactionStatus } from '@/types/api'

const columns = 'grid grid-cols-[80px_minmax(0,1.7fr)_130px_minmax(0,1fr)_104px_130px_40px] gap-3'

const statusTone: Record<TransactionStatus, string> = {
  confirmado: 'bg-green/[0.12] text-green-bright',
  previsto: 'bg-purple/[0.14] text-purple-light',
  cancelado: 'bg-white/[0.06] text-ink-muted line-through',
}

interface Props {
  entries: TransactionEntry[]
  isRefreshing: boolean
  onEdit: (entry: TransactionEntry) => void
  onDelete: (entry: TransactionEntry) => void
  onChangeStatus: (entry: TransactionEntry, status: TransactionStatus) => void
  emptyAction?: React.ReactNode
}

export function TransactionsTable({
  entries,
  isRefreshing,
  onEdit,
  onDelete,
  onChangeStatus,
  emptyAction,
}: Props) {
  const { isPrivate } = usePrivacy()

  if (entries.length === 0) {
    return (
      <div className="py-4">
        <EmptyState
          title="Nenhum lançamento no período"
          description="Ajuste o período ou os filtros acima, ou registre o primeiro lançamento deste recorte."
          action={emptyAction}
        />
      </div>
    )
  }

  return (
    <>
      <div className={cn('flex flex-col md:hidden', isRefreshing && 'opacity-60 transition-opacity')}>
        {entries.map((entry, index) => (
          <TransactionCard
            key={entry.id}
            entry={entry}
            isPrivate={isPrivate}
            isLast={index === entries.length - 1}
            onEdit={() => onEdit(entry)}
            onDelete={() => onDelete(entry)}
            onChangeStatus={(status) => onChangeStatus(entry, status)}
          />
        ))}
      </div>

      <div
        role="table"
        aria-label="Lançamentos"
        aria-busy={isRefreshing}
        className="hidden text-[12.5px] md:block"
      >
        <div
          role="row"
          className={cn(
            columns,
            'border-b border-hairline pb-2.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint',
          )}
        >
          <span role="columnheader">Data</span>
          <span role="columnheader">Descrição</span>
          <span role="columnheader">Categoria</span>
          <span role="columnheader">Origem</span>
          <span role="columnheader">Situação</span>
          <span role="columnheader" className="text-right">
            Valor
          </span>
          <span role="columnheader" className="sr-only">
            Ações
          </span>
        </div>

        <div className={cn('transition-opacity', isRefreshing && 'opacity-60')}>
          {entries.map((entry, index) => (
            <div
              key={entry.id}
              role="row"
              className={cn(
                columns,
                'items-center py-[13px] transition-colors hover:bg-white/[0.02]',
                index < entries.length - 1 && 'border-b border-hairline-soft',
                entry.status === 'cancelado' && 'opacity-55',
              )}
            >
              <span role="cell" className="font-mono text-[11.5px] tabular-nums text-ink-soft">
                {formatDayMonth(entry.date)}
              </span>

              <div role="cell" className="flex min-w-0 flex-col gap-0.5">
                <span className="truncate font-semibold text-ink">{entry.description}</span>
                <span className="flex items-center gap-1.5 text-[10.5px] text-ink-muted">
                  {entry.installment ? (
                    <span className="rounded bg-white/[0.06] px-1.5 py-px font-mono font-semibold text-ink-soft">
                      {entry.installment}
                    </span>
                  ) : null}
                  {entry.is_recurring ? (
                    <span className="flex items-center gap-1 text-purple-light">
                      <Repeat className="size-3" aria-hidden="true" />
                      recorrente
                    </span>
                  ) : null}
                  {entry.is_transfer ? 'transferência entre contas' : entry.method_label ?? '—'}
                </span>
              </div>

              <div role="cell" className="min-w-0">
                {entry.category ? (
                  <span
                    className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
                    style={{ color: entry.category.color }}
                  >
                    {entry.category.name}
                  </span>
                ) : (
                  <span className="text-[11px] text-ink-muted">Sem categoria</span>
                )}
              </div>

              <span
                role="cell"
                className="flex min-w-0 items-center gap-1.5 text-[11.5px] font-medium text-ink-soft"
              >
                {entry.origin === 'cartao' ? (
                  <CreditCard className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
                ) : (
                  <Landmark className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
                )}
                <span className="truncate">{entry.source}</span>
              </span>

              <span role="cell">
                <span
                  className={cn(
                    'inline-block rounded-full px-[9px] py-[3px] text-[10.5px] font-bold',
                    statusTone[entry.status],
                  )}
                >
                  {entry.status_label}
                </span>
              </span>

              <span
                role="cell"
                className={cn(
                  'whitespace-nowrap text-right font-mono text-[13px] font-semibold tabular-nums',
                  entry.is_transfer
                    ? 'text-ink-soft'
                    : entry.direction === 'entrada'
                      ? 'text-green-bright'
                      : 'text-[#FF9E5C]',
                  isPrivate && 'privacy-blur',
                )}
              >
                {formatSigned(entry.amount, entry.direction)}
              </span>

              <span role="cell" className="flex justify-end">
                <RowMenu
                  entry={entry}
                  onEdit={() => onEdit(entry)}
                  onDelete={() => onDelete(entry)}
                  onChangeStatus={(status) => onChangeStatus(entry, status)}
                />
              </span>
            </div>
          ))}
        </div>
      </div>
    </>
  )
}

interface TransactionCardProps {
  entry: TransactionEntry
  isPrivate: boolean
  isLast: boolean
  onEdit: () => void
  onDelete: () => void
  onChangeStatus: (status: TransactionStatus) => void
}

function TransactionCard({
  entry,
  isPrivate,
  isLast,
  onEdit,
  onDelete,
  onChangeStatus,
}: TransactionCardProps) {
  return (
    <div
      className={cn(
        'flex flex-col gap-2 py-[13px] text-[12.5px]',
        !isLast && 'border-b border-hairline-soft',
        entry.status === 'cancelado' && 'opacity-55',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="truncate font-semibold text-ink">{entry.description}</span>
          <span className="flex flex-wrap items-center gap-1.5 text-[10.5px] text-ink-muted">
            <span className="font-mono tabular-nums">{formatDayMonth(entry.date)}</span>
            {entry.installment ? (
              <span className="rounded bg-white/[0.06] px-1.5 py-px font-mono font-semibold text-ink-soft">
                {entry.installment}
              </span>
            ) : null}
            {entry.is_recurring ? (
              <span className="flex items-center gap-1 text-purple-light">
                <Repeat className="size-3" aria-hidden="true" />
                recorrente
              </span>
            ) : null}
          </span>
        </div>

        <div className="flex shrink-0 items-center gap-1.5">
          <span
            className={cn(
              'whitespace-nowrap font-mono text-[13px] font-semibold tabular-nums',
              entry.is_transfer
                ? 'text-ink-soft'
                : entry.direction === 'entrada'
                  ? 'text-green-bright'
                  : 'text-[#FF9E5C]',
              isPrivate && 'privacy-blur',
            )}
          >
            {formatSigned(entry.amount, entry.direction)}
          </span>

          <RowMenu
            entry={entry}
            onEdit={onEdit}
            onDelete={onDelete}
            onChangeStatus={onChangeStatus}
          />
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        {entry.category ? (
          <span
            className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
            style={{ color: entry.category.color }}
          >
            {entry.category.name}
          </span>
        ) : (
          <span className="text-[11px] text-ink-muted">Sem categoria</span>
        )}

        <span
          className={cn(
            'inline-block rounded-full px-[9px] py-[3px] text-[10.5px] font-bold',
            statusTone[entry.status],
          )}
        >
          {entry.status_label}
        </span>
      </div>

      <span className="flex min-w-0 items-center gap-1.5 text-[11.5px] font-medium text-ink-soft">
        {entry.origin === 'cartao' ? (
          <CreditCard className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
        ) : (
          <Landmark className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
        )}
        <span className="truncate">
          {entry.is_transfer ? 'transferência entre contas' : entry.source}
        </span>
      </span>
    </div>
  )
}

interface RowMenuProps {
  entry: TransactionEntry
  onEdit: () => void
  onDelete: () => void
  onChangeStatus: (status: TransactionStatus) => void
}

/**
 * Uma parcela e uma baixa de fatura aparecem na lista, mas não se editam por
 * aqui: a parcela nasce da compra e o pagamento pertence à tela de Cartões.
 * Nesses casos o menu explica em vez de sumir, para a ausência não parecer bug.
 */
function RowMenu({ entry, onEdit, onDelete, onChangeStatus }: RowMenuProps) {
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
        aria-label={`Ações de ${entry.description}`}
        className="grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink"
      >
        <MoreHorizontal className="size-4" aria-hidden="true" />
      </button>

      {isOpen ? (
        <div
          role="menu"
          className="absolute right-0 top-8 z-20 flex w-[212px] flex-col gap-0.5 rounded-[13px] border border-hairline-strong bg-surface-alt p-1.5 shadow-[0_18px_40px_rgba(0,0,0,0.55)]"
        >
          {entry.is_invoice_payment ? (
            <MenuNote>Pagamento de fatura. Ajuste na tela de Cartões.</MenuNote>
          ) : entry.is_paid ? (
            <MenuNote>Parcela de uma fatura já paga.</MenuNote>
          ) : (
            <>
              {entry.status !== 'confirmado' ? (
                <MenuItem icon={Check} onClick={() => run(() => onChangeStatus('confirmado'))}>
                  Confirmar
                </MenuItem>
              ) : null}

              {entry.status === 'confirmado' ? (
                <MenuItem icon={Undo2} onClick={() => run(() => onChangeStatus('previsto'))}>
                  Voltar para previsto
                </MenuItem>
              ) : null}

              {entry.status !== 'cancelado' ? (
                <MenuItem icon={RotateCcw} onClick={() => run(() => onChangeStatus('cancelado'))}>
                  Cancelar
                </MenuItem>
              ) : (
                <MenuItem icon={RotateCcw} onClick={() => run(() => onChangeStatus('confirmado'))}>
                  Reativar
                </MenuItem>
              )}

              <MenuItem icon={Pencil} onClick={() => run(onEdit)}>
                {entry.origin === 'cartao' ? 'Editar a compra' : 'Editar'}
              </MenuItem>

              <MenuItem icon={Trash2} tone="danger" onClick={() => run(onDelete)}>
                {entry.origin === 'cartao' ? 'Excluir a compra' : 'Excluir'}
              </MenuItem>
            </>
          )}
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
  icon: typeof Check
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

function MenuNote({ children }: { children: React.ReactNode }) {
  return (
    <p className="px-2.5 py-2 text-[11.5px] leading-relaxed text-ink-muted">{children}</p>
  )
}

export function TransactionsTableSkeleton() {
  return (
    <Card className="flex flex-col gap-3.5">
      <Skeleton className="h-3 w-40" />
      {Array.from({ length: 8 }).map((_, index) => (
        <Skeleton key={index} className="h-[38px] w-full" />
      ))}
    </Card>
  )
}
