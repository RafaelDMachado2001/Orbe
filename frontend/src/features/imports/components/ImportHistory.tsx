import { CreditCard, History, Undo2, Wallet } from 'lucide-react'

import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatDate } from '@/lib/format'
import type { ImportBatch } from '@/types/api'

interface Props {
  batches: ImportBatch[]
  isPending: boolean
  undoingId: number | null
  onUndo: (batch: ImportBatch) => void
}

/**
 * As últimas importações, com o botão de desfazer.
 *
 * O histórico existe porque um extrato traz dezenas de linhas de uma vez: sem
 * ele, corrigir um arquivo importado por engano seria caçar lançamento por
 * lançamento no extrato.
 */
export function ImportHistory({ batches, isPending, undoingId, onUndo }: Props) {
  return (
    <Card className="flex flex-col gap-3">
      <CardHeader
        title="Importações recentes"
        subtitle={batches.length > 0 ? 'Desfazer remove o lote inteiro' : undefined}
      />

      {isPending ? (
        <div className="flex flex-col gap-2.5">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-[74px] w-full rounded-[13px]" />
          ))}
        </div>
      ) : batches.length === 0 ? (
        <p className="rounded-[13px] border border-dashed border-hairline px-4 py-6 text-center text-[11.5px] leading-relaxed text-ink-muted">
          Nenhuma importação por aqui ainda. O que você importar aparece nesta lista, com a opção de
          desfazer.
        </p>
      ) : (
        batches.map((batch) => (
          <BatchRow
            key={batch.id}
            batch={batch}
            isUndoing={undoingId === batch.id}
            onUndo={() => onUndo(batch)}
          />
        ))
      )}
    </Card>
  )
}

function BatchRow({
  batch,
  isUndoing,
  onUndo,
}: {
  batch: ImportBatch
  isUndoing: boolean
  onUndo: () => void
}) {
  const Icon = batch.target === 'cartao' ? CreditCard : Wallet

  return (
    <article className="flex flex-col gap-2 rounded-[13px] border border-hairline bg-surface-alt p-3.5">
      <div className="flex items-start gap-2.5">
        <span className="grid size-7 shrink-0 place-items-center rounded-[9px] bg-white/[0.05] text-ink-soft">
          <Icon className="size-3.5" aria-hidden="true" />
        </span>

        <div className="flex min-w-0 flex-col gap-px">
          <span className="truncate text-[12.5px] font-semibold text-ink">{batch.filename}</span>
          <span className="truncate text-[11px] font-medium text-ink-muted">
            {batch.destination} · {batch.format_label}
          </span>
        </div>

        <span className="ml-auto shrink-0 rounded-full bg-white/[0.06] px-[9px] py-[3px] text-[10.5px] font-bold text-ink-soft">
          {batch.imported_count}
        </span>
      </div>

      <p className="text-[11px] leading-relaxed text-ink-muted">
        <History className="mr-1 inline size-3 align-[-2px]" aria-hidden="true" />
        {period(batch)}
        {batch.skipped_count > 0 ? ` · ${batch.skipped_count} deixados de fora` : ''}
        {batch.remaining_count !== batch.imported_count
          ? ` · ${batch.remaining_count} ainda no extrato`
          : ''}
      </p>

      {batch.can_undo ? (
        <button
          type="button"
          onClick={onUndo}
          disabled={isUndoing}
          className={cn(
            'flex items-center gap-1.5 self-start rounded-[9px] px-2 py-1 text-[11px] font-semibold text-ink-soft transition-colors',
            'hover:bg-danger/[0.12] hover:text-danger disabled:cursor-not-allowed disabled:opacity-60',
          )}
        >
          <Undo2 className="size-3" aria-hidden="true" />
          {isUndoing ? 'Desfazendo…' : 'Desfazer importação'}
        </button>
      ) : (
        <p className="text-[10.5px] font-medium leading-relaxed text-ink-faint">
          {batch.undo_block_reason ?? 'Nada deste lote continua no extrato.'}
        </p>
      )}
    </article>
  )
}

function period(batch: ImportBatch): string {
  if (batch.period_start === null || batch.period_end === null) {
    return formatDate(batch.created_at.slice(0, 10))
  }

  return batch.period_start === batch.period_end
    ? formatDate(batch.period_start)
    : `${formatDate(batch.period_start)} a ${formatDate(batch.period_end)}`
}
