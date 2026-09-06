import {
  Check,
  CreditCard,
  Landmark,
  MoreHorizontal,
  Pause,
  Pencil,
  Play,
  Trash2,
  Zap,
} from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

import { usePrivacy } from '@/app/usePrivacy'
import { Card, CardHeader } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/ui/States'
import { cn } from '@/lib/cn'
import { formatDayMonth } from '@/lib/format'
import type { RecurrenceRow } from '@/types/api'

const columns = 'grid grid-cols-[minmax(0,1.7fr)_140px_minmax(0,1fr)_150px_130px_92px_40px] gap-3'

interface Props {
  title: string
  subtitle: string
  rows: RecurrenceRow[]
  emptyDescription: string
  isRefreshing: boolean
  launchingId: number | null
  onLaunch: (row: RecurrenceRow) => void
  onEdit: (row: RecurrenceRow) => void
  onToggle: (row: RecurrenceRow, isActive: boolean) => void
  onDelete: (row: RecurrenceRow) => void
  action?: React.ReactNode
}

export function RecurrenceList({
  title,
  subtitle,
  rows,
  emptyDescription,
  isRefreshing,
  launchingId,
  onLaunch,
  onEdit,
  onToggle,
  onDelete,
  action,
}: Props) {
  const { isPrivate } = usePrivacy()

  return (
    <Card className={cn('flex flex-col gap-3 transition-opacity', isRefreshing && 'opacity-60')}>
      <CardHeader title={title} subtitle={subtitle} action={action} />

      {rows.length === 0 ? (
        <EmptyState title="Nada por aqui" description={emptyDescription} />
      ) : (
        <>
          <div className="flex flex-col md:hidden">
            {rows.map((row, index) => (
              <RecurrenceCard
                key={row.id}
                row={row}
                isPrivate={isPrivate}
                isLast={index === rows.length - 1}
                isLaunching={launchingId === row.id}
                onLaunch={() => onLaunch(row)}
                onEdit={() => onEdit(row)}
                onToggle={(isActive) => onToggle(row, isActive)}
                onDelete={() => onDelete(row)}
              />
            ))}
          </div>

          <div role="table" aria-label={title} className="hidden text-[12.5px] md:block">
          <div
            role="row"
            className={cn(
              columns,
              'border-b border-hairline pb-2.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint',
            )}
          >
            <span role="columnheader">Descrição</span>
            <span role="columnheader">Quando</span>
            <span role="columnheader">Origem</span>
            <span role="columnheader">Categoria</span>
            <span role="columnheader" className="text-right">
              Valor
            </span>
            <span role="columnheader" className="text-center">
              No mês
            </span>
            <span role="columnheader" className="sr-only">
              Ações
            </span>
          </div>

          {rows.map((row, index) => (
            <div
              key={row.id}
              role="row"
              className={cn(
                columns,
                'items-center py-[13px] transition-colors hover:bg-white/[0.02]',
                index < rows.length - 1 && 'border-b border-hairline-soft',
                !row.is_active && 'opacity-55',
              )}
            >
              <div role="cell" className="flex min-w-0 flex-col gap-0.5">
                <span className="truncate font-semibold text-ink">{row.description}</span>
                <span className="text-[10.5px] text-ink-muted">
                  {row.is_active
                    ? row.ends_on
                      ? `até ${formatDayMonth(row.ends_on)}`
                      : 'sem prazo'
                    : 'pausada'}
                </span>
              </div>

              <span role="cell" className="truncate text-[11.5px] font-medium text-ink-soft">
                {row.schedule_label}
              </span>

              <span
                role="cell"
                className="flex min-w-0 items-center gap-1.5 text-[11.5px] font-medium text-ink-soft"
              >
                {row.source_kind === 'cartao' ? (
                  <CreditCard className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
                ) : (
                  <Landmark className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
                )}
                <span className="truncate">{row.source}</span>
              </span>

              <div role="cell" className="min-w-0">
                {row.category ? (
                  <span
                    className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
                    style={{ color: row.category.color, background: `${row.category.color}1F` }}
                  >
                    {row.category.name}
                  </span>
                ) : (
                  <span className="text-[11px] text-ink-muted">Sem categoria</span>
                )}
              </div>

              <div role="cell" className="flex flex-col items-end gap-0.5">
                <Money
                  value={row.amount}
                  className={cn(
                    'text-[13px] font-semibold',
                    row.type === 'receita' ? 'text-green-bright' : 'text-[#FF9E5C]',
                    isPrivate && 'privacy-blur',
                  )}
                />
                {row.occurrences > 1 ? (
                  <span className="text-[10px] font-medium text-ink-muted">
                    ×{row.occurrences} no mês
                  </span>
                ) : null}
              </div>

              <span role="cell" className="flex justify-center">
                <MonthStatus row={row} />
              </span>

              <span role="cell" className="flex justify-end">
                <RowMenu
                  row={row}
                  isLaunching={launchingId === row.id}
                  onLaunch={() => onLaunch(row)}
                  onEdit={() => onEdit(row)}
                  onToggle={(isActive) => onToggle(row, isActive)}
                  onDelete={() => onDelete(row)}
                />
              </span>
            </div>
          ))}
          </div>
        </>
      )}
    </Card>
  )
}

interface RecurrenceCardProps {
  row: RecurrenceRow
  isPrivate: boolean
  isLast: boolean
  isLaunching: boolean
  onLaunch: () => void
  onEdit: () => void
  onToggle: (isActive: boolean) => void
  onDelete: () => void
}

function RecurrenceCard({
  row,
  isPrivate,
  isLast,
  isLaunching,
  onLaunch,
  onEdit,
  onToggle,
  onDelete,
}: RecurrenceCardProps) {
  return (
    <div
      className={cn(
        'flex flex-col gap-2 py-[13px] text-[12.5px]',
        !isLast && 'border-b border-hairline-soft',
        !row.is_active && 'opacity-55',
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="truncate font-semibold text-ink">{row.description}</span>
          <span className="truncate text-[10.5px] text-ink-muted">
            {row.is_active
              ? row.ends_on
                ? `até ${formatDayMonth(row.ends_on)}`
                : 'sem prazo'
              : 'pausada'}
            {' · '}
            {row.schedule_label}
          </span>
        </div>

        <div className="flex shrink-0 items-start gap-1.5">
          <div className="flex flex-col items-end gap-0.5">
            <Money
              value={row.amount}
              className={cn(
                'text-[13px] font-semibold',
                row.type === 'receita' ? 'text-green-bright' : 'text-[#FF9E5C]',
                isPrivate && 'privacy-blur',
              )}
            />
            {row.occurrences > 1 ? (
              <span className="text-[10px] font-medium text-ink-muted">
                ×{row.occurrences} no mês
              </span>
            ) : null}
          </div>

          <RowMenu
            row={row}
            isLaunching={isLaunching}
            onLaunch={onLaunch}
            onEdit={onEdit}
            onToggle={onToggle}
            onDelete={onDelete}
          />
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        {row.category ? (
          <span
            className="inline-block max-w-full truncate rounded-full px-[9px] py-[3px] text-[11px] font-semibold"
            style={{ color: row.category.color, background: `${row.category.color}1F` }}
          >
            {row.category.name}
          </span>
        ) : (
          <span className="text-[11px] text-ink-muted">Sem categoria</span>
        )}
        <MonthStatus row={row} />
      </div>

      <span className="flex min-w-0 items-center gap-1.5 text-[11.5px] font-medium text-ink-soft">
        {row.source_kind === 'cartao' ? (
          <CreditCard className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
        ) : (
          <Landmark className="size-3.5 shrink-0 text-ink-faint" aria-hidden="true" />
        )}
        <span className="truncate">{row.source}</span>
      </span>
    </div>
  )
}

/** Estado da regra no mês consultado: quantas ocorrências já viraram lançamento. */
function MonthStatus({ row }: { row: RecurrenceRow }) {
  if (!row.is_active || row.occurrences === 0) {
    return <span className="text-[11px] text-ink-faint">—</span>
  }

  if (row.pending === 0) {
    return (
      <span className="inline-flex items-center gap-1 rounded-full bg-green/[0.12] px-[9px] py-[3px] text-[10.5px] font-bold text-green-bright">
        <Check className="size-3" aria-hidden="true" />
        Lançada
      </span>
    )
  }

  return (
    <span className="rounded-full bg-purple/[0.14] px-[9px] py-[3px] text-[10.5px] font-bold text-purple-light">
      {row.launched > 0 ? `${row.pending} a lançar` : 'Pendente'}
    </span>
  )
}

interface MenuProps {
  row: RecurrenceRow
  isLaunching: boolean
  onLaunch: () => void
  onEdit: () => void
  onToggle: (isActive: boolean) => void
  onDelete: () => void
}

function RowMenu({ row, isLaunching, onLaunch, onEdit, onToggle, onDelete }: MenuProps) {
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
        aria-label={`Ações de ${row.description}`}
        disabled={isLaunching}
        className="grid size-7 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-white/[0.06] hover:text-ink disabled:opacity-40"
      >
        <MoreHorizontal className="size-4" aria-hidden="true" />
      </button>

      {isOpen ? (
        <div
          role="menu"
          className="absolute right-0 top-8 z-20 flex w-[204px] flex-col gap-0.5 rounded-[13px] border border-hairline-strong bg-surface-alt p-1.5 shadow-[0_18px_40px_rgba(0,0,0,0.55)]"
        >
          {row.is_active && row.pending > 0 ? (
            <MenuItem icon={Zap} onClick={() => run(onLaunch)}>
              {row.pending === 1 ? 'Lançar no mês' : `Lançar ${row.pending} ocorrências`}
            </MenuItem>
          ) : null}

          <MenuItem icon={Pencil} onClick={() => run(onEdit)}>
            Editar
          </MenuItem>

          {row.is_active ? (
            <MenuItem icon={Pause} onClick={() => run(() => onToggle(false))}>
              Pausar
            </MenuItem>
          ) : (
            <MenuItem icon={Play} onClick={() => run(() => onToggle(true))}>
              Retomar
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

export function RecurrenceListSkeleton() {
  return (
    <Card className="flex flex-col gap-3.5">
      <Skeleton className="h-4 w-40" />
      {Array.from({ length: 5 }).map((_, index) => (
        <Skeleton key={index} className="h-[38px] w-full" />
      ))}
    </Card>
  )
}
